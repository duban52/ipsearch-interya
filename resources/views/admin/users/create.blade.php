@extends('layouts.app')

@section('title', 'Crear usuario')

@section('content')
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card">
            <div class="card-header">
                <h2 class="h5 mb-0">Crear usuario</h2>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    La cuenta se crea activa y con contraseña definida por el administrador.
                    Para que el propio usuario defina su clave, usa <strong>Invitar usuario</strong>.
                </p>
                <form method="POST" action="{{ route('admin.users.store') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Nombre</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Apellido</label>
                            <input type="text" name="lastname" class="form-control" value="{{ old('lastname') }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Correo</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Usuario</label>
                        <input type="text" name="username" class="form-control" value="{{ old('username') }}" required>
                        <div class="form-text">Solo letras, números, guiones y guiones bajos.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Contraseña</label>
                        <input type="password" name="password" class="form-control" autocomplete="new-password" required>
                        <div class="form-text">Mínimo 10 caracteres: mayúscula, minúscula, número y símbolo.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirmar contraseña</label>
                        <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Rol</label>
                        <select name="role" class="form-select">
                            <option value="user" {{ old('role') === 'user' ? 'selected' : '' }}>Usuario</option>
                            <option value="superadmin" {{ old('role') === 'superadmin' ? 'selected' : '' }}>Superadministrador</option>
                        </select>
                    </div>
                    <div class="mb-4 form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ old('is_active', true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">Activar inmediatamente</label>
                    </div>
                    <button type="submit" class="btn btn-primary">Crear usuario</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-link">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
