<?php

namespace App\Services\Mikrotik;

use App\Models\MikrotikRouter;
use Illuminate\Support\Facades\Crypt;

/**
 * Cliente para la API REST de RouterOS 7 (/rest/...).
 *
 * Usa curl_multi para consultar todos los routers y endpoints en paralelo, con timeouts
 * individuales, verificación SSL configurable y sin seguir redirecciones
 * (evita SSRF vía redirection). Las credenciales se descifran en memoria y
 * nunca se registran en logs ni se devuelven en respuestas.
 */
class MikrotikRestClient
{
    /**
     * Endpoints consultados en cada búsqueda.
     *
     * @var array<string,string>
     */
    private const SOURCES = [
        'dhcp' => '/rest/ip/dhcp-server/lease',
        'ppp' => '/rest/ppp/active',
        'arp' => '/rest/ip/arp',
        'hotspot_binding' => '/rest/ip/hotspot/ip-binding',
    ];

    public function __construct(private readonly \Illuminate\Contracts\Encryption\Encrypter $encrypter) {}

    /**
     * Comprueba conectividad y credenciales contra /rest/system/resource.
     *
     * @return array{version: string, board_name: string, uptime: ?string, latency_ms: int}
     *
     * @throws MikrotikException
     */
    public function testConnection(MikrotikRouter $router): array
    {
        $started = microtime(true);

        [$code, $body, $error, $detail] = $this->execute($router, 'GET', '/rest/system/resource');

        $latency = (int) round((microtime(true) - $started) * 1000);

        if ($error !== null) {
            throw new MikrotikException(
                $error === 'timeout' ? 'Tiempo de espera agotado.' : 'No fue posible conectar con el router.',
                $error,
                null,
                $detail,
            );
        }

        if ($code === 401 || $code === 403) {
            throw MikrotikException::auth($this->endpoint($router)."/rest/system/resource → HTTP {$code}");
        }

        if ($code >= 400) {
            throw new MikrotikException(
                "El router respondió HTTP {$code}",
                'http',
                null,
                $this->endpoint($router)."/rest/system/resource → HTTP {$code}",
            );
        }

        return [
            'version' => $body['version'] ?? 'desconocida',
            'board_name' => $body['board-name'] ?? 'desconocido',
            'uptime' => $body['uptime'] ?? null,
            'latency_ms' => $latency,
            'endpoint' => $this->endpoint($router),
        ];
    }

    /**
     * URL base usada para hablar con el router (sin credenciales).
     */
    public function endpoint(MikrotikRouter $router): string
    {
        return sprintf('%s://%s:%d', $router->use_ssl ? 'https' : 'http', $router->host, $router->api_port);
    }

    /**
     * Consulta todos los mecanismos de asignación en un router.
     *
     * @return array{dhcp: array, ppp: array, arp: array, hotspot_binding: array, error: ?string, error_detail: ?string, partial: bool}
     */
    public function collectSources(MikrotikRouter $router, ?string $mac = null): array
    {
        return $this->collectSourcesMany([$router], $mac)[$router->id];
    }

    /**
     * Consulta los mecanismos de asignación de varios routers a la vez.
     *
     * Todas las peticiones (routers × endpoints) van en un único curl_multi:
     * la búsqueda tarda lo que el router más lento, no la suma de todos.
     * Con $mac (formato AA:BB:CC:DD:EE:FF) el filtro lo aplica el router y
     * solo viajan las filas de esa MAC, no las tablas completas.
     *
     * Un endpoint no disponible (por ejemplo PPP sin configurar) NO invalida
     * el router: solo es error cuando ningún endpoint respondió.
     *
     * @param  iterable<MikrotikRouter>  $routers
     * @return array<int, array<string,mixed>> Indexado por id de router.
     */
    public function collectSourcesMany(iterable $routers, ?string $mac = null): array
    {
        $out = [];
        $state = [];
        $handles = [];
        $multi = curl_multi_init();

        try {
            foreach ($routers as $router) {
                $state[$router->id] = [
                    'router' => $router,
                    'results' => array_fill_keys(array_keys(self::SOURCES), []),
                    'failures' => [],
                    'details' => [],
                    'answered' => 0,
                    'latency_ms' => 0,
                ];

                try {
                    foreach (self::SOURCES as $key => $path) {
                        $handle = $this->makeHandle($router, 'GET', $path.$this->macFilter($key, $mac));
                        $handles[spl_object_id($handle)] = [$handle, $router->id, $key];
                        curl_multi_add_handle($multi, $handle);
                    }
                } catch (MikrotikException $e) {
                    // Credenciales ilegibles: falla este router, los demás siguen.
                    $out[$router->id] = $this->sourcesPayload(
                        array_fill_keys(array_keys(self::SOURCES), []),
                        $e->errorType,
                        $e->getMessage(),
                        false,
                    );
                    unset($state[$router->id]);
                }
            }

            do {
                $status = curl_multi_exec($multi, $running);
                if ($running) {
                    curl_multi_select($multi, 0.2);
                }
            } while ($running && $status === CURLM_OK);

            // El código de resultado de cada transferencia se obtiene de la cola
            // de mensajes de curl_multi. curl_errno() no es fiable en modo multi
            // en todas las compilaciones de curl.
            $transferResults = [];
            while (($info = curl_multi_info_read($multi)) !== false) {
                $transferResults[spl_object_id($info['handle'])] = (int) $info['result'];
            }

            foreach ($handles as $id => [$handle, $routerId, $key]) {
                $code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
                $transferResult = $transferResults[$id] ?? CURLE_OK;
                $body = (string) curl_multi_getcontent($handle);
                $curlError = curl_error($handle);
                $elapsedMs = (int) round(curl_getinfo($handle, CURLINFO_TOTAL_TIME) * 1000);
                $endpoint = (string) curl_getinfo($handle, CURLINFO_EFFECTIVE_URL); // sin credenciales

                curl_multi_remove_handle($multi, $handle);
                curl_close($handle);
                unset($handles[$id]);

                if (! isset($state[$routerId])) {
                    continue;
                }

                $state[$routerId]['latency_ms'] = max($state[$routerId]['latency_ms'], $elapsedMs);

                if ($transferResult !== CURLE_OK) {
                    $state[$routerId]['failures'][$key] = $transferResult === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'unreachable';
                    $state[$routerId]['details'][$key] = trim($endpoint.' → '.($curlError ?: 'sin respuesta'));

                    continue;
                }

                if ($code === 401 || $code === 403) {
                    $state[$routerId]['failures'][$key] = 'auth';
                    $state[$routerId]['details'][$key] = "{$endpoint} → HTTP {$code}";

                    continue;
                }

                // El router respondió. 400/404 = el menú no existe en este equipo
                // (normal sin PPP/HS): no es fallo de conectividad.
                $state[$routerId]['answered']++;

                if ($code < 400) {
                    $decoded = json_decode($body, true);
                    $state[$routerId]['results'][$key] = is_array($decoded) ? $decoded : [];
                }
            }
        } finally {
            foreach ($handles as [$handle]) {
                curl_multi_remove_handle($multi, $handle);
                curl_close($handle);
            }
            curl_multi_close($multi);
        }

        foreach ($state as $routerId => $s) {
            $error = $this->classifyRouterError($s['failures'], $s['answered']);

            $out[$routerId] = $this->sourcesPayload(
                $s['results'],
                $error,
                $error === null ? null : (reset($s['details']) ?: null),
                $error === null && $s['failures'] !== [],
                $s['latency_ms'],
            );
        }

        return $out;
    }

