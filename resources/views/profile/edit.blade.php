@extends('layouts.app')

@section('title', 'Perfil')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">Perfil de usuario</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('profile.update') }}">
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
                        <label class="form-label fw-semibold">Correo electrónico</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Usuario</label>
                        <input type="text" name="username" class="form-control" value="{{ old('username', $user->username) }}" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mt-4" id="password">
            <div class="card-header">
                <h2 class="h5 mb-0">Cambiar contraseña</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('password.update') }}">
                    @csrf @method('PUT')
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Contraseña actual</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nueva contraseña</label>
                        <input type="password" name="password" class="form-control" required>
                        <div class="form-text">Mínimo 10 caracteres: mayúscula, minúscula, número y símbolo.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Confirmar contraseña</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Actualizar contraseña</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection