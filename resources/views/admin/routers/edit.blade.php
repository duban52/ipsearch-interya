@extends('layouts.app')

@section('title', 'Editar router')

@section('content')
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card">
            <div class="card-header">
                <h2 class="h3 mb-0">Editar router</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.routers.update', $router) }}">
                    @csrf @method('PUT')
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $router->name) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descripción</label>
                        <textarea name="description" class="form-control">{{ old('description', $router->description) }}</textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">IP / Hostname</label>
                            <input type="text" name="host" class="form-control" value="{{ old('host', $router->host) }}" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Puerto web (REST)</label>
                            <input type="number" name="api_port" class="form-control" value="{{ old('api_port', $router->api_port) }}" required>
                            <div class="form-text">443 (HTTPS) u 80 (HTTP), no 8728/8729.</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Usuario</label>
                            <input type="text" name="api_user" class="form-control" value="{{ old('api_user', $router->api_user) }}" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Contraseña</label>
                            <input type="password" name="api_password" class="form-control" autocomplete="new-password"
                                   placeholder="Dejar vacío para mantener la actual">
                        </div>
                        <div class="col-md-8 mb-3 d-flex align-items-end gap-4">
                            <div class="form-check">
                                <input type="checkbox" name="use_ssl" value="1" id="use_ssl" {{ $router->use_ssl ? 'checked' : '' }} class="form-check-input">
                                <label class="form-check-label" for="use_ssl">Usar SSL</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="verify_ssl" value="1" id="verify_ssl" {{ $router->verify_ssl ? 'checked' : '' }} class="form-check-input">
                                <label class="form-check-label" for="verify_ssl">Verificar SSL</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="is_active" value="1" id="is_active" {{ $router->is_active ? 'checked' : '' }} class="form-check-input">
                                <label class="form-check-label" for="is_active">Router activo</label>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Ubicación</label>
                            <input type="text" name="location" class="form-control" value="{{ old('location', $router->location) }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Municipio</label>
                            <input type="text" name="municipality" class="form-control" value="{{ old('municipality', $router->municipality) }}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Prioridad</label>
                            <select name="priority" class="form-select">
                                @for($i = 1; $i <= 10; $i++)
                                    <option value="{{ $i }}" {{ $router->priority == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Timeout conexión (ms)</label>
                            <input type="number" name="connect_timeout_ms" class="form-control" value="{{ old('connect_timeout_ms', $router->connect_timeout_ms) }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Timeout lectura (ms)</label>
                            <input type="number" name="read_timeout_ms" class="form-control" value="{{ old('read_timeout_ms', $router->read_timeout_ms) }}" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary w-100">Actualizar router</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection