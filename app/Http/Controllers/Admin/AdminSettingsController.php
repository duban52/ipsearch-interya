<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MailSetting;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class AdminSettingsController extends Controller
{
    /**
     * Registro de auditoría paginado con filtros básicos.
     */
    public function audit(Request $request)
    {
        $logs = AuditLog::query()
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->input('action')))
            ->when($request->filled('result'), fn ($q) => $q->where('result', $request->input('result')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('hasta')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');

        return view('admin.auditoria.index', compact('logs', 'actions'));
    }

    public function auditDetail(int $id)
    {
        $log = AuditLog::findOrFail($id);

        return view('admin.audit.detail', compact('log'));
    }

    /**
     * Configuración general del sistema.
     */
    public function index()
    {
        $settings = SystemSetting::query()->pluck('value', 'key');
        $mail = MailSetting::query()->first();

        return view('admin.config.index', compact('settings', 'mail'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:100'],
            'login_max_attempts' => ['required', 'integer', 'min:3', 'max:20'],
            'login_decay_seconds' => ['required', 'integer', 'min:60', 'max:3600'],
            'device_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'device_scheme' => ['required', 'in:http,https'],
        ], [
            'device_scheme.in' => 'El esquema debe ser HTTP o HTTPS.',
        ]);

        SystemSetting::set('app_name', $data['app_name'], 'string');
        SystemSetting::set('login_max_attempts', $data['login_max_attempts'], 'int');
        SystemSetting::set('login_decay_seconds', $data['login_decay_seconds'], 'int');
        SystemSetting::set('device_port', $data['device_port'], 'int');
        SystemSetting::set('device_scheme', $data['device_scheme'], 'string');

        AuditLog::record('settings_updated', $request->user(), 'Configuración del sistema actualizada', $request->ip());

        return back()->with('success', 'Configuración guardada correctamente.');
    }

    /**
     * Configuración SMTP. La contraseña se guarda cifrada y nunca se muestra
     * de vuelta en el formulario.
     */
    public function updateSmtp(Request $request)
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:150'],
            'password' => ['nullable', 'string', 'max:255'],
            'encryption' => ['required', 'in:tls,ssl,none'],
            'from_address' => ['required', 'email', 'max:150'],
            'from_name' => ['required', 'string', 'max:150'],
        ]);

        $mail = MailSetting::query()->firstOrNew([]);
        $mail->fill($data);

        if (! empty($data['password'])) {
            $mail->password = Crypt::encryptString($data['password']);
        }

        $mail->is_active = true;
        $mail->save();

        AuditLog::record('smtp_updated', $request->user(), "Configuración SMTP actualizada ({$mail->host}:{$mail->port})", $request->ip());

        return back()->with('success', 'Configuración SMTP guardada correctamente.');
    }

    /**
     * Envía un correo de prueba a la dirección del administrador para validar
     * la configuración sin depender de una invitación real.
     */
    public function testSmtp(Request $request)
    {
        $to = $request->user()->email;

        if (config('mail.default') === 'log') {
            return response()->json([
                'success' => false,
                'message' => 'El correo está en modo registro: no hay SMTP activo, los mensajes se escriben en storage/logs/laravel.log.',
            ]);
        }

        try {
            \Illuminate\Support\Facades\Mail::raw(
                "Correo de prueba de IPSearch Interya.\n\n"
                .'Enviado: '.now()->format('d/m/Y H:i:s')."\n"
                .'Transporte: '.config('mail.default')."\n"
                .'Servidor: '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port'),
                function ($message) use ($to) {
                    $message->to($to)->subject('Prueba de correo — IPSearch Interya');
                }
            );

            AuditLog::record('smtp_test', $request->user(), "Correo de prueba enviado a {$to}", $request->ip());

            return response()->json([
                'success' => true,
                'message' => "Correo de prueba enviado a {$to}. Revisa la bandeja (y el spam).",
            ]);
        } catch (\Throwable $e) {
            AuditLog::record('smtp_test_fail', $request->user(), "Fallo al enviar correo de prueba: {$e->getMessage()}", $request->ip(), 'fail');

            return response()->json([
                'success' => false,
                'message' => 'No se pudo enviar el correo de prueba.',
                'detail' => $e->getMessage(),
            ]);
        }
    }
}
