<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function show(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('search.index');
        }

        return view('auth.verify-notice', ['email' => $request->user()->getEmailForVerification()]);
    }

    public function verify(Request $request, string $id, string $hash)
    {
        $user = $request->user();

        if (! hash_equals((string) $id, (string) $user->getKey())) {
            abort(403, 'El enlace de verificación no corresponde al usuario autenticado.');
        }

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            abort(403, 'El enlace de verificación no es válido.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            AuditLog::record('email_verified', $user, 'Correo electrónico verificado', $request->ip());
        }

        return redirect()->route('search.index')->with('success', 'Correo verificado correctamente.');
    }

    public function store(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('search.index');
        }

        $request->user()->sendEmailVerificationNotification();

        AuditLog::record('verification_resent', $request->user(), 'Verificación de correo reenviada', $request->ip());

        return back()->with('success', 'Se ha reenviado el enlace de verificación.');
    }
}
