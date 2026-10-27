<?php

use App\Http\Controllers\Admin\Projects\ContractController;
use App\Http\Controllers\Admin\Projects\CustomerInvitationController;
use App\Http\Controllers\Admin\Projects\DesignController;
use App\Http\Controllers\Admin\Projects\ProjectController;
use App\Http\Controllers\Admin\Projects\ProjectTimelineController;
use App\Http\Controllers\Admin\Projects\QuoteController;
use App\Http\Controllers\Projects\QuotePrintController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo projects (panel): proyectos, diseño, cotizaciones, contratos.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'staff'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Proyectos
        Route::get('proyectos', [ProjectController::class, 'index'])->name('proyectos.index');
        Route::post('proyectos', [ProjectController::class, 'store'])->name('proyectos.store');
        Route::get('proyectos/{project}', [ProjectController::class, 'show'])->name('proyectos.show');
        Route::patch('proyectos/{project}', [ProjectController::class, 'update'])->name('proyectos.update');
        Route::post('proyectos/{project}/estado', [ProjectController::class, 'transition'])->name('proyectos.estado');
        Route::post('proyectos/{project}/notas', [ProjectTimelineController::class, 'storeNote'])->name('proyectos.notas.store');
        Route::post('proyectos/{project}/archivos', [ProjectTimelineController::class, 'storeFile'])->name('proyectos.archivos.store');
        Route::patch('proyectos/{project}/archivos/{attachment}', [ProjectTimelineController::class, 'updateFile'])->name('proyectos.archivos.update');
        Route::delete('proyectos/{project}/archivos/{attachment}', [ProjectTimelineController::class, 'destroyFile'])->name('proyectos.archivos.destroy');

        // Contratos con crm y visits
        Route::post('prospectos/{lead:uuid}/proyecto', [ProjectController::class, 'storeFromLead'])->name('proyectos.desde-prospecto');
        Route::post('visitas/{visit:uuid}/proyecto', [ProjectController::class, 'storeFromVisit'])->name('proyectos.desde-visita');
        Route::post('clientes/{customer:uuid}/invitacion', [CustomerInvitationController::class, 'store'])->name('clientes.invitacion');

        // Diseño
        Route::get('disenos', [DesignController::class, 'index'])->name('disenos.index');
        Route::post('proyectos/{project}/disenos', [DesignController::class, 'store'])->name('disenos.store');
        Route::put('disenos/{design}', [DesignController::class, 'update'])->name('disenos.update');
        Route::post('disenos/{design}/enviar', [DesignController::class, 'send'])->name('disenos.enviar');
        Route::post('disenos/{design}/aprobacion-tecnica', [DesignController::class, 'approveTechnical'])->name('disenos.aprobacion-tecnica');
        Route::post('disenos/{design}/archivos', [DesignController::class, 'storeFile'])->name('disenos.archivos.store');
        Route::delete('disenos/{design}/archivos/{attachment}', [DesignController::class, 'destroyFile'])->whereNumber('attachment')->name('disenos.archivos.destroy');

        // Cotizaciones
        Route::get('cotizaciones', [QuoteController::class, 'index'])->name('cotizaciones.index');
        Route::get('proyectos/{project}/cotizaciones/crear', [QuoteController::class, 'create'])->name('cotizaciones.create');
        Route::post('proyectos/{project}/cotizaciones', [QuoteController::class, 'store'])->name('cotizaciones.store');
        Route::get('cotizaciones/{quote}/editar', [QuoteController::class, 'edit'])->name('cotizaciones.edit');
        Route::put('cotizaciones/{quote}', [QuoteController::class, 'update'])->name('cotizaciones.update');
        Route::post('cotizaciones/{quote}/enviar', [QuoteController::class, 'send'])->name('cotizaciones.enviar');
        Route::post('cotizaciones/{quote}/aceptar', [QuoteController::class, 'accept'])->name('cotizaciones.aceptar');
        Route::post('cotizaciones/{quote}/rechazar', [QuoteController::class, 'reject'])->name('cotizaciones.rechazar');

        // Contrato y anticipo
        Route::post('contratos/{contract}/anticipo-manual', [ContractController::class, 'recordDeposit'])->name('contratos.anticipo-manual');
    });

// Vista imprimible (equipo con permiso o cliente dueño; QuotePolicy::view).
Route::middleware(['auth'])
    ->get('cotizaciones/{quote}/imprimir', QuotePrintController::class)
    ->name('cotizaciones.imprimir');
