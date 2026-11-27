<?php

namespace App\Services\Mikrotik;

use App\Models\MikrotikRouter;
use Illuminate\Support\Facades\Crypt;

/**
 * Cliente para la API REST de RouterOS 7 (/rest/...).
 *
 * Usa curl_multi para consultar varios endpoints en paralelo, con timeouts
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
     * Un endpoint no disponible (por ejemplo PPP sin configurar) NO invalida
     * la consulta: solo se marca error de router cuando no se obtuvo ningún
     * dato de ningún endpoint.
     *
     * @return array{dhcp: array, ppp: array, arp: array, hotspot_binding: array, error: ?string, partial: bool}
     */
    public function collectSources(MikrotikRouter $router): array
    {
        $results = array_fill_keys(array_keys(self::SOURCES), []);
        $failures = [];
        $details = [];

        $multi = curl_multi_init();
        $handles = [];

        try {
            foreach (self::SOURCES as $key => $path) {
                $handle = $this->makeHandle($router, 'GET', $path);
                $handles[$key] = $handle;
                curl_multi_add_handle($multi, $handle);
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

            foreach ($handles as $key => $handle) {
                $code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
                $transferResult = $transferResults[spl_object_id($handle)] ?? CURLE_OK;
                $body = (string) curl_multi_getcontent($handle);
                $curlError = curl_error($handle);

                curl_multi_remove_handle($multi, $handle);
                curl_close($handle);
                unset($handles[$key]);

                if ($transferResult !== CURLE_OK) {
                    $failures[$key] = $transferResult === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'unreachable';
                    $details[$key] = trim($this->endpoint($router).self::SOURCES[$key].' → '.($curlError ?: 'sin respuesta'));

                    continue;
                }

                if ($code === 401 || $code === 403) {
                    $failures[$key] = 'auth';
                    $details[$key] = $this->endpoint($router).self::SOURCES[$key]." → HTTP {$code}";

                    continue;
                }

                // 400/404: el menú no existe en este router (normal en equipos sin
                // PPP/HS). No es un fallo de conectividad.
                if ($code >= 400) {
                    continue;
                }

                $decoded = json_decode($body, true);
                $results[$key] = is_array($decoded) ? $decoded : [];
            }
        } finally {
            foreach ($handles as $handle) {
                curl_multi_remove_handle($multi, $handle);
                curl_close($handle);
            }
            curl_multi_close($multi);
        }

        $error = $this->classifyRouterError($failures, $results);

        return [
            'dhcp' => $results['dhcp'],
            'ppp' => $results['ppp'],
            'arp' => $results['arp'],
            'hotspot_binding' => $results['hotspot_binding'],
            'error' => $error,
            'error_detail' => $error === null ? null : (reset($details) ?: null),
            'partial' => $error === null && $failures !== [],
        ];
    }

    /**
     * @param  array<string,string>  $failures
     * @param  array<string,array>  $results
     */
    private function classifyRouterError(array $failures, array $results): ?string
    {
        $hasData = count(array_filter($results, fn ($rows) => $rows !== [])) > 0;

        if ($hasData || $failures === []) {
            return null; // Accesible; sin datos es simplemente "no hay coincidencias".
        }

        if (in_array('auth', $failures, true)) {
            return 'auth';
        }

        if (count($failures) === count(self::SOURCES)) {
            return count(array_unique($failures)) === 1 && reset($failures) === 'timeout'
                ? 'timeout'
                : 'unreachable';
        }

        return null;
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
