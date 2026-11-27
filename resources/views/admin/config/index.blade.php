@extends('layouts.app')

@section('title', 'Configuración')

@section('content')
@if (config('mail.default') === 'log')
    <div class="alert alert-warning">
        <div class="fw-semibold"><i class="bi bi-envelope-exclamation me-1"></i>El correo está en modo registro</div>
        <div class="small mt-1">
            No hay SMTP configurado: los mensajes <strong>no se envían</strong>, se escriben en
            <code>storage/logs/laravel.log</code>. Configura el SMTP de abajo para que las invitaciones
            y la recuperación de contraseña lleguen por correo.
        </div>
    </div>
@else
    <div class="alert alert-success py-2">
        <i class="bi bi-check-circle me-1"></i>
        Correo activo mediante <strong>{{ config('mail.default') }}</strong>:
        <span class="font-monospace">{{ config('mail.mailers.smtp.host') }}:{{ config('mail.mailers.smtp.port') }}</span>
    </div>
@endif

<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card shadow-sm">
            <div class="card-header">
                <h2 class="h5 mb-0">Configuración del sistema</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.config.update') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre del sistema</label>
                        <input type="text" name="app_name" class="form-control" value="{{ old('app_name', $settings['app_name'] ?? 'IPSearch Interya') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Máximo intentos de login</label>
                        <input type="number" name="login_max_attempts" class="form-control" min="3" max="20" value="{{ old('login_max_attempts', $settings['login_max_attempts'] ?? 5) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tiempo de bloqueo (segundos)</label>
                        <input type="number" name="login_decay_seconds" class="form-control" min="60" max="3600" value="{{ old('login_decay_seconds', $settings['login_decay_seconds'] ?? 300) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Puerto para abrir dispositivo</label>
                        <input type="number" name="device_port" class="form-control" min="1" max="65535" value="{{ old('device_port', $settings['device_port'] ?? 80) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Esquema para dispositivo</label>
                        <select name="device_scheme" class="form-select">
                            <option value="http" {{ old('device_scheme', $settings['device_scheme'] ?? 'http') === 'http' ? 'selected' : '' }}>HTTP</option>
                            <option value="https" {{ old('device_scheme', $settings['device_scheme'] ?? 'http') === 'https' ? 'selected' : '' }}>HTTPS</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar configuración</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mx-auto">
        <div class="card shadow-sm">
            <div class="card-header">
                <h2 class="h5 mb-0">Configuración SMTP</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.smtp.update') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Host SMTP</label>
                        <input type="text" name="host" class="form-control" value="{{ old('host', $mail->host ?? 'smtp.interya.com') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Puerto</label>
                        <input type="number" name="port" class="form-control" value="{{ old('port', $mail->port ?? 587) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Usuario</label>
                        <input type="text" name="username" class="form-control" value="{{ old('username', $mail->username ?? '') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Contraseña</label>
                        <input type="password" name="password" class="form-control" autocomplete="new-password" placeholder="{{ $mail && $mail->password ? 'Dejar vacío para mantener la actual' : '' }}">
                        <div class="form-text">Se almacena cifrada. Nunca se muestra en pantalla.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Encriptación</label>
                        <select name="encryption" class="form-select">
                            @foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'Ninguna'] as $value => $label)
                                <option value="{{ $value }}" {{ old('encryption', $mail->encryption ?? 'tls') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Remitente</label>
                        <input type="text" name="from_address" class="form-control" value="{{ old('from_address', $mail->from_address ?? 'no-reply@interya.com') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre remitente</label>
                        <input type="text" name="from_name" class="form-control" value="{{ old('from_name', $mail->from_name ?? 'IPSearch Interya') }}" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar SMTP</button>
                    <button type="button" class="btn btn-outline-primary" id="smtpTestBtn">Enviar correo de prueba</button>
                    <div id="smtpTestResult" class="mt-3"></div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const button = document.getElementById('smtpTestBtn');
        const box = document.getElementById('smtpTestResult');
        if (!button || !box) return;

        button.addEventListener('click', async function () {
            button.disabled = true;
            box.innerHTML = '';
            const original = button.textContent;
            button.textContent = 'Enviando…';

            try {
                const { data } = await window.postJson("{{ route('admin.smtp.test') }}");
                const cls = data.success ? 'alert-success' : 'alert-warning';
                box.innerHTML = `<div class="alert ${cls} mb-0 py-2 small">
                    ${data.success ? '<i class="bi bi-check-circle me-1"></i>' : '<i class="bi bi-exclamation-triangle me-1"></i>'}
                    ${escapeHtml(data.message || 'Sin respuesta')}
                    ${data.detail ? `<div class="font-monospace mt-1">${escapeHtml(data.detail)}</div>` : ''}
                </div>`;
            } catch (e) {
                box.innerHTML = '<div class="alert alert-danger mb-0 py-2 small">Error de red al enviar la prueba.</div>';
            } finally {
                button.disabled = false;
                button.textContent = original;
            }
        });

        function escapeHtml(s) {
            if (s == null) return '';
            return String(s).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
        }
    })();
</script>
@endpush
