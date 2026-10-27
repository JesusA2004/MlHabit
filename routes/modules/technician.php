<?php

use App\Http\Controllers\Technician\MeasurementController;
use App\Http\Controllers\Technician\VisitController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| App de campo (/tecnico): Hoy + visitas de medición. Cada técnico ve solo
| lo suyo. /tecnico/instalaciones/* lo agrega el módulo ops.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:tecnico'])
    ->prefix('tecnico')
    ->name('tecnico.')
    ->group(function () {
        Route::get('/', [VisitController::class, 'today'])->name('hoy');
        Route::get('visitas', [VisitController::class, 'index'])->name('visitas.index');
        Route::get('visitas/{visit:uuid}', [VisitController::class, 'show'])->name('visitas.show');
        Route::post('visitas/{visit:uuid}/iniciar', [VisitController::class, 'start'])->name('visitas.iniciar');
        Route::post('visitas/{visit:uuid}/completar', [VisitController::class, 'complete'])->name('visitas.completar');
        Route::post('visitas/{visit:uuid}/medicion', [VisitController::class, 'measure'])->name('visitas.medicion');

        Route::get('mediciones/{measurement}', [MeasurementController::class, 'edit'])->name('mediciones.edit');
        Route::put('mediciones/{measurement}', [MeasurementController::class, 'update'])->name('mediciones.update');
        Route::post('mediciones/{measurement}/enviar', [MeasurementController::class, 'submit'])->name('mediciones.enviar');
        Route::post('mediciones/{measurement}/corregir', [MeasurementController::class, 'revise'])->name('mediciones.corregir');
        Route::post('mediciones/{measurement}/archivos', [MeasurementController::class, 'storeFile'])->name('mediciones.archivos.store');
        Route::delete('mediciones/{measurement}/archivos/{attachment}', [MeasurementController::class, 'destroyFile'])->whereNumber('attachment')->name('mediciones.archivos.destroy');
    });
