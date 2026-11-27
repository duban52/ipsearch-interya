@extends('layouts.app')

@section('title', 'Invitar usuario')

@section('content')
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card shadow-sm">
            <div class="card-header">
                <h2 class="h5 mb-0">Invitar usuario</h2>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Se crea la cuenta inactiva y se genera un enlace de activación de un solo uso,
                    válido durante 7 días. La contraseña la define el usuario al activar su cuenta.
                </p>
                <form method="POST" action="{{ route('invite.store') }}">
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
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Rol</label>
                        <select name="role" class="form-select">
                            <option value="user" {{ old('role') === 'user' ? 'selected' : '' }}>Usuario</option>
                            <option value="superadmin" {{ old('role') === 'superadmin' ? 'selected' : '' }}>Superadministrador</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Generar invitación</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-link">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
