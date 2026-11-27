@extends('layouts.app')

@section('title', 'Buscador')

@section('content')
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 mb-1">Buscador de dispositivos</h1>
                <p class="text-muted mb-0 small">Ingresa la dirección MAC para identificar la IP asociada en nuestra infraestructura.</p>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body p-4">
                <form id="macForm" autocomplete="off">
                    @csrf
                    <label class="form-label fw-semibold" for="mac">Dirección MAC</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-light"><i class="bi bi-upc-scan"></i></span>
                        <input type="text" class="form-control font-monospace" id="mac" name="mac"
                               placeholder="AA:BB:CC:DD:EE:FF" maxlength="32" required autofocus
                               style="text-transform: uppercase;">
                        <button type="submit" class="btn btn-primary px-4" id="btnSearch">
                            <i class="bi bi-search me-2"></i>Buscar dispositivo
                        </button>
                    </div>
                    <div class="form-text">
                        Formatos aceptados: <code>AA:BB:CC:DD:EE:FF</code>, <code>AA-BB-CC-DD-EE-FF</code> o <code>AABBCCDDEEFF</code>
                    </div>
                </form>
            </div>
        </div>

        <div id="searchProgress" class="d-none mb-4">
            <div class="card shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div>
                        <div class="fw-semibold">Consultando routers&hellip;</div>
                        <div class="small text-muted">Se están consultando todos los routers activos de la infraestructura.</div>
                    </div>
                </div>
            </div>
        </div>

        <div id="resultsContainer" class="d-none"></div>

        @if ($recent->isNotEmpty())
            <div class="card shadow-sm mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Búsquedas recientes</span>
                    <a href="{{ route('search.history') }}" class="small">Ver historial completo</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle small">
                        <thead>
                            <tr>
                                <th>MAC</th>
                                <th>IP</th>
                                <th>Resultados</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recent as $s)
                                <tr>
                                    <td class="font-monospace">{{ $s->mac_normalized }}</td>
                                    <td class="font-monospace">{{ $s->ip_address ?? '—' }}</td>
                                    <td><span class="badge bg-secondary">{{ $s->result_count }}</span></td>
                                    <td>
                                        @switch($s->status)
                                            @case('completed')<span class="badge bg-success">Completa</span>@break
                                            @case('partial')<span class="badge bg-warning text-dark">Parcial</span>@break
                                            @case('no_results')<span class="badge bg-secondary">Sin resultados</span>@break
                                            @case('error')<span class="badge bg-danger">Error</span>@break
                                            @default<span class="badge bg-secondary">{{ $s->status }}</span>
                                        @endswitch
                                    </td>
                                    <td>{{ $s->created_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
const macInput = document.getElementById('mac');
const form = document.getElementById('macForm');
const btn = document.getElementById('btnSearch');
const progress = document.getElementById('searchProgress');
const container = document.getElementById('resultsContainer');

macInput.addEventListener('input', function () {
    this.value = this.value.toUpperCase();
});

form.addEventListener('submit', async function (e) {
    e.preventDefault();
    const mac = macInput.value.trim();
    if (!mac) return;

    btn.disabled = true;
    progress.classList.remove('d-none');
    container.classList.add('d-none');

    try {
        const res = await fetch("{{ route('search.perform') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
            },
            body: JSON.stringify({ mac }),
        });

        const data = await res.json();

        if (!res.ok) {
            const msg = data.message || data.errors?.mac?.[0] || 'No se pudo completar la búsqueda.';
            renderError(msg);
            return;
        }

        renderResults(data);
    } catch (err) {
        renderError('Error de red. Intenta nuevamente.');
    } finally {
        btn.disabled = false;
        progress.classList.add('d-none');
    }
});

function renderError(message) {
    container.innerHTML = `
        <div class="alert alert-warning d-flex gap-2 align-items-start">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div>${escapeHtml(message)}</div>
        </div>`;
    container.classList.remove('d-none');
}

const MECHANISM_LABEL = {
    pppoe_active: 'PPPoE activo',
    dhcp_active: 'DHCP activo',
    arp_complete: 'ARP',
    dhcp_static: 'DHCP estático',
    hotspot_binding: 'Hotspot binding',
};

function mechanismsOf(result) {
    const list = (result.mechanisms && result.mechanisms.length) ? result.mechanisms : [result.mechanism];
    return list.map(m => MECHANISM_LABEL[m] || m);
}

function activeBadge(result) {
    return result.is_active_match
        ? '<span class="badge bg-success">Activo</span>'
        : '<span class="badge bg-secondary">Histórico</span>';
}

