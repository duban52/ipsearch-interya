@php
    // Selector de tema compartido por las pantallas de autenticación.
@endphp
<button id="themeToggle" class="btn btn-sm" aria-label="Cambiar tema" title="Cambiar entre tema claro y oscuro"
        style="position: fixed; top: 1rem; right: 1rem; z-index: 10; background: var(--card); color: var(--text); border: 1px solid var(--border);">
    <i class="bi bi-moon-stars"></i>
</button>
<script>
    (function () {
        var root = document.documentElement;
        var btn = document.getElementById('themeToggle');
        var icon = btn.querySelector('i');
        var saved = localStorage.getItem('theme');
        var initial = saved === 'dark' ? 'dark' : 'light';

        function paint(theme) {
            root.setAttribute('data-bs-theme', theme);
            icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
        }

        paint(initial);

        btn.addEventListener('click', function () {
            var next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            localStorage.setItem('theme', next);
            paint(next);
        });
    })();
</script>
