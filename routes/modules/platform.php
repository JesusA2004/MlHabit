<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StorageController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo platform: tablero, usuarios, roles, actividad, configuración,
| almacenamiento, invitaciones y centro de notificaciones.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'staff'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Todo el personal llega aquí tras iniciar sesión; cada bloque del
        // tablero se filtra por permiso dentro del controlador.
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::middleware('can:users.manage')->group(function () {
            Route::get('usuarios', [UserController::class, 'index'])->name('usuarios.index');
            Route::post('usuarios', [UserController::class, 'store'])->name('usuarios.store');
            Route::patch('usuarios/{user}', [UserController::class, 'update'])->name('usuarios.update');
            Route::post('usuarios/{user}/invitacion', [UserController::class, 'resendInvitation'])
                ->middleware('throttle:sensitive-actions')
                ->name('usuarios.invitacion');
            Route::delete('usuarios/{user}/dos-pasos', [UserController::class, 'resetTwoFactor'])
                ->middleware('throttle:sensitive-actions')
                ->name('usuarios.dos-pasos');
        });

        Route::middleware('can:roles.manage')->group(function () {
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
            Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
            Route::post('roles/{role}/restablecer', [RoleController::class, 'reset'])->name('roles.restablecer');
        });

        Route::get('actividad', [ActivityController::class, 'index'])
            ->middleware('can:activity.view')
            ->name('actividad.index');

        Route::middleware('can:settings.manage')->group(function () {
            Route::get('configuracion', [SettingsController::class, 'index'])->name('configuracion.index');
            Route::put('configuracion/{group}', [SettingsController::class, 'update'])
                ->whereIn('group', SettingsController::GROUPS)
                ->name('configuracion.update');

            Route::get('almacenamiento', [StorageController::class, 'index'])->name('almacenamiento.index');
            Route::put('almacenamiento/{provider}', [StorageController::class, 'update'])->name('almacenamiento.update');
            Route::post('almacenamiento/{provider}/activar', [StorageController::class, 'activate'])->name('almacenamiento.activar');
            Route::post('almacenamiento/{provider}/probar', [StorageController::class, 'test'])
                ->middleware('throttle:sensitive-actions')
                ->name('almacenamiento.probar');
        });
    });

// Enlace del correo de invitación (firmado, 72 h): genera un token de
// restablecimiento fresco y lleva a "Crea tu contraseña".
Route::get('invitacion/{user}', InvitationController::class)
    ->middleware(['signed', 'throttle:sensitive-actions'])
    ->name('invitacion.aceptar');

Route::middleware(['auth'])->group(function () {
    Route::get('notificaciones', [NotificationController::class, 'index'])
        ->middleware('throttle:notifications')
        ->name('notificaciones.index');
    Route::post('notificaciones/leidas', [NotificationController::class, 'markAllRead'])->name('notificaciones.leidas');
    Route::patch('notificaciones/{notification}/leida', [NotificationController::class, 'markRead'])
        ->whereUuid('notification')
        ->name('notificaciones.leida');
});
