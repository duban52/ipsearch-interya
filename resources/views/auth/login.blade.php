<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        (function () {
            var saved = localStorage.getItem('theme');
            document.documentElement.setAttribute('data-bs-theme', saved === 'dark' ? 'dark' : 'light');
        })();
    </script>
    <title>IPSearch Interya - Inicio de sesión</title>
    @include('partials.favicon')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
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
            --primary: #0071BC;
            --primary-dark: #083A56;
            --accent: #FCEE21;
            --bg: #0B1C2C;
            --card: #152A3F;
            --text: #E6EDF3;
            --muted: #A9BFD2;
            --border: #2E4A64;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--primary-dark) 0%, #0a4d6e 40%, var(--primary) 100%);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .auth-card {
            background: var(--card);
            border-radius: 1rem;
            box-shadow: 0 20px 60px rgba(8, 58, 86, .35);
            width: 100%;
            max-width: 420px;
            padding: 2rem;
            border: 1px solid var(--border);
        }
        .brand-mark { display: block; width: 56px; height: 56px; margin-bottom: 1.25rem; }
        .form-control, .form-select {
            border-color: var(--border);
            background: var(--card);
            color: var(--text);
            padding: .65rem .85rem;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 .2rem rgba(0,113,188,.18);
            background: var(--card);
            color: var(--text);
        }
        .btn-primary { background: var(--primary); border-color: var(--primary); font-weight: 600; }
        .btn-primary:hover { background: var(--primary-dark); border-color: var(--primary-dark); }
        .muted { color: var(--muted); }
        .form-label { font-weight: 500; color: var(--text); font-size: .875rem; }
        .theme-toggle { position: absolute; top: 1rem; right: 1rem; }
        .alert { border-radius: .75rem; font-size: .875rem; }
        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <button id="themeToggle" class="btn btn-sm theme-toggle" aria-label="Cambiar tema" style="background:var(--card);color:var(--text);border:1px solid var(--border)">
        <i class="bi bi-moon-stars-fill"></i>
    </button>

    <div class="auth-card">
        <img src="{{ asset('favicon.svg') }}" class="brand-mark" width="56" height="56" alt="IPSearch Interya">
        <h1 class="h4 fw-bold mb-1">IPSearch Interya</h1>
        <p class="muted mb-4">Sistema privado de resolución MAC &rarr; IP</p>

        @if ($errors->any())
            <div class="alert alert-danger py-2">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('status'))
            <div class="alert alert-success py-2">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('login.submit') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="email">Correo electrónico o usuario</label>
                <div class="input-group">
                    <span class="input-group-text" style="background:var(--card);border-color:var(--border);color:var(--muted)">
                        <i class="bi bi-person"></i>
                    </span>
                    <input type="text" id="email" name="email" class="form-control" value="{{ old('email') }}"
                           required autofocus autocomplete="username" placeholder="nombre@interya.com">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="password">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text" style="background:var(--card);border-color:var(--border);color:var(--muted)">
                        <i class="bi bi-lock"></i>
                    </span>
                    <input type="password" id="password" name="password" class="form-control"
                           required autocomplete="current-password" placeholder="••••••••">
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3 small">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                    <label class="form-check-label muted" for="remember">Recordarme</label>
                </div>
                <a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar sesión
            </button>
        </form>

        <div class="mt-4 pt-3 border-top small muted">
            Acceso restringido a personal autorizado de Interya.
        </div>
    </div>

    @include('partials.theme')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>