function renderResults(data) {
    const statusLabel = {
        completed: 'Completa',
        partial: 'Parcial',
        no_results: 'Sin resultados',
        error: 'Error',
        no_routers: 'Sin routers activos',
    }[data.status] || data.status;

    const statusBadge = {
        completed: 'bg-success',
        partial: 'bg-warning text-dark',
        no_results: 'bg-secondary',
        error: 'bg-danger',
        no_routers: 'bg-secondary',
    }[data.status] || 'bg-secondary';

    if (!data.results || data.results.length === 0) {
        const msg = data.status === 'no_routers'
            ? 'No hay routers activos configurados. Contacta al administrador.'
            : `No encontramos una dirección IP asociada a la MAC <strong class="font-monospace">${escapeHtml(data.mac)}</strong> en los routers disponibles.`;
        container.innerHTML = `<div class="alert alert-info d-flex gap-2 align-items-start"><i class="bi bi-info-circle-fill mt-1"></i><div>${msg}</div></div>`;
        container.classList.remove('d-none');
        return;
    }

    // El primer resultado es el principal; el resto se ofrece como información
    // secundaria (por ejemplo, la misma MAC en otro router).
    const primary = data.results[0];
    const others = data.results.slice(1);

    let html = `
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="fw-semibold">Resultado para <span class="font-monospace">${escapeHtml(data.mac)}</span></div>
                    <div class="small text-muted">
                        ${data.count} ${data.count === 1 ? 'dispositivo' : 'dispositivos'}
                        &middot; ${data.reachable}/${data.total_routers} routers consultados
                        &middot; ${data.duration_ms} ms
                    </div>
                </div>
                <span class="badge ${statusBadge}">${statusLabel}</span>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="text-muted small">Dirección IP</div>
                        <div class="display-6 font-monospace fw-bold">${escapeHtml(primary.ip_address)}</div>
                        <div class="small">
                            <i class="bi bi-router me-1"></i>${escapeHtml(primary.router_name)}
                            ${primary.municipality ? ' &middot; ' + escapeHtml(primary.municipality) : ''}
                        </div>
                        <div class="mt-2 d-flex flex-wrap gap-1">
                            ${mechanismsOf(primary).map((label, i) => `<span class="badge ${i === 0 ? 'bg-success' : 'bg-secondary'}">${escapeHtml(label)}</span>`).join('')}
                            ${primary.is_active_match ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Histórico</span>'}
                            ${primary.hostname ? `<span class="badge bg-light text-dark border"><i class="bi bi-pc-display me-1"></i>${escapeHtml(primary.hostname)}</span>` : ''}
                        </div>
                    </div>
                    <button class="btn btn-primary px-4" data-ip="${escapeHtml(primary.ip_address)}">
                        <i class="bi bi-box-arrow-up-right me-2"></i>Abrir dispositivo
                    </button>
                </div>
            </div>`;

    if (others.length > 0) {
        html += `
            <details class="border-top">
                <summary class="px-3 py-2 small text-muted" style="cursor: pointer;">
                    ${others.length} ${others.length === 1 ? 'resultado adicional' : 'resultados adicionales'}
                    (misma MAC en otro equipo o con otra IP)
                </summary>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead>
                            <tr><th>IP</th><th>Router</th><th>Mecanismo</th><th>Estado</th><th></th></tr>
                        </thead>
                        <tbody>`;

        others.forEach(r => {
            html += `
                <tr>
                    <td class="font-monospace fw-semibold">${escapeHtml(r.ip_address)}</td>
                    <td class="small">${escapeHtml(r.router_name)}</td>
                    <td class="small">${mechanismsOf(r).map(l => escapeHtml(l)).join(', ')}</td>
                    <td>${activeBadge(r)}</td>
                    <td><button class="btn btn-sm btn-outline-primary" data-ip="${escapeHtml(r.ip_address)}">Abrir</button></td>
                </tr>`;
        });

        html += '</tbody></table></div></details>';
    }

    html += '</div>';
    container.innerHTML = html;
    container.classList.remove('d-none');

    document.querySelectorAll('[data-ip]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const ip = btn.getAttribute('data-ip');
            try {
                const res = await fetch(`/device/open/${encodeURIComponent(ip)}`, {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (data.url) window.open(data.url, '_blank', 'noopener,noreferrer');
            } catch (_) {
                alert('No se pudo abrir la URL.');
            }
        });
    });
}

function escapeHtml(s) {
    if (s == null) return '';
    return String(s).replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[m]));
}
</script>
@endpush