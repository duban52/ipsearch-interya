@extends('layouts.app')

@section('title', 'Crear router')

@section('content')
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h2 class="h5 mb-0">Crear router</h2>
            </div>
            <div class="card-body">
                <div class="alert alert-info small">
                    <strong>Antes de guardar:</strong> el router necesita RouterOS <strong>7.1 o superior</strong> y el
                    servicio web habilitado (<code>/ip/service</code> → <code>www-ssl</code> en 443, o <code>www</code> en 80).
                    Usa un usuario dedicado de <strong>solo lectura</strong>. Se recomienda HTTPS: con HTTP la contraseña
                    viaja en Base64, es decir, en texto plano para cualquiera que capture el tráfico.
                </div>
                <form method="POST" action="{{ route('admin.routers.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descripción</label>
                        <textarea name="description" class="form-control">{{ old('description') }}</textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">IP / Hostname</label>
                            <input type="text" name="host" class="form-control" value="{{ old('host') }}" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-semibold">Puerto web (REST)</label>
                            <input type="number" name="api_port" class="form-control" value="{{ old('api_port', 443) }}" required>
                            <div class="form-text">REST usa el servicio web: <strong>443</strong> (HTTPS) o <strong>80</strong> (HTTP). 8728/8729 son de la API binaria y no sirven aquí.</div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-semibold">Usuario API</label>
                            <input type="text" name="api_user" class="form-control" value="{{ old('api_user') }}" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Contraseña API</label>
                            <input type="password" name="api_password" class="form-control" autocomplete="new-password" required>
                            <div class="form-text">Se almacena cifrada.</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label d-block fw-semibold">Usar SSL</label>
                            <input type="checkbox" name="use_ssl" value="1" {{ old('use_ssl', true) ? 'checked' : '' }} class="form-check-input">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label d-block fw-semibold">Verificar SSL</label>
                            <input type="checkbox" name="verify_ssl" value="1" {{ old('verify_ssl', true) ? 'checked' : '' }} class="form-check-input">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Ubicación</label>
                            <input type="text" name="location" class="form-control" value="{{ old('location') }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Municipio</label>
                            <input type="text" name="municipality" class="form-control" value="{{ old('municipality') }}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Prioridad</label>
                            <select name="priority" class="form-select">
                                @for ($i = 1; $i <= 10; $i++)
                                    <option value="{{ $i }}" {{ (int) old('priority', 5) === $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Timeout conexión (ms)</label>
                            <input type="number" name="connect_timeout_ms" class="form-control" value="{{ old('connect_timeout_ms', 3000) }}" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Timeout lectura (ms)</label>
                            <input type="number" name="read_timeout_ms" class="form-control" value="{{ old('read_timeout_ms', 8000) }}" required>
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_active" value="1" id="is_active" class="form-check-input" checked>
                        <label class="form-check-label" for="is_active">Router activo</label>
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar router</button>
                    <a href="{{ route('admin.routers.index') }}" class="btn btn-link">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
