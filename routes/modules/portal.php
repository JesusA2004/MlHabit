<?php

use App\Http\Controllers\Portal\DesignController;
use App\Http\Controllers\Portal\PaymentController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Portal\QuoteController;
use App\Http\Controllers\Portal\RequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Portal del cliente (/portal). Toda consulta se filtra por el cliente del
| usuario autenticado. /portal/instalaciones/* y /portal/incidencias/* los
| agrega el módulo ops. La invitación al portal usa /invitacion/{user}
| (módulo platform).
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'role:cliente'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {
        Route::get('/', [PortalController::class, 'home'])->name('inicio');
        Route::post('proyectos/{project}/seleccionar', [PortalController::class, 'select'])->name('proyectos.seleccionar');
        Route::get('solicitudes', [RequestController::class, 'index'])->name('solicitudes.index');
        Route::get('citas', [PortalController::class, 'appointments'])->name('citas');
        Route::get('documentos', [PortalController::class, 'documents'])->name('documentos');
        Route::get('garantias', [PortalController::class, 'support'])->name('garantias');
        Route::get('ayuda', [PortalController::class, 'help'])->name('ayuda');

        Route::get('cotizaciones', [QuoteController::class, 'index'])->name('cotizaciones.index');
        Route::get('cotizaciones/{quote}', [QuoteController::class, 'show'])->name('cotizaciones.show');
        Route::post('cotizaciones/{quote}/aceptar', [QuoteController::class, 'accept'])->middleware('throttle:10,1')->name('cotizaciones.aceptar');
        Route::post('cotizaciones/{quote}/rechazar', [QuoteController::class, 'reject'])->middleware('throttle:10,1')->name('cotizaciones.rechazar');

        Route::get('disenos', [DesignController::class, 'index'])->name('disenos.index');
        Route::post('disenos/{design}/decision', [DesignController::class, 'decide'])->middleware('throttle:10,1')->name('disenos.decision');

        Route::get('pagos', [PaymentController::class, 'index'])->name('pagos.index');
        Route::get('pagos/anticipo/{contract}', [PaymentController::class, 'deposit'])->name('pagos.anticipo');
        Route::post('pagos/{payment:uuid}/cobrar', [PaymentController::class, 'charge'])->middleware('throttle:10,1')->name('pagos.cobrar');
    });
