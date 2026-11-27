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
            }

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $setting->host,
                'mail.mailers.smtp.port' => (int) $setting->port,
                'mail.mailers.smtp.username' => $setting->username,
                'mail.mailers.smtp.password' => $password,
                'mail.mailers.smtp.scheme' => $this->scheme($setting->encryption),
                'mail.mailers.smtp.encryption' => $setting->encryption === 'none' ? null : $setting->encryption,
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
     * RouterOS/Proveedores: ssl implica conexión cifrada desde el inicio
     * (smtps); tls usa STARTTLS sobre smtp; sin cifrado, smtp plano.
     */
    private function scheme(?string $encryption): string
    {
        return $encryption === 'ssl' ? 'smtps' : 'smtp';
    }
}
