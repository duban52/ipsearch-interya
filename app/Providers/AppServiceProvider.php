<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Habilidades usadas por las vistas (@can('admin') / @can('superadmin')).
        // La autorización real de cada ruta se aplica con el middleware role:superadmin.
        Gate::define('admin', fn (User $user) => $user->isSuperAdmin());
        Gate::define('superadmin', fn (User $user) => $user->isSuperAdmin());

        // Las vistas usan Bootstrap 5; el paginador por defecto de Laravel 11 es
        // Tailwind y se renderizaba sin estilos (flechas SVG gigantes).
        Paginator::useBootstrapFive();

        // La configuración SMTP vive en la base de datos (panel de administración)
        // y se aplica al mailer en cada arranque. Si no hay configuración activa,
        // se mantiene la del .env.
        $this->app->make(\App\Services\Mail\MailConfigurator::class)->apply();
    }
}
