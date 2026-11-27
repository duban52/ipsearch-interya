@extends('layouts.app')

@section('title', 'Routers MikroTik')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h3 mb-0">Routers MikroTik</h2>
            <a href="{{ route('admin.routers.create') }}" class="btn btn-primary">Agregar router</a>
        </div>
    </div>
</div>

<div id="routerTestResult" class="mb-3"></div>

<div class="row mt-3">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover mb-0 small align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nombre</th>
                            <th>Servicio REST</th>
                            <th>Ubicación</th>
                            <th>Municipio</th>
                            <th>Estado</th>
                            <th>Prioridad</th>
                            <th>Última comprobación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($routers as $i => $router)
                            <tr>
                                <td class="align-middle">{{ $routers->firstItem() + $i }}</td>
                                <td class="fw-semibold">{{ $router->name }}</td>
                                <td class="small font-monospace">{{ $router->use_ssl ? 'https' : 'http' }}://{{ $router->host }}:{{ $router->api_port }}</td>
                                <td class="small">{{ $router->location ?? '—' }}</td>
                                <td class="small">{{ $router->municipality ?? '—' }}</td>
                                <td>
                                    @switch($router->connectivity_status)
                                        @case('ok')<span class="badge bg-success">Activo</span>@break
                                        @case('fail')<span class="badge bg-danger">Inactivo</span>@break
                                        @default<span class="badge bg-secondary">Sin comprobar</span>
                                    @endswitch
                                </td>
                                <td class="small font-monospace">{{ $router->priority }}</td>
                                <td class="small">{{ $router->last_checked_at?->diffForHumans() ?? 'Nunca' }}</td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="{{ route('admin.routers.edit', $router) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                                        <form action="{{ route('admin.routers.test', $router) }}" method="POST" class="js-router-test d-inline" data-name="{{ $router->name }}">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-warning" type="submit">Probar</button>
                                        </form>
                                        <form action="{{ route('admin.routers.destroy', $router) }}" method="POST"
                                              onsubmit="return confirm('¿Eliminar router {{ $router->name }}?')" class="d-inline">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    No hay routers registrados. Usa <strong>Agregar router</strong> para dar de alta el primero.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($routers->hasPages())
                <div class="card-footer">{{ $routers->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection

@include('partials.router-test')
