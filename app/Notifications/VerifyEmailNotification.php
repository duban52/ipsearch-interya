<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verifica tu correo — IPSearch Interya')
            ->greeting('Hola '.$notifiable->name)
            ->line('Confirma tu dirección de correo para completar la configuración de tu cuenta.')
            ->action('Verificar mi correo', $this->verificationUrl($notifiable))
            ->line('Si no creaste esta cuenta, ignora este correo.');
    }
}
