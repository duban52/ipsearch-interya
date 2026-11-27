@extends('layouts.app')

@section('content')
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0">Registro de auditoría</h2>
        <a href="{{ route('admin.audit') }}" class="btn btn-sm btn-outline-primary">← Volver</a>
    </div>
    <div class="card-body">
        <dl class="row">
            <dt class="col-md-3">Acción</dt><dd class="col-md-9">{{ $log->action }}</dd>
            <dt class="col-md-3">Usuario</dt><dd class="col-md-9">{{ $log->user_label ?? 'Sistema' }}</dd>
            <dt class="col-md-3">Descripción</dt><dd class="col-md-9">{{ $log->description }}</dd>
            <dt class="col-md-3">IP origen</dt><dd class="col-md-9 font-monospace">{{ $log->ip_address ?? '—' }}</dd>
            <dt class="col-md-3">Resultado</dt><dd class="col-md-9">
                @switch($log->result)
                    @case('success')<span class="badge bg-success">Éxito</span>@break
                    @case('fail')<span class="badge bg-danger">Fallo</span>@break
                    @default<span class="badge bg-secondary">Denegado</span>
                @endswitch
            </dd>
            <dt class="col-md-3">Fecha</dt><dd class="col-md-9">{{ $log->created_at->format('d/m/Y H:i:s') }}</dd>
            <dt class="col-md-3">Operación</dt><dd class="col-md-9 font-mono small">{{ $log->operation_id ?? '—' }}</dd>
            @if($log->context)
                <dt class="col-md-3">Contexto</dt><dd class="col-md-9"><pre class="small bg-light p-2 rounded">{{ json_encode($log->context, JSON_PRETTY_PRINT) }}</pre></dd>
            @endif
        </dl>
    </div>
</div>
@endsection