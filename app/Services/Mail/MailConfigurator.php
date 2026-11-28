<?php

namespace App\Services\Mail;

use App\Models\MailSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Aplica al mailer la configuración SMTP guardada por el superadministrador.
 *
 * El correo del sistema no vive en el .env: se administra desde el panel
 * (admin/config) y se aplica en cada arranque. Cualquier fallo (tabla aún no
 * migrada, APP_KEY cambiada, BD caída) se registra y se ignora para no romper
 * la aplicación ni los comandos de consola.
 */
class MailConfigurator
{
    public function apply(): void
    {
        try {
            if (! Schema::hasTable('mail_settings')) {
                return;
            }

            $setting = MailSetting::query()->where('is_active', true)->first();

            if (! $setting || blank($setting->host)) {
                return;
            }

            $password = null;

            if (filled($setting->password)) {
                $password = Crypt::decryptString($setting->password);

                // Google muestra la contraseña de aplicación en bloques
                // ("abcd efgh ijkl mnop"); pegada con espacios, Gmail responde 535.
                if (self::isGmail($setting->host)) {
                    $password = preg_replace('/\s+/', '', $password);
                }
            }

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => trim($setting->host),
                'mail.mailers.smtp.port' => (int) $setting->port,
                'mail.mailers.smtp.username' => $setting->username ? trim($setting->username) : null,
                'mail.mailers.smtp.password' => $password,
                'mail.mailers.smtp.scheme' => self::scheme((int) $setting->port, $setting->encryption),
                'mail.mailers.smtp.timeout' => 10,
                'mail.from.address' => $setting->from_address,
                'mail.from.name' => $setting->from_name,
            ]);
        } catch (\Throwable $e) {
            // Sin datos sensibles en el log.
            Log::warning('No se pudo aplicar la configuración SMTP guardada', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 465 es TLS implícito (smtps) y 587/25 usan STARTTLS sobre smtp, elija lo
     * que elija el formulario: "TLS + 465" o "SSL + 587" dejaban la conexión
     * colgada hasta el timeout (el caso típico con Gmail). Para otros puertos
     * manda el cifrado elegido.
     */
    public static function scheme(int $port, ?string $encryption): string
    {
        return match (true) {
            $port === 465 => 'smtps',
            in_array($port, [25, 587], true) => 'smtp',
            default => $encryption === 'ssl' ? 'smtps' : 'smtp',
        };
    }

    public static function isGmail(?string $host): bool
    {
        return (bool) preg_match('/(^|\.)(gmail|googlemail)\.com$/i', trim((string) $host));
    }
}
