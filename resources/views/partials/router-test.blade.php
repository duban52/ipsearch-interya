@push('scripts')
<script>
    (function () {
        const box = document.getElementById('routerTestResult');
        if (!box) return;

        function escapeHtml(s) {
            if (s == null) return '';
            return String(s).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
        }

        document.querySelectorAll('form.js-router-test').forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                event.preventDefault();

                const button = form.querySelector('button[type="submit"]');
                const name = form.dataset.name || 'el router';

                button.disabled = true;
                button.textContent = 'Probando…';
                box.innerHTML = '';

                try {
                    const { data } = await window.postJson(form.action);

                    if (data.success) {
                        const info = data.info || {};
                        box.innerHTML = `
                            <div class="alert alert-success mb-0">
                                <div class="fw-semibold"><i class="bi bi-check-circle me-1"></i>Conexión correcta con ${escapeHtml(name)}</div>
                                <div class="small mt-1">
                                    RouterOS ${escapeHtml(info.version ?? '—')} · ${escapeHtml(info.board_name ?? '—')}
                                    · ${escapeHtml(String(info.latency_ms ?? '—'))} ms
                                    · <span class="font-monospace">${escapeHtml(info.endpoint ?? '')}</span>
                                </div>
                            </div>`;
                    } else {
                        box.innerHTML = `
                            <div class="alert alert-danger mb-0">
                                <div class="fw-semibold"><i class="bi bi-exclamation-triangle me-1"></i>No se pudo conectar con ${escapeHtml(name)}: ${escapeHtml(data.message ?? 'error desconocido')}</div>
                                ${data.hint ? `<div class="small mt-1">${escapeHtml(data.hint)}</div>` : ''}
                                ${data.detail ? `<div class="small font-monospace mt-1">${escapeHtml(data.detail)}</div>` : ''}
                            </div>`;
                    }
                } catch (error) {
                    box.innerHTML = '<div class="alert alert-danger mb-0">Error de red al probar el router.</div>';
                } finally {
                    button.disabled = false;
                    button.textContent = 'Probar';
                }
            });
        });
    })();
</script>
@endpush
