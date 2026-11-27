@extends('layouts.app')

@section('title', 'Salud de infraestructura')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="h3 mb-0">Salud de infraestructura</h2>
        <p class="text-muted small mb-0">
            El estado se actualiza en cada búsqueda, al probar manualmente y por el sondeo
            automático (<code>routers:health</code>, cada 10 minutos).
        </p>
    </div>
</div>

<div id="routerTestResult" class="mb-3"></div>

<div class="card shadow-sm">
    <div class="card-body">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Router</th>
                    <th>Servicio REST</th>
                    <th>Municipio</th>
                    <th>Estado</th>
                    <th>Última comprobación</th>
                    <th>Última conexión</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($routers as $r)
                    <tr>
                        <td class="fw-semibold">{{ $r->name }}</td>
                        <td class="small font-monospace text-muted">{{ $r->use_ssl ? 'https' : 'http' }}://{{ $r->host }}:{{ $r->api_port }}</td>
                        <td class="small">{{ $r->municipality ?? '—' }}</td>
                        <td>
                            @switch($r->connectivity_status)
                                @case('ok')<span class="badge bg-success">Activo</span>@break
                                @case('fail')<span class="badge bg-danger">Inactivo</span>@break
                                @default<span class="badge bg-secondary">Sin comprobar</span>
                            @endswitch
                        </td>
                        <td class="small">{{ $r->last_checked_at?->diffForHumans() ?? 'Nunca' }}</td>
                        <td class="small">{{ $r->last_success_at?->diffForHumans() ?? '—' }}</td>
                        <td>
                            <form action="{{ route('admin.routers.test', $r) }}" method="POST" class="js-router-test" data-name="{{ $r->name }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-primary" type="submit">Probar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No hay routers activos registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@include('partials.router-test')
