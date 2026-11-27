<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // IMPORTANTE: el middleware de cuenta activa NO se añade al grupo "web" global.
        // Si se aplica globalmente, las rutas públicas (login, recuperación de
        // contraseña, invitación) redirigen a /login y se produce un bucle infinito.
        // Se aplica explícitamente con el alias "active" en los grupos protegidos.
        $middleware->alias([
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
