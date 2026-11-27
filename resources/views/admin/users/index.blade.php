@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h3">Usuarios</h2>
    <div class="d-flex gap-2">
        <a href="{{ route('invite.create') }}" class="btn btn-outline-primary">Invitar usuario</a>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Crear usuario</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 small align-middle">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Correo</th>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Último acceso</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td class="fw-medium">{{ $user->fullName() }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->username }}</td>
                            <td><span class="badge {{ $user->role === 'superadmin' ? 'bg-danger' : 'bg-primary' }}">{{ ucfirst($user->role) }}</span></td>
                            <td>
                                @if($user->is_active)
                                    <span class="badge bg-success">Activo</span>
                                @else
                                    <span class="badge bg-warning text-dark">Suspendido</span>
                                @endif
                            </td>
                            <td class="small">{{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Nunca' }}</td>
                            <td class="d-flex gap-2">
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                                @if(!$user->is_active)
                                    <form action="{{ route('admin.users.reactivate', $user) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-success">Reactivar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        {{ $users->links() }}
    </div>
</div>
@endsection