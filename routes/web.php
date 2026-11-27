<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\RouterAdminController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\Api\MacResolverController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas públicas (acciones controladas de autenticación)
|--------------------------------------------------------------------------
| Ninguna de estas rutas permite consultar MikroTik. Solo formularios de
| acceso y recuperación de credenciales.
*/

Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('login.submit');

// Recuperación de contraseña (formularios públicos)
Route::get('forgot-password', [PasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('forgot-password', [PasswordController::class, 'sendLinkEmail'])
    ->middleware('throttle:5,1')
    ->name('password.email');
Route::get('reset-password/{token}', [PasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('reset-password', [PasswordController::class, 'resetFromToken'])
    ->middleware('throttle:5,1')
    ->name('password.store');

// Activación de cuenta por invitación (formularios públicos)
Route::get('invite/{token}', [RegisteredUserController::class, 'acceptInvite'])->name('invite.accept');
Route::post('invite/{token}', [RegisteredUserController::class, 'acceptInviteStore'])->name('invite.accept.submit');

// El registro libre está deshabilitado: los usuarios se crean por invitación
// desde el panel de superadministración. Estas rutas se conservan como
// redirección para no romper enlaces antiguos.
Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
Route::post('register', [RegisteredUserController::class, 'store'])->name('register.store');

/*
|--------------------------------------------------------------------------
| Rutas protegidas: usuario autenticado y con cuenta activa
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Buscador MAC → IP
    Route::get('/', [SearchController::class, 'index'])->name('search.index');
    Route::post('search', [SearchController::class, 'search'])
        ->middleware('throttle:30,1')
        ->name('search.perform');

    // Historial propio (la ruta literal va antes que la paramétrica)
    Route::get('search/history', [SearchController::class, 'history'])->name('search.history');
    Route::get('search/{id}', [SearchController::class, 'detail'])
        ->whereNumber('id')
        ->name('search.detail');

    // Apertura de la interfaz web del dispositivo
    Route::get('device/open/{ip}', [SearchController::class, 'openDevice'])
        ->where('ip', '[0-9a-fA-F:.]+')
        ->middleware('throttle:60,1')
        ->name('device.open');

    // Perfil y contraseña
    Route::get('perfil', [UserController::class, 'edit'])->name('profile.edit');
    Route::put('perfil', [UserController::class, 'update'])->name('profile.update');
    Route::put('password', [PasswordController::class, 'updateCurrent'])->name('password.update');

    // Verificación de correo electrónico
    Route::get('verify-email', [VerificationController::class, 'show'])->name('verification.notice');
    Route::get('verify/{id}/{hash}', [VerificationController::class, 'verify'])->name('verification.verify');
    Route::post('email/verification-notification', [VerificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // API interna consumida por el frontend autenticado
    Route::prefix('api/v1')->group(function () {
        Route::post('/mac-search', [MacResolverController::class, 'search'])
            ->middleware('throttle:30,1')
            ->name('api.mac.search');
        Route::get('/mac-search/history', [MacResolverController::class, 'history'])->name('api.mac.history');
    });
});

/*
|--------------------------------------------------------------------------
| Rutas administrativas: solo superadministrador con cuenta activa
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active', 'role:superadmin'])->prefix('admin')->group(function () {
    Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('estadisticas', [AdminDashboardController::class, 'stats'])->name('admin.stats');
    Route::get('salud', [AdminDashboardController::class, 'health'])->name('admin.health');

    // Routers MikroTik
    Route::resource('routers', RouterAdminController::class)
        ->except(['show'])
        ->names('admin.routers');
    Route::post('routers/{router}/test', [RouterAdminController::class, 'test'])->name('admin.routers.test');

    // Usuarios
    Route::resource('users', UserAdminController::class)
        ->except(['show', 'destroy'])
        ->names('admin.users');
    Route::delete('users/{user}', [UserAdminController::class, 'destroy'])->name('admin.users.destroy');
    Route::post('users/{user}/suspend', [UserAdminController::class, 'suspend'])->name('admin.users.suspend');
    Route::post('users/{user}/reactivate', [UserAdminController::class, 'reactivate'])->name('admin.users.reactivate');
    Route::post('users/{user}/resend-invitation', [UserAdminController::class, 'resendInvitation'])->name('admin.users.resendInvitation');

    // Invitaciones
    Route::get('users/invite/create', [RegisteredUserController::class, 'createInvite'])->name('invite.create');
    Route::post('users/invite/create', [RegisteredUserController::class, 'storeInvite'])->name('invite.store');
    Route::post('invite/resend/{token}', [RegisteredUserController::class, 'resendInvite'])->name('invite.resend');

    // Auditoría
    Route::get('auditoria', [AdminSettingsController::class, 'audit'])->name('admin.audit');
    Route::get('auditoria/{id}', [AdminSettingsController::class, 'auditDetail'])
        ->whereNumber('id')
        ->name('admin.audit.detail');

    // Configuración general y SMTP
    Route::get('config', [AdminSettingsController::class, 'index'])->name('admin.config');
    Route::post('config', [AdminSettingsController::class, 'update'])->name('admin.config.update');
    Route::post('smtp', [AdminSettingsController::class, 'updateSmtp'])->name('admin.smtp.update');
    Route::post('smtp/test', [AdminSettingsController::class, 'testSmtp'])
        ->middleware('throttle:6,1')
        ->name('admin.smtp.test');
});

/*
|--------------------------------------------------------------------------
| Redirecciones y utilidades
|--------------------------------------------------------------------------
*/

Route::get('/home', function () {
    return redirect()->route('search.index');
})->name('home');

// Health check público básico (sin datos sensibles ni acceso a infraestructura)
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
})->name('health');