    /**
     * Filtro REST de RouterOS (?campo=valor) para que el router devuelva solo
     * las filas de la MAC buscada. RouterOS guarda las MAC en mayúsculas con ":".
     */
    private function macFilter(string $source, ?string $mac): string
    {
        if ($mac === null) {
            return '';
        }

        return '?'.http_build_query([$source === 'ppp' ? 'caller-id' : 'mac-address' => $mac]);
    }

    /**
     * @param  array<string,array>  $results
     */
    private function sourcesPayload(array $results, ?string $error, ?string $detail, bool $partial, int $latencyMs = 0): array
    {
        return [
            'dhcp' => $results['dhcp'],
            'ppp' => $results['ppp'],
            'arp' => $results['arp'],
            'hotspot_binding' => $results['hotspot_binding'],
            'error' => $error,
            'error_detail' => $detail,
            'partial' => $partial,
            'latency_ms' => $latencyMs,
        ];
    }

    /**
     * El router es accesible si al menos un endpoint respondió, aunque sea sin
     * filas (con el filtro por MAC, "vacío" es lo normal).
     *
     * @param  array<string,string>  $failures
     */
    private function classifyRouterError(array $failures, int $answered): ?string
    {
        if ($answered > 0 || $failures === []) {
            return null;
        }

        if (in_array('auth', $failures, true)) {
            return 'auth';
        }

        return count(array_unique($failures)) === 1 && reset($failures) === 'timeout'
            ? 'timeout'
            : 'unreachable';
    }

    /**
     * @return array{0:int, 1:array<string,mixed>, 2:?string, 3:?string}
     */
    private function execute(MikrotikRouter $router, string $method, string $path): array
    {
        $handle = $this->makeHandle($router, $method, $path);

        $body = curl_exec($handle);
        $code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $errno = curl_errno($handle);
        $curlError = curl_error($handle);
        $error = null;
        $detail = null;

        if ($errno !== 0) {
            $error = $errno === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'unreachable';
            $detail = trim($this->endpoint($router).$path.' → '.($curlError ?: 'sin respuesta'));
        }

        curl_close($handle);

        return [
            $code,
            is_string($body) && $body !== '' ? (array) json_decode($body, true) : [],
            $error,
            $detail,
        ];
    }

    /**
     * @return \CurlHandle
     *
     * @throws MikrotikException
     */
    private function makeHandle(MikrotikRouter $router, string $method, string $path)
    {
        $scheme = $router->use_ssl ? 'https' : 'http';
        $url = sprintf('%s://%s:%d%s', $scheme, $router->host, $router->api_port, $path);

        try {
            $password = Crypt::decryptString($router->api_password);
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            throw new MikrotikException('No fue posible descifrar las credenciales del router. Vuelve a guardarlas.', 'auth');
        }

        $handle = curl_init();

        curl_setopt_array($handle, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $router->api_user.':'.$password,
            CURLOPT_CONNECTTIMEOUT_MS => $router->connect_timeout_ms,
            CURLOPT_TIMEOUT_MS => $router->read_timeout_ms,
            CURLOPT_SSL_VERIFYPEER => $router->verify_ssl,
            CURLOPT_SSL_VERIFYHOST => $router->verify_ssl ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        if ($method !== 'GET') {
            curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        }

        return $handle;
    }
}
