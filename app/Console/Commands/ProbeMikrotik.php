<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Diagnóstico de conectividad con un MikroTik, sin usar credenciales ni tocar
 * la base de datos.
 *
 * Sirve para separar tres problemas distintos que en la interfaz se ven igual:
 *  1. el host no se resuelve o el puerto está cerrado (red/firewall),
 *  2. el puerto responde pero no es el del servicio web REST (puerto equivocado),
 *  3. el servicio REST existe y pide credenciales (el problema es el usuario).
 */
class ProbeMikrotik extends Command
{
    protected $signature = 'routers:probe
        {host : IP o hostname del router (acepta http://host:puerto y lo normaliza)}
        {--port= : Puerto a probar (por defecto 443, o 80 con --plain)}
        {--plain : Usar HTTP en lugar de HTTPS}
        {--timeout=5 : Segundos de espera}';

    protected $description = 'Diagnostica la conectividad y el servicio REST de un router MikroTik';

    public function handle(): int
    {
        $input = trim((string) $this->argument('host'));
        $scheme = $this->option('plain') ? 'http' : 'https';
        $timeout = max(1, (int) $this->option('timeout'));

        // Normalizar si el usuario pega una URL completa.
        $host = $input;
        $portFromUrl = null;

        if (preg_match('#^(https?)://([^/:]+)(?::(\d+))?#i', $input, $m)) {
            $scheme = strtolower($m[1]);
            $host = $m[2];
            $portFromUrl = isset($m[3]) ? (int) $m[3] : null;
        }

        $port = (int) ($this->option('port') ?: $portFromUrl ?: ($scheme === 'https' ? 443 : 80));

        $this->newLine();
        $this->line("Diagnóstico de <info>{$host}:{$port}</info> ({$scheme})");
        $this->newLine();

        // 1. Resolución
        $ip = $host;
        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            $ip = gethostbyname($host);
            if ($ip === $host) {
                $this->error("DNS: no se pudo resolver '{$host}'.");
                $this->line('  → Revisa que el hostname sea correcto o usa la IP directamente.');

                return self::FAILURE;
            }
            $this->line("  DNS:        {$host} → <info>{$ip}</info>");
        } else {
            $this->line("  DNS:        IP directa ({$ip})");
        }

        // 2. TCP
        $target = ($scheme === 'https' ? 'ssl://' : '').$ip;
        $errno = 0;
        $errstr = '';
        $start = microtime(true);
        $socket = @fsockopen($target, $port, $errno, $errstr, $timeout);
        $tcpMs = (int) round((microtime(true) - $start) * 1000);

        if ($socket === false) {
            $this->warn("  TCP {$port}:  CERRADO o inalcanzable ({$tcpMs} ms) — {$errstr}");
            $this->line('  → El router no acepta conexiones en ese puerto desde este servidor.');
            $this->line('  → Causas típicas: puerto equivocado, firewall/NAT, VPN o ruta inexistente.');
        } else {
            fclose($socket);
            $this->info("  TCP {$port}:  ABIERTO ({$tcpMs} ms)");
        }

        // 3. HTTP sobre /rest/
        $url = "{$scheme}://{$ip}:{$port}";
        $response = $this->httpGet($url.'/rest/', $timeout);

        if ($response['code'] === 0) {
            $this->warn('  HTTP:       sin respuesta ('.$response['error'].')');
        } else {
            $this->line("  HTTP /rest/: <info>HTTP {$response['code']}</info>");
        }

        $this->newLine();
        $this->line('<comment>Interpretación</comment>');

        if (in_array($port, [8728, 8729], true)) {
            $this->warn('  • Los puertos 8728/8729 pertenecen a la API BINARIA de RouterOS, no a la API REST.');
            $this->line('    Para REST usa el puerto del servicio web: 80 (HTTP) o 443 (HTTPS).');
            $this->line('    Si el servicio web está en otro puerto, se configura en /ip/service.');
        }

        $this->line(match (true) {
            $response['code'] === 401 || $response['code'] === 403 => '  • El servicio REST responde y exige credenciales: la red y el puerto son correctos. '
                .'Si la prueba en el panel falla, el problema es el usuario/contraseña o sus permisos (grupo con "read" y acceso API).',
            $response['code'] === 200 => '  • /rest/ respondió sin credenciales (configuración poco habitual). Revisa que sea realmente el router esperado.',
            $response['code'] === 404 => '  • El servidor web responde pero no existe /rest/: la API REST requiere RouterOS 7.1 o superior '
                .'y el servicio web habilitado en /ip/service.',
            $response['code'] === 0 => '  • No hubo respuesta HTTP. Si el TCP estaba abierto, el puerto no habla el servicio web de RouterOS.',
            default => '  • Respuesta inesperada del servicio web. Revisa la versión de RouterOS y el puerto.',
        });

        $this->newLine();
        $this->line('  Cuando la red y el puerto estén confirmados, usa el botón <info>Probar</info> del panel o:');
        $this->line('  <info>php artisan routers:health --router=ID</info>');

        return self::SUCCESS;
    }

    /**
     * @return array{code:int, error:string}
     */
    private function httpGet(string $url, int $timeout): array
    {
        $handle = curl_init();

        curl_setopt_array($handle, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        curl_exec($handle);
        $code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        return ['code' => $code, 'error' => $error ?: 'sin detalle'];
    }
}
