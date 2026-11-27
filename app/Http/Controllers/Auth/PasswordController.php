<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordController extends Controller
{
    public function showLinkRequestForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Envía el enlace de recuperación usando el broker nativo de Laravel
     * (tabla password_reset_tokens, tokens con caducidad y throttle).
     */
    public function sendLinkEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'Formato de correo inválido.',
        ]);

        $user = User::where('email', $request->email)->first();

        // Solo las cuentas activas pueden recuperar la contraseña. La respuesta
        // es siempre la misma para no revelar si el correo existe.
        if ($user && $user->isActive()) {
            try {
                Password::sendResetLink(['email' => $user->email]);
            } catch (\Throwable $e) {
                // Un fallo del servidor de correo no debe revelar nada al visitante.
                logger()->warning('No se pudo enviar el enlace de recuperación', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            AuditLog::record('password_reset_requested', $user, 'Solicitud de recuperación de contraseña', $request->ip());
        }

        return back()->with('status', 'Si el correo existe y la cuenta está activa, recibirás un enlace.');
    }

    public function showResetForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    /**
     * Restablece la contraseña validando token + email con el broker.
     */
    public function resetFromToken(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->mixedCase()->numbers()->symbols()],
        ], [
            'password.confirmed' => 'La confirmación no coincide.',
            'password.min' => 'Mínimo 10 caracteres con mayúscula, minúscula, número y símbolo.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                AuditLog::record('password_reset', $user, 'Contraseña restablecida correctamente', $request->ip());
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('success', 'Contraseña actualizada. Inicia sesión con la nueva.');
    }

    /**
     * Cambio de contraseña del usuario autenticado desde su perfil.
     */
    public function updateCurrent(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->mixedCase()->numbers()->symbols()],
        ], [
            'current_password.required' => 'La contraseña actual es obligatoria.',
            'password.confirmed' => 'La confirmación no coincide.',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            AuditLog::record('password_change_failed', $user, 'Contraseña actual incorrecta', $request->ip(), 'fail');

            return back()->withErrors(['current_password' => 'La contraseña actual no es correcta.']);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
        ])->save();

        AuditLog::record('password_changed', $user, 'Contraseña cambiada por el usuario', $request->ip());

        return back()->with('success', 'Contraseña actualizada.');
    }
}
