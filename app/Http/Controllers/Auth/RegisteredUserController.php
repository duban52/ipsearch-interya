<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegisteredUserController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    /**
     * El registro libre está deshabilitado: las cuentas se crean por invitación
     * desde la administración.
     */
    public function create()
    {
        return redirect()->route('login')
            ->withErrors(['email' => 'El registro está deshabilitado. Solicita una invitación al administrador.']);
    }

    public function store(Request $request)
    {
        return $this->create();
    }

    /**
     * Alta de usuario + invitación (solo superadministrador).
     */
    public function createInvite()
    {
        return view('admin.users.invite');
    }

    public function storeInvite(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'lastname' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'role' => ['required', 'in:superadmin,user'],
        ], [
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'username.unique' => 'Ese nombre de usuario ya está en uso.',
            'username.alpha_dash' => 'El usuario solo puede contener letras, números, guiones y guiones bajos.',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'lastname' => $data['lastname'] ?? null,
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make(Str::random(64)),
            'role' => $data['role'],
            'is_active' => false,
        ]);

        $issued = $this->invitations->issue($user, $request->user());

        AuditLog::record('user_invited', $request->user(), "Invitación creada para {$user->fullName()} ({$user->email})", $request->ip(), 'success', null, [
            'enviada_por_correo' => $issued['mailed'],
        ]);

        $aviso = $issued['mailed']
            ? 'Invitación enviada por correo a '.$user->email.'.'
            : 'Invitación generada. El correo no pudo enviarse; entrega el enlace manualmente:';

        return redirect()->route('admin.users.edit', $user)
            ->with('success', trim($aviso.' '.$issued['url']));
    }

    public function acceptInvite(Request $request, string $token)
    {
        $invitation = $this->findValidInvitation($token);

        return view('auth.invite-accept', compact('invitation', 'token'));
    }

    public function acceptInviteStore(Request $request, string $token)
    {
        // Permitir la ruta tanto en texto claro como ya hasheada.
        $invitation = $this->findValidInvitation($token);

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()->symbols()],
        ], [
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'password.min' => 'La contraseña debe tener al menos 10 caracteres.',
        ]);

        $user = $invitation->user;

        if (! $user) {
            abort(410, 'La invitación no es válida o ha expirado.');
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'is_active' => true,
            'suspended_at' => null,
            'suspended_reason' => null,
            'email_verified_at' => now(),
        ])->save();

        $invitation->forceFill(['accepted_at' => now()])->save();

        AuditLog::record('invite_accepted', $user, 'Invitación aceptada y contraseña establecida', $request->ip());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('search.index')
            ->with('success', 'Tu cuenta está activa. Bienvenido a IPSearch Interya.');
    }

    public function resendInvite(Request $request, string $token)
    {
        $invitation = Invitation::where('token', $this->hashToken($token))->firstOrFail();

        if ($invitation->isAccepted()) {
            return back()->withErrors(['invite' => 'Esta invitación ya fue aceptada.']);
        }

        $issued = $this->invitations->issue($invitation->user, $request->user());

        AuditLog::record('invite_resent', $request->user(), "Invitación reenviada para {$invitation->email}", $request->ip());

        return back()->with('success', 'Invitación reenviada. Enlace: '.$issued['url']);
    }

    private function findValidInvitation(string $rawOrHashedToken): Invitation
    {
        $invitation = Invitation::with('user')
            ->where('token', $this->hashToken($rawOrHashedToken))
            ->first();

        if (! $invitation || $invitation->isExpired() || $invitation->isAccepted()) {
            abort(410, 'La invitación no es válida o ha expirado.');
        }

        return $invitation;
    }

    private function hashToken(string $token): string
    {
        return ctype_xdigit($token) && strlen($token) === 64
            ? strtolower($token)
            : hash('sha256', $token);
    }
}
