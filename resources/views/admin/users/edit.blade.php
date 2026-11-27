@extends('layouts.app')

@section('title', 'Editar usuario')

@section('content')
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">Usuario: {{ $user->fullName() }}</h2>
                <div class="d-flex gap-2 align-items-center">
                    @if ($user->is_active)
                        <span class="badge bg-success">Activo</span>
                    @else
                        <span class="badge bg-warning text-dark">Suspendido</span>
                        <form action="{{ route('admin.users.reactivate', $user) }}" method="POST" class="d-inline">
                            @csrf
                            <button class="btn btn-sm btn-outline-success">Reactivar</button>
                        </form>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.users.update', $user) }}">
                    @csrf @method('PUT')
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Nombre</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Apellido</label>
                            <input type="text" name="lastname" class="form-control" value="{{ old('lastname', $user->lastname) }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Correo</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Usuario</label>
                        <input type="text" name="username" class="form-control" value="{{ old('username', $user->username) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Rol</label>
                        <select name="role" class="form-select">
                            <option value="user" {{ old('role', $user->role) === 'user' ? 'selected' : '' }}>Usuario</option>
                            <option value="superadmin" {{ old('role', $user->role) === 'superadmin' ? 'selected' : '' }}>Superadministrador</option>
                        </select>
                        <div class="form-text">Cambiar el rol modifica los privilegios de inmediato.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Nueva contraseña</label>
                        <input type="password" name="password" class="form-control" autocomplete="new-password" placeholder="Dejar vacío para mantener la actual">
                        <div class="form-text">Mínimo 10 caracteres: mayúscula, minúscula, número y símbolo.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Confirmar nueva contraseña</label>
                        <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-link">Volver</a>
                </form>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <form action="{{ route('admin.users.suspend', $user) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('¿Suspender usuario?')">
                    @csrf
                    <button class="btn btn-sm btn-outline-warning" {{ ! $user->is_active ? 'disabled' : '' }}>Suspender</button>
                </form>
                <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('¿Eliminar usuario?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
