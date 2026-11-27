<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\AuditLog;

class UserController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'lastname' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email,'.$user->id],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username,'.$user->id],
        ], [
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'username.unique' => 'Ese nombre de usuario ya está en uso.',
            'username.alpha_dash' => 'El usuario solo puede contener letras, números, guiones y guiones bajos.',
        ]);

        $changed = $user->isDirty('email', 'username', 'name', 'lastname');

        $user->fill($data);

        // Email change invalidates verification until re-verified
        if ($changed && $user->isDirty('email')) {
            $user->forceFill(['email_verified_at' => null])->save();
            AuditLog::record('email_changed', $user, "Correo cambiado a {$data['email']}", $request->ip());
            AuditLog::record('email_verification_required', $user, 'Se requiere nueva verificación de correo tras el cambio.', $request->ip());
        } elseif ($changed) {
            $user->save();
            AuditLog::record('profile_updated', $user, 'Perfil actualizado', $request->ip());
        }

        return back()->with('success', 'Perfil actualizado correctamente.');
    }
}