<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cuenta (módulo platform): perfil y seguridad.
|--------------------------------------------------------------------------
| El cambio de contraseña y la verificación en dos pasos usan las rutas de
| Fortify (PUT /user/password, /user/two-factor-*). Las cuentas no se
| eliminan desde aquí: tienen historial (proyectos, pagos, bitácora).
*/

Route::middleware(['auth'])->group(function () {
    Route::get('settings', fn () => redirect('/settings/profile'));

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->name('security.edit');

    Route::delete('settings/sessions', [SecurityController::class, 'destroyOtherSessions'])
        ->middleware('throttle:6,1')
        ->name('sessions.destroy-others');
});
