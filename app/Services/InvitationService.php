<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Emisión y entrega de invitaciones de activación de cuenta.
 *
 * El token se persiste siempre como hash SHA-256; el valor en claro solo
 * existe durante la petición y se entrega por correo (y como respaldo al
 * administrador) para que la cuenta pueda activarse.
 */
class InvitationService
{
    /**
     * Invalida invitaciones pendientes previas y emite una nueva.
     *
     * @return array{invitation: Invitation, url: string, mailed: bool}
     */
    public function issue(User $user, ?User $admin = null, int $days = 7): array
    {
        Invitation::where('user_id', $user->id)
            ->whereNull('accepted_at')
            ->update(['expires_at' => now()]);

        $raw = Str::random(80);

        $invitation = Invitation::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'token' => hash('sha256', $raw),
            'expires_at' => now()->addDays($days),
            'invited_by' => $admin?->fullName() ?? 'Sistema',
        ]);

        $url = route('invite.accept', ['token' => $raw]);

        return [
            'invitation' => $invitation,
            'url' => $url,
            'mailed' => $this->send($user, $url, $admin, $invitation->expires_at),
        ];
    }

    /**
     * Un fallo de correo no debe impedir crear la invitación: el enlace se
     * devuelve igualmente para entregarlo por otro medio.
     *
     * Con el transporte "log" (sin SMTP configurado) el mensaje no llega a
     * ninguna bandeja: se considera no entregado para que el administrador
     * reciba el enlace en pantalla.
     */
    private function send(User $user, string $url, ?User $admin, $expiresAt): bool
    {
        if (config('mail.default') === 'log') {
            Log::info('Correo en modo log: la invitación no se entrega, se muestra el enlace al administrador.', [
                'user_id' => $user->id,
            ]);

            return false;
        }

        try {
            $user->notify(new InvitationNotification($url, $admin?->fullName(), $expiresAt));

            return true;
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar la invitación por correo', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
