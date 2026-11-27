<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Restablece tu contraseña — IPSearch Interya')
            ->greeting('Hola '.$notifiable->name)
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta.')
            ->action('Restablecer contraseña', $this->resetUrl($notifiable))
            ->line("El enlace caduca en {$expire} minutos y solo puede usarse una vez.")
            ->line('Si no solicitaste este cambio, ignora este correo: tu contraseña actual sigue siendo válida.');
    }
}
