<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class UserAdminController extends Controller
{
    public function index()
    {
        $users = User::orderBy('role', 'asc')->orderBy('name')->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'lastname' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->mixedCase()->numbers()->symbols()],
            'role' => ['required', 'in:superadmin,user'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'username.unique' => 'Ese nombre de usuario ya está en uso.',
            'username.alpha_dash' => 'El usuario solo puede contener letras, números, guiones y guiones bajos.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'password.min' => 'La contraseña debe tener al menos 10 caracteres con mayúscula, minúscula, número y símbolo.',
        ]);

        $user = User::create(array_merge($data, [
            'is_active' => $request->boolean('is_active'),
            'password' => bcrypt($data['password']),
        ]));

        AuditLog::record('user_created', $request->user(), "Usuario creado: {$user->fullName()} ({$user->email})", $request->ip());

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'lastname' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email,'.$user->id],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username,'.$user->id],
            'password' => ['nullable', 'confirmed', PasswordRule::min(10)->letters()->mixedCase()->numbers()->symbols()],
            'role' => ['required', 'in:superadmin,user'],
        ], [
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'username.unique' => 'Ese nombre de usuario ya está en uso.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
        ]);

        // La contraseña es opcional al editar: si viene vacía se conserva la actual,
        // y si viene se guarda cifrada (nunca en texto plano).
        $passwordChanged = filled($data['password'] ?? null);

        if ($passwordChanged) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }

        $user->fill($data);

        $emailChanged = $user->isDirty('email');

        // Email change invalidates verification
        if ($emailChanged) {
            $user->forceFill(['email_verified_at' => null])->save();
            AuditLog::record('email_changed', $request->user(), "Correo cambiado a {$data['email']}", $request->ip());
            AuditLog::record('email_verification_required', $user, 'Se requiere nueva verificación de correo tras el cambio.', $request->ip());
        } else {
            $user->save();
            AuditLog::record('profile_updated', $request->user(), 'Perfil actualizado', $request->ip());
        }

        if ($passwordChanged) {
            AuditLog::record('password_changed_by_admin', $request->user(), "Contraseña restablecida para {$user->fullName()}", $request->ip());
        }

        return back()->with('success', 'Usuario actualizado.');
    }

    public function suspend(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'No puedes suspender tu propia cuenta.');
        }

        // No permitir suspender al último superadministrador activo
        $otherActiveAdmins = User::where('role', 'superadmin')
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->count();

        if ($user->isSuperAdmin() && $otherActiveAdmins === 0) {
            return back()->with('error', 'No puedes suspender al último superadministrador activo.');
        }

        $user->forceFill([
            'is_active' => false,
            'suspended_at' => now(),
            'suspended_reason' => $request->input('reason'),
        ])->save();

        AuditLog::record('user_suspended', $request->user(), "Usuario {$user->fullName()} suspendido", $request->ip());

        return back()->with('success', 'Usuario suspendido.');
    }

    public function reactivate(Request $request, User $user)
    {
        $user->forceFill(['is_active' => true, 'suspended_at' => null])->save();

        AuditLog::record('user_reactivated', $request->user(), "Usuario {$user->fullName()} reactivado", $request->ip());

        return back()->with('success', 'Usuario reactivado.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        // No permitir eliminar al último superadministrador activo
        $otherActiveAdmins = User::where('role', 'superadmin')
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->count();

        if ($user->isSuperAdmin() && $otherActiveAdmins === 0) {
            return back()->with('error', 'No puedes eliminar al último superadministrador activo.');
        }

        $name = $user->fullName();
        $user->delete();

        AuditLog::record('user_deleted', $request->user(), "Usuario {$name} eliminado", $request->ip());

        return back()->with('success', 'Usuario eliminado.');
    }

    public function resendInvitation(Request $request, User $user)
    {
        // Las cuentas invitadas están inactivas hasta que aceptan la invitación,
        // por eso solo se bloquea el reenvío cuando la cuenta está suspendida.
        if ($user->suspended_at !== null) {
            return back()->with('error', 'No se puede invitar a un usuario suspendido. Reactívalo primero.');
        }

        $invitation = Invitation::where('user_id', $user->id)->whereNull('accepted_at')->latest()->first();

        if ($invitation && ! $invitation->isExpired()) {
            return back()->with('error', 'Ya existe una invitación pendiente para este usuario.');
        }

        $issued = app(\App\Services\InvitationService::class)->issue($user, $request->user());

        AuditLog::record('invite_resent', $request->user(), "Invitación reenviada para {$user->fullName()} ({$user->email})", $request->ip(), 'success', null, [
            'enviada_por_correo' => $issued['mailed'],
        ]);

        return back()->with('success', $issued['mailed']
            ? 'Invitación reenviada por correo a '.$user->email.'.'
            : 'Invitación reenviada. El correo no pudo enviarse; enlace de activación: '.$issued['url']);
    }
}