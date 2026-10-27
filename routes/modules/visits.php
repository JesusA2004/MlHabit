<?php

use App\Http\Controllers\Admin\Visits\VisitController;
use App\Http\Controllers\Admin\Visits\VisitPolicyController;
use App\Http\Controllers\Public\Visits\VisitCheckoutController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo visits: checkout público de la visita técnica y agenda de visitas
| en el panel. Pagos/Openpay viven en routes/modules/payments.php.
|--------------------------------------------------------------------------
*/

// ---------------------------------------------------------------- Público
Route::prefix('agendar-visita')->name('public.visita.')->group(function () {
    Route::get('/', [VisitCheckoutController::class, 'open'])->name('abierta');

    Route::post('cobertura', [VisitCheckoutController::class, 'coverage'])
        ->middleware('throttle:30,1')
        ->name('cobertura');
    Route::get('horarios', [VisitCheckoutController::class, 'slots'])
        ->middleware('throttle:60,1')
        ->name('horarios');
    Route::post('/', [VisitCheckoutController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('reservar');

    Route::get('pago/{payment:uuid}', [VisitCheckoutController::class, 'pay'])
        ->middleware('signed')
        ->name('pago');
    Route::post('pago/{payment:uuid}/cobrar', [VisitCheckoutController::class, 'charge'])
        ->middleware(['signed', 'throttle:10,1'])
        ->name('cobrar');
    // Retorno de 3D Secure: sin firma (Openpay agrega ?id=); solo concilia por API.
    Route::get('pago/{payment:uuid}/retorno', [VisitCheckoutController::class, 'threeDsReturn'])
        ->middleware('throttle:20,1')
        ->name('retorno');
    Route::get('pago/{payment:uuid}/estado', [VisitCheckoutController::class, 'status'])
        ->middleware(['signed', 'throttle:60,1'])
        ->name('estado');

    // Enlace firmado que crm envía al prospecto (contrato docs/EQUIPO.md).
    Route::get('{lead:uuid}', [VisitCheckoutController::class, 'forLead'])
        ->middleware('signed')
        ->name('inicio');
});

// ---------------------------------------------------------------- Panel
Route::middleware(['auth', 'verified', 'staff'])
    ->prefix('admin/visitas')
    ->name('admin.visitas.')
    ->group(function () {
        Route::get('/', [VisitController::class, 'index'])->name('index');
        Route::get('crear', [VisitController::class, 'create'])->name('create');
        Route::get('disponibilidad', [VisitController::class, 'availability'])->name('disponibilidad');
        Route::post('/', [VisitController::class, 'store'])->middleware('throttle:30,1')->name('store');

        Route::get('politica', [VisitPolicyController::class, 'index'])->name('politica.index');
        Route::post('politica', [VisitPolicyController::class, 'store'])->name('politica.store');
        Route::post('politica/{policy}/activar', [VisitPolicyController::class, 'activate'])->name('politica.activar');

        Route::get('{visit:uuid}', [VisitController::class, 'show'])->name('show');
        Route::post('{visit:uuid}/tecnico', [VisitController::class, 'assign'])->name('asignar');
        Route::post('{visit:uuid}/reprogramar', [VisitController::class, 'reschedule'])->name('reprogramar');
        Route::post('{visit:uuid}/cancelar', [VisitController::class, 'cancel'])->name('cancelar');
        Route::post('{visit:uuid}/ausente', [VisitController::class, 'noShow'])->name('ausente');
        Route::post('{visit:uuid}/confirmar', [VisitController::class, 'confirm'])->name('confirmar');
        Route::post('{visit:uuid}/realizada', [VisitController::class, 'complete'])->name('realizada');
        Route::post('{visit:uuid}/enlace-pago', [VisitController::class, 'sendPaymentLink'])->middleware('throttle:10,1')->name('enlace-pago');
        Route::post('{visit:uuid}/pago-manual', [VisitController::class, 'recordPayment'])->name('pago-manual');
    });
