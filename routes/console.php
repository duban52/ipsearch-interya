<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
| Requieren el planificador activo:
|   php artisan schedule:work          (desarrollo)
|   * * * * * php artisan schedule:run (producción)
*/

// Mantiene al día el estado de los routers aunque no haya búsquedas.
Schedule::command('routers:health')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();
