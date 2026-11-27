<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'IPSearch Interya')</title>
    @include('partials.favicon')
    <script>
        // Aplicar el tema y el estado del menú guardados antes del primer pintado.
        (function () {
            var root = document.documentElement;
            root.setAttribute('data-bs-theme', localStorage.getItem('theme') === 'dark' ? 'dark' : 'light');

            if (localStorage.getItem('sidebar') === 'collapsed') {
                root.classList.add('sidebar-collapsed');
            }
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0071BC;
            --primary-dark: #083A56;
            --accent: #FCEE21;
            --bg: #F4F8FC;
            --sidebar: #083A56;
            --card: #FFFFFF;
            --text: #16232F;
            --muted: #4E6072;
            --border: #D9E3ED;
            --table-head: #EDF3F9;
            --hover: #EAF2FA;
        }
        [data-bs-theme="dark"] {
            --primary: #2E9BE0;
            --primary-dark: #083A56;
            --accent: #FCEE21;
            --bg: #0B1C2C;
            --sidebar: #072536;
            --card: #152A3F;
            --text: #EAF1F7;
            --muted: #A9BFD2;
            --border: #2E4A64;
            --table-head: #1B3348;
            --hover: #1C3A52;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        a { color: var(--primary); }
        a:hover { color: var(--primary-dark); }
        [data-bs-theme="dark"] a:hover { color: #7CC4F2; }

        .font-mono { font-family: 'JetBrains Mono', monospace; }

        /* ---------- Barra lateral (siempre oscura, texto de alto contraste) ---------- */
        .sidebar {
            background: var(--sidebar);
            min-height: 100vh;
            width: 240px;
            position: fixed;
            top: 0; left: 0;
            z-index: 100;
            display: flex; flex-direction: column;
            color: #D6E5F0;
            transition: transform .25s ease;
        }
        .sidebar .brand {
            padding: 1.25rem 1rem;
            color: var(--accent);
            font-weight: 800;
            font-size: 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,.12);
            display: flex; align-items: center; gap: .6rem;
        }
        .sidebar nav { flex: 1; overflow-y: auto; padding: 1rem 0; }
        .sidebar a {
            display: flex; align-items: center; gap: .75rem;
            padding: .65rem 1.25rem;
            color: #C9DCE9;
            text-decoration: none;
            font-size: .9rem;
            transition: background .2s, color .2s;
        }
        .sidebar a:hover { background: rgba(255,255,255,.10); color: #FFFFFF; }
        .sidebar a.active {
            background: rgba(0,113,188,.35);
            color: #FFFFFF;
            border-left: 4px solid var(--accent);
            font-weight: 600;
        }
        .sidebar a i { width: 1.25rem; text-align: center; }
        .sidebar .divider { height: 1px; background: rgba(255,255,255,.14); margin: .75rem 1.25rem; border: none; }
        .sidebar .logout-link { color: #FFB3B3; }
        .sidebar .logout-link:hover { color: #FFFFFF; background: rgba(220,53,69,.35); }

        .main { margin-left: 240px; padding: 1.5rem 2rem 6rem; transition: margin-left .25s ease; }

        /* ---------- Superficies ---------- */
        .navbar {
            background: var(--card) !important;
            border-bottom: 1px solid var(--border) !important;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: .85rem;
            box-shadow: 0 2px 8px rgba(8,58,86,.06);
            color: var(--text);
        }
        .card-header, .card-footer {
            background: var(--card);
            border-color: var(--border);
            color: var(--text);
        }
        .card-header { border-bottom: 1px solid var(--border); }
        .dropdown-menu {
            background: var(--card);
            border-color: var(--border);
            box-shadow: 0 .5rem 1.5rem rgba(8,58,86,.18);
        }
        .dropdown-item { color: var(--text); }
        .dropdown-item:hover, .dropdown-item:focus { background: var(--hover); color: var(--primary); }
        .dropdown-divider { border-color: var(--border); }

        /* ---------- Tablas ---------- */
        .table {
            --bs-table-bg: transparent;
            --bs-table-color: var(--text);
            --bs-table-border-color: var(--border);
            --bs-table-hover-bg: var(--hover);
            --bs-table-hover-color: var(--text);
            color: var(--text);
            margin-bottom: 0;
        }
        .table > thead th {
            background: var(--table-head);
            color: var(--text);
            font-weight: 600;
            font-size: .8rem;
            text-transform: none;
            border-bottom: 1px solid var(--border);
        }
        .table > tbody > tr > td { color: var(--text); }

        /* ---------- Formularios ---------- */
        .form-label { color: var(--text); font-weight: 600; }
        .form-control, .form-select {
            background-color: var(--card);
            color: var(--text);
            border-color: var(--border);
        }
        .form-control::placeholder { color: var(--muted); opacity: .85; }
        .form-control:focus, .form-select:focus {
            background-color: var(--card);
            color: var(--text);
            border-color: var(--primary);
            box-shadow: 0 0 0 .2rem rgba(0,113,188,.22);
        }
        .form-control:disabled, .form-select:disabled {
            background-color: var(--table-head);
            color: var(--muted);
        }
        .form-text { color: var(--muted) !important; }
        .form-check-label { color: var(--text); }
        .input-group-text {
            background: var(--table-head);
            color: var(--text);
            border-color: var(--border);
        }

        /* ---------- Botones y utilidades ---------- */
        .btn-primary { background: var(--primary); border-color: var(--primary); font-weight: 500; color: #fff; }
        .btn-primary:hover, .btn-primary:focus { background: var(--primary-dark); border-color: var(--primary-dark); color: #fff; }
        .btn-outline-primary { color: var(--primary); border-color: var(--primary); }
        .btn-outline-primary:hover { background: var(--primary); border-color: var(--primary); color: #fff; }
        .btn-link { color: var(--primary); }
        .badge { font-weight: 600; }
        .text-muted, .muted { color: var(--muted) !important; }
        .font-monospace-small { font-family: 'JetBrains Mono', monospace; font-size: .875rem; }
        .theme-switch { margin-left: auto; }
        .pagination {
            --bs-pagination-bg: var(--card);
            --bs-pagination-color: var(--primary);
            --bs-pagination-border-color: var(--border);
            --bs-pagination-hover-bg: var(--hover);
            --bs-pagination-hover-color: var(--primary);
            --bs-pagination-hover-border-color: var(--border);
            --bs-pagination-active-bg: var(--primary);
            --bs-pagination-active-border-color: var(--primary);
            --bs-pagination-disabled-bg: var(--card);
            --bs-pagination-disabled-color: var(--muted);
            --bs-pagination-disabled-border-color: var(--border);
        }
        .alert { color: var(--text); }

        /* Pie de página: fijo al fondo del viewport, nunca más alto de 75px */
        .app-footer {
            position: fixed;
            bottom: 0;
            left: 240px;
            right: 0;
            max-height: 75px;
            overflow: hidden;
            padding: .6rem 2rem;
            background: var(--card);
            border-top: 1px solid var(--border);
            color: var(--muted);
            font-size: .75rem;
            line-height: 1.35;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            z-index: 50;
            transition: left .25s ease;
        }

        /* Escritorio: el botón de menú pliega/despliega la barra lateral. */
        @media (min-width: 768px) {
            html.sidebar-collapsed .sidebar { transform: translateX(-100%); }
            html.sidebar-collapsed .main { margin-left: 0; }
            html.sidebar-collapsed .app-footer { left: 0; }
        }

        @media (max-width: 767.98px) {
            .sidebar { position: absolute; transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main { margin-left: 0; }
            .app-footer { left: 0; padding-inline: 1rem; }
            .mobile-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 90; display: none; }
            .mobile-overlay.show { display: block; }
        }

        /* En pantallas muy estrechas el texto se reduce para no pasar de 75px. */
        @media (max-width: 575.98px) {
            .app-footer { font-size: .7rem; padding: .45rem .75rem; }
        }
    </style>
</head>
<body>
    <div class="mobile-overlay" id="mobileOverlay" onclick="toggleSidebar()"></div>

    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <i class="bi bi-activity fs-4"></i> IPSearch Interya
        </div>
        <nav>
            <a href="{{ route('search.index') }}" class="{{ request()->routeIs('search.index') ? 'active' : '' }}"><i class="bi bi-search"></i> Buscador MAC</a>
            <a href="{{ route('search.history') }}" class="{{ request()->routeIs('search.history', 'search.detail') ? 'active' : '' }}"><i class="bi bi-clock-history"></i> Historial</a>
            <a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.*') ? 'active' : '' }}"><i class="bi bi-person-circle"></i> Perfil</a>

            @can('admin')
            <div class="divider"></div>
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><i class="bi bi-grid"></i> Panel Admin</a>
            <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*', 'invite.*') ? 'active' : '' }}"><i class="bi bi-people"></i> Usuarios</a>
            <a href="{{ route('admin.routers.index') }}" class="{{ request()->routeIs('admin.routers.*') ? 'active' : '' }}"><i class="bi bi-router"></i> Routers</a>
            <a href="{{ route('admin.audit') }}" class="{{ request()->routeIs('admin.audit*') ? 'active' : '' }}"><i class="bi bi-journal-text"></i> Auditoría</a>
            <a href="{{ route('admin.health') }}" class="{{ request()->routeIs('admin.health') ? 'active' : '' }}"><i class="bi bi-heart-pulse"></i> Salud</a>
            <a href="{{ route('admin.config') }}" class="{{ request()->routeIs('admin.config') ? 'active' : '' }}"><i class="bi bi-gear"></i> Configuración</a>
            @endcan
        </nav>
        <div class="p-3 border-top" style="border-color: rgba(255,255,255,.12) !important;">
            <form action="{{ route('logout') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="btn btn-link w-100 text-start d-flex align-items-center gap-2 p-0 logout-link">
                    <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                </button>
            </form>
        </div>
    </aside>

    <main class="main">
        <nav class="navbar navbar-expand-lg mb-4 rounded-3 px-2">
            <div class="container-fluid">
                <button class="btn btn-sm btn-outline-secondary border-0" onclick="toggleSidebar()"
                        aria-label="Mostrar u ocultar el menú" title="Mostrar u ocultar el menú">
                    <i class="bi bi-chevron-bar-left fs-5" id="menuToggleIcon"></i>
                </button>
                <div class="d-flex align-items-center ms-3 gap-2">
                    <span class="badge bg-primary text-white">{{ ucfirst(auth()->user()->role) }}</span>
                    <button type="button" id="themeToggle" class="btn btn-sm btn-outline-secondary" aria-label="Cambiar tema" title="Cambiar entre tema claro y oscuro">
                        <i class="bi bi-moon-stars"></i>
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-sm rounded-circle bg-body border shadow-sm d-flex align-items-center justify-content-center p-0" data-bs-toggle="dropdown" aria-label="Menú de usuario">
                            <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->fullName()) }}&background=0071bc&color=fff&size=40&font-size=20" width="32" height="32" class="rounded-circle">
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person"></i> Perfil</a></li>
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}#password"><i class="bi bi-key"></i> Cambiar contraseña</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('logout') }}" method="POST" class="m-0">
                                    @csrf
                                    <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show">
                <ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-warning alert-dismissible fade show">{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')

        @include('partials.footer')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobileOverlay');
        const isMobile = () => window.matchMedia('(max-width: 767.98px)').matches;

        function toggleSidebar() {
            if (isMobile()) {
                const open = sidebar.classList.toggle('open');
                overlay.classList.toggle('show', open);

                return;
            }

            const collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebar', collapsed ? 'collapsed' : 'expanded');

            const icon = document.getElementById('menuToggleIcon');
            if (icon) {
                icon.className = collapsed ? 'bi bi-list fs-5' : 'bi bi-chevron-bar-left fs-5';
            }
        }

        // Estado inicial del icono del menú (por si se restauró colapsado).
        if (document.documentElement.classList.contains('sidebar-collapsed')) {
            const menuIcon = document.getElementById('menuToggleIcon');
            if (menuIcon) {
                menuIcon.className = 'bi bi-list fs-5';
            }
        }

        // Tema: claro por defecto; la elección del usuario se recuerda.
        (function () {            const root = document.documentElement;
            const btn = document.getElementById('themeToggle');
            const icon = btn.querySelector('i');

            function paint(theme) {
                root.setAttribute('data-bs-theme', theme);
                icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
            }

            paint(root.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light');

            btn.addEventListener('click', function () {
                const next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
                localStorage.setItem('theme', next);
                paint(next);
            });
        })();

        // POST en JSON reutilizable para las acciones del panel.
        window.postJson = async function (url) {
            const meta = document.querySelector('meta[name="csrf-token"]');
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': meta ? meta.content : '' },
            });
            let data = {};
            try { data = await res.json(); } catch (e) { data = {}; }
            return { ok: res.ok, status: res.status, data: data };
        };
    </script>
    @stack('scripts')
</body>
</html>
