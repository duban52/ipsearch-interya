<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $url,
        private readonly ?string $invitedBy = null,
        private readonly ?\DateTimeInterface $expiresAt = null,
    ) {}

    /**
     * @return array<int,string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Activa tu cuenta de IPSearch Interya')
            ->greeting('Hola '.$notifiable->name)
            ->line('Se creó una cuenta de IPSearch Interya para ti'
                .($this->invitedBy ? " por {$this->invitedBy}" : '').'.')
            ->line('Para activarla, establece tu contraseña con el siguiente enlace:')
            ->action('Activar mi cuenta', $this->url);

        if ($this->expiresAt) {
            $message->line('El enlace caduca el '.$this->expiresAt->format('d/m/Y H:i').' y solo puede usarse una vez.');
        }

        return $message
            ->line('Si no esperabas esta invitación, puedes ignorar este correo.');
    }
}
