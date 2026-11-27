@extends('layouts.app')

@section('title', 'Auditoría')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h3">Registro de auditoría</h2>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.audit') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Acción</label>
                <select name="action" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold small">Resultado</label>
                <select name="result" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach (['success' => 'Éxito', 'fail' => 'Fallo', 'denied' => 'Denegado'] as $value => $label)
                        <option value="{{ $value }}" {{ request('result') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold small">Desde</label>
                <input type="date" name="desde" value="{{ request('desde') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold small">Hasta</label>
                <input type="date" name="hasta" value="{{ request('hasta') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
                <a href="{{ route('admin.audit') }}" class="btn btn-sm btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 small align-middle">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Descripción</th>
                        <th>IP origen</th>
                        <th>Resultado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="small">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $log->user_label ?? 'Sistema' }}</td>
                            <td><span class="badge bg-secondary">{{ $log->action }}</span></td>
                            <td class="small">{{ Str::limit($log->description, 80) }}</td>
                            <td class="small font-monospace">{{ $log->ip_address ?? '—' }}</td>
                            <td>
                                @switch($log->result)
                                    @case('success')<span class="badge bg-success">Éxito</span>@break
                                    @case('fail')<span class="badge bg-danger">Fallo</span>@break
                                    @default<span class="badge bg-secondary">Denegado</span>
                                @endswitch
                            </td>
                            <td>
                                <a href="{{ route('admin.audit.detail', $log) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No hay registros con los filtros seleccionados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        {{ $logs->links() }}
    </div>
</div>
@endsection
