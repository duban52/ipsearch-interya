<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPSearch Interya - Recuperar contraseña</title>
    @include('partials.favicon')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0071BC;
            --primary-dark: #083A56;
            --accent: #FCEE21;
            --bg: #F4F8FC;
            --card: #FFFFFF;
            --text: #1A2332;
            --muted: #4E6072;
            --border: #E2E8F0;
        }
        [data-bs-theme="dark"] {
            --primary: #0071BC; --primary-dark: #083A56; --accent: #FCEE21;
            --bg: #0B1C2C; --card: #152A3F; --text: #E6EDF3; --muted: #A9BFD2; --border: #2E4A64;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--primary-dark) 0%, #0a4d6e 40%, var(--primary) 100%);
            color: var(--text);
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center; padding: 1rem;
        }
        .auth-card {
            background: var(--card); border-radius: 1rem; box-shadow: 0 20px 60px rgba(8,58,86,.35);
            width: 100%; max-width: 420px; padding: 2rem; border: 1px solid var(--border);
        }
        .brand-mark {
            width: 48px; height: 48px; border-radius: 12px; background: var(--accent); color: var(--primary-dark);
            display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.25rem; margin-bottom: 1.25rem;
        }
        .form-control { border-color: var(--border); background: var(--card); color: var(--text); padding: .65rem .85rem; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 .2rem rgba(0,113,188,.18); background: var(--card); color: var(--text); }
        .btn-primary { background: var(--primary); border-color: var(--primary); font-weight: 600; }
        .btn-primary:hover { background: var(--primary-dark); border-color: var(--primary-dark); }
        .muted { color: var(--muted); }
        .form-label { font-weight: 500; color: var(--text); font-size: .875rem; }
        .alert { border-radius: .75rem; font-size: .875rem; }
        a { color: var(--primary); }
        .input-group-text { background: var(--card); border-color: var(--border); color: var(--muted); }
    </style>
</head>
<body>
<div class="auth-card">
    <div class="brand-mark">IS</div>
    <h1 class="h4 fw-bold mb-1">Recuperar contraseña</h1>
    <p class="muted mb-4">Te enviaremos un enlace temporal para restablecer tu contraseña.</p>

    @if ($errors->any())
        <div class="alert alert-danger py-2"><ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    @if (session('status'))
        <div class="alert alert-success py-2">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="email">Correo electrónico</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
            </div>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-send me-2"></i>Enviar enlace</button>
    </form>

    <div class="mt-4 text-center small">
        <a href="{{ route('login') }}"><i class="bi bi-arrow-left me-1"></i>Volver al inicio de sesión</a>
    </div>
</div>
@include('partials.theme')
</body>
</html>