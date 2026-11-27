@extends('layouts.app')

@section('title', 'Historial de búsquedas')

@section('content')
<div class="card shadow-sm">
    <div class="card-header">
        <h2 class="h5 mb-0">Historial de consultas MAC → IP</h2>
    </div>
    <div class="table-responsive p-0">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>MAC</th>
                    <th>IP encontrada</th>
                    <th>Routers</th>
                    <th>Resultados</th>
                    <th>Estado</th>
                    <th>Duración</th>
                    <th>Fecha</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($searches->items() as $s)
                    <tr>
                        <td class="font-monospace">{{ $s->mac_normalized }}</td>
                        <td class="font-monospace">{{ $s->ip_address ?? '—' }}</td>
                        <td>
                            <span class="badge bg-secondary">{{ $s->reachable_routers }}/{{ $s->total_routers }}</span>
                        </td>
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
                        <td class="font-monospace small">{{ $s->duration_ms }} ms</td>
                        <td>{{ $s->created_at->format('d/m/Y H:i:s') }}</td>
                        <td>
                            <a href="{{ route('search.detail', ['id' => $s->id]) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        {{ $searches->links() }}
    </div>
</div>
@endsection