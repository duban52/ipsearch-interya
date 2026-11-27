<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPSearch Interya - Restablecer contraseña</title>
    @include('partials.favicon')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary:#0071BC; --primary-dark:#083A56; --accent:#FCEE21; --bg:#F4F8FC; --card:#fff; --text:#1A2332; --muted: #4E6072; --border:#E2E8F0; }
        [data-bs-theme="dark"] { --primary:#0071BC; --primary-dark:#083A56; --accent:#FCEE21; --bg:#0B1C2C; --card: #152A3F; --text:#E6EDF3; --muted: #A9BFD2; --border: #2E4A64; }
        body { font-family:'Inter',sans-serif; background:linear-gradient(135deg,var(--primary-dark),#0a4d6e 40%,var(--primary)); color:var(--text); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:1rem; }
        .auth-card { background:var(--card); border-radius:1rem; box-shadow:0 20px 60px rgba(8,58,86,.35); width:100%; max-width:420px; padding:2rem; border:1px solid var(--border); }
        .brand-mark { width:48px; height:48px; border-radius:12px; background:var(--accent); color:var(--primary-dark); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1.25rem; margin-bottom:1.25rem; }
        .form-control { border-color:var(--border); background:var(--card); color:var(--text); padding:.65rem .85rem; }
        .form-control:focus { border-color:var(--primary); box-shadow:0 0 0 .2rem rgba(0,113,188,.18); background:var(--card); color:var(--text); }
        .btn-primary { background:var(--primary); border-color:var(--primary); font-weight:600; }
        .btn-primary:hover { background:var(--primary-dark); border-color:var(--primary-dark); }
        .muted { color:var(--muted); }
        .form-label { font-weight:500; color:var(--text); font-size:.875rem; }
        .alert { border-radius:.75rem; font-size:.875rem; }
        a { color:var(--primary); }
        .input-group-text { background:var(--card); border-color:var(--border); color:var(--muted); }
        .hint { font-size:.75rem; color:var(--muted); margin-top:.25rem; }
    </style>
</head>
<body>
<div class="auth-card">
    <div class="brand-mark">IS</div>
    <h1 class="h4 fw-bold mb-1">Nueva contraseña</h1>
    <p class="muted mb-4">Ingresa una contraseña robusta.</p>

    @if ($errors->any())
        <div class="alert alert-danger py-2"><ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ old('email', $email) }}">

        <div class="mb-3">
            <label class="form-label" for="password">Nueva contraseña</label>
            <input type="password" id="password" name="password" class="form-control" required autofocus autocomplete="new-password">
            <div class="hint">Mínimo 10 caracteres: mayúscula, minúscula, número y símbolo.</div>
        </div>
        <div class="mb-4">
            <label class="form-label" for="password_confirmation">Confirmar contraseña</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-check2-circle me-2"></i>Restablecer contraseña</button>
    </form>

    <div class="mt-4 text-center small"><a href="{{ route('login') }}">Volver al inicio</a></div>
</div>
@include('partials.theme')
</body>
</html>