@extends('layouts.app')

@section('title', 'Verifica tu correo')

@section('content')
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card shadow-sm">
            <div class="card-header">
                <h2 class="h5 mb-0">Verifica tu correo electrónico</h2>
            </div>
            <div class="card-body">
                <p class="mb-3">
                    Enviamos un enlace de verificación a <strong>{{ $email }}</strong>.
                    Revisa tu bandeja de entrada y haz clic en el enlace para confirmar tu cuenta.
                </p>
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">Reenviar enlace</button>
                    <a href="{{ route('search.index') }}" class="btn btn-link">Continuar sin verificar</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
