<?php

namespace App\Services\Mikrotik;

class MikrotikException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorType = 'error',
        ?\Throwable $previous = null,
        public readonly ?string $detail = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function timeout(?string $detail = null): self
    {
        return new self('Tiempo de espera agotado al conectar con el router.', 'timeout', null, $detail);
    }

    public static function auth(?string $detail = null): self
    {
        return new self('Credenciales rechazadas por el router.', 'auth', null, $detail);
    }

    public static function unreachable(?string $detail = null): self
    {
        return new self('No fue posible conectar con el router.', 'unreachable', null, $detail);
    }

    /**
     * Explicación accionable del fallo, en español y sin datos sensibles.
     */
    public function hint(): string
    {
        return match ($this->errorType) {
            'timeout' => 'El router no respondió dentro del tiempo configurado. Revisa: 1) el puerto debe ser el del '
                .'servicio web REST (80 para HTTP, 443 para HTTPS), NO 8728/8729 que son de la API binaria; '
                .'2) en el router, /ip/service debe tener habilitado "www" (80) o "www-ssl" (443); '
                .'3) que el equipo sea alcanzable desde el servidor (VPN, firewall, ruta).',
            'unreachable' => 'No se pudo abrir la conexión TCP al host y puerto indicados. Suele ser un puerto '
                .'equivocado, un firewall/NAT bloqueando o una ruta/VPN inexistente desde el servidor.',
            'auth' => 'El router respondió pero rechazó las credenciales (401/403). Verifica usuario y contraseña '
                .'y que el grupo del usuario tenga permisos de lectura (y de API) habilitados.',
            'http' => 'El servicio web respondió con un error HTTP. La API REST requiere RouterOS 7.1 o superior '
                .'con el servicio web (www / www-ssl) habilitado.',
            default => 'Error de comunicación con el router. Revisa la configuración y los logs del servidor.',
        };
    }
}
