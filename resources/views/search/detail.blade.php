@extends('layouts.app')

@section('title', 'Detalle de búsqueda')

@section('content')
<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0">Resultado de búsqueda</h2>
        <a href="{{ route('search.history') }}" class="btn btn-sm btn-outline-primary">← Volver al historial</a>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">MAC consultada</div>
                        <div class="font-monospace fw-bold">{{ $search->mac_normalized }}</div>
                        <div class="text-muted small">{{ $search->mac_input }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">IP encontrada</div>
                        <div class="font-monospace fw-bold">{{ $search->ip_address ?? '—' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Routers consultados</div>
                        <div class="font-monospace fw-bold">{{ $search->reachable_routers }}/{{ $search->total_routers }}</div>
                        <div class="text-muted small">{{ $search->result_count }} resultado(s)</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Estado</div>
                        <div class="badge @switch($search->status) @case('completed')bg-success@break @case('partial')bg-warning text-dark@break @case('no_results')bg-secondary@break @case('error')bg-danger@break @default bg-secondary @endswitch">{{ ucfirst($search->status) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Duración</div>
                        <div class="font-monospace fw-bold">{{ $search->duration_ms }} ms</div>
                        <div class="text-muted small">{{ $search->created_at->format('d/m/Y H:i:s') }}</div>
                    </div>
                </div>
            </div>
        </div>

        @if ($search->results->isNotEmpty())
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>IP</th>
                        <th>Router</th>
                        <th>Municipio</th>
                        <th>Mecanismo</th>
                        <th>Estado</th>
                        <th>Hostname</th>
                        <th>Servidor</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($search->results as $r)
                        <tr>
                            <td class="font-monospace fw-semibold">
                                {{ $r->ip_address }}
                                @if ($loop->first)
                                    <span class="badge bg-primary ms-1">Principal</span>
                                @endif
                            </td>
                            <td>{{ $r->router_name }}</td>
                            <td class="small">{{ $r->municipality ?? '—' }}</td>
                            <td>
                                @foreach ($r->mechanismLabels() as $i => $label)
                                    <span class="badge {{ $i === 0 ? 'bg-success' : 'bg-secondary' }}">{{ $label }}</span>
                                @endforeach
                            </td>
                            <td>
                                @if ($r->is_active_match)
                                    <span class="badge bg-success">Activo</span>
                                @else
                                    <span class="badge bg-light text-dark border">Histórico</span>
                                @endif
                            </td>
                            <td class="small">{{ $r->hostname ?? '—' }}</td>
                            <td class="small">{{ $r->server ?? '—' }}</td>
                            <td>
                                <a href="{{ $deviceScheme }}://{{ $r->ip_address }}:{{ $devicePort }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener noreferrer">Abrir</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="alert alert-info">No se encontraron resultados para esta MAC.</div>
        @endif
    </div>
</div>
@endsection