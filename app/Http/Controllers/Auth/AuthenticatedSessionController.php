<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'El correo o usuario es obligatorio.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);

        $field = filter_var($credentials['email'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $key = 'login:'.strtolower($credentials['email']).'|'.$request->ip();

        $maxAttempts = (int) \App\Models\SystemSetting::get('login_max_attempts', 5);
        $decaySeconds = (int) \App\Models\SystemSetting::get('login_decay_seconds', 300);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            AuditLog::record('login_locked', null, "Bloqueo temporal por intentos fallidos desde {$request->ip()}", $request->ip(), 'denied');

            throw ValidationException::withMessages([
                'email' => "Demasiados intentos fallidos. Intenta de nuevo en {$seconds} segundos.",
            ]);
        }

        $user = \App\Models\User::where($field, $credentials['email'])->first();

        if (! $user || ! Auth::attempt([$field => $credentials['email'], 'password' => $credentials['password']], $request->boolean('remember'))) {
            RateLimiter::hit($key, $decaySeconds);

            $label = $user ? $user->fullName() : $credentials['email'];
            AuditLog::record('login_failed', $user, "Intento fallido para {$label}", $request->ip(), 'fail');

            return back()->withErrors([
                'email' => 'Las credenciales no coinciden con nuestros registros.',
            ])->onlyInput('email');
        }

        if (! $user->isActive()) {
            Auth::logout();
            AuditLog::record('login_blocked', $user, 'Acceso bloqueado: cuenta inactiva o suspendida', $request->ip(), 'denied');

            throw ValidationException::withMessages([
                'email' => 'Tu cuenta está suspendida o inactiva.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        AuditLog::record('login_success', $user, 'Inicio de sesión exitoso', $request->ip());

        return redirect()->intended(route('search.index'));
    }

    public function destroy(Request $request)
    {
        $user = $request->user();
        AuditLog::record('logout', $user, 'Cierre de sesión', $request->ip());

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}