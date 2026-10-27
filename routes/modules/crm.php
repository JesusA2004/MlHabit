<?php

use App\Http\Controllers\Admin\Crm\AgendaController;
use App\Http\Controllers\Admin\Crm\ConsultationController;
use App\Http\Controllers\Admin\Crm\CoverageZoneController;
use App\Http\Controllers\Admin\Crm\CustomerController;
use App\Http\Controllers\Admin\Crm\LeadController;
use App\Http\Controllers\Admin\Crm\PrequalificationController;
use App\Http\Controllers\Public\Crm\ConsultationBookingController;
use App\Http\Controllers\Public\Crm\CoverageCheckController;
use App\Http\Controllers\Public\Crm\LeadRequestController;
use App\Http\Controllers\Public\Crm\PrequalificationRequestController;
use App\Http\Middleware\CaptureLeadAttribution;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo CRM: solicitudes, prospectos, clientes, asesorías,
| precalificación, cobertura y agenda.
|--------------------------------------------------------------------------
| Las pantallas del cliente sin sesión usan enlaces FIRMADOS (signed).
*/

// ── Público ────────────────────────────────────────────────────────────
Route::get('solicitar', [LeadRequestController::class, 'create'])
    ->middleware(CaptureLeadAttribution::class)
    ->name('public.solicitar');
Route::post('solicitar', [LeadRequestController::class, 'store'])
    ->middleware('throttle:crm-lead-submissions')
    ->name('public.solicitar.store');
Route::get('solicitar/gracias/{lead:uuid}', [LeadRequestController::class, 'thanks'])
    ->middleware('signed')
    ->name('public.solicitar.gracias');

Route::get('cobertura/verificar', CoverageCheckController::class)
    ->middleware('throttle:crm-coverage')
    ->name('public.cobertura.verificar');

Route::get('asesorias/disponibilidad', [ConsultationBookingController::class, 'availability'])
    ->middleware('throttle:crm-availability')
    ->name('public.asesoria.disponibilidad');

Route::middleware('signed')->group(function () {
    Route::get('solicitud/{lead:uuid}/asesoria', [ConsultationBookingController::class, 'create'])->name('public.asesoria.create');
    Route::post('solicitud/{lead:uuid}/asesoria', [ConsultationBookingController::class, 'store'])
        ->middleware('throttle:crm-customer-actions')
        ->name('public.asesoria.store');

    Route::get('asesoria/{consultation:uuid}', [ConsultationBookingController::class, 'show'])->name('public.asesoria.show');
    Route::post('asesoria/{consultation:uuid}/reprogramar', [ConsultationBookingController::class, 'reschedule'])
        ->middleware('throttle:crm-customer-actions')
        ->name('public.asesoria.reprogramar');
    Route::post('asesoria/{consultation:uuid}/cancelar', [ConsultationBookingController::class, 'cancel'])
        ->middleware('throttle:crm-customer-actions')
        ->name('public.asesoria.cancelar');

    Route::get('solicitud/{lead:uuid}/precalificacion', [PrequalificationRequestController::class, 'create'])->name('public.precalificacion.create');
    Route::post('solicitud/{lead:uuid}/precalificacion', [PrequalificationRequestController::class, 'store'])
        ->middleware('throttle:crm-customer-actions')
        ->name('public.precalificacion.store');
});

// ── Panel ──────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified', 'staff'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Prospectos
        Route::get('prospectos', [LeadController::class, 'index'])->name('prospectos.index');
        Route::post('prospectos', [LeadController::class, 'store'])->name('prospectos.store');
        Route::get('prospectos/{lead:uuid}', [LeadController::class, 'show'])->name('prospectos.show');
        Route::patch('prospectos/{lead:uuid}/etapa', [LeadController::class, 'updateStatus'])->name('prospectos.etapa');
        Route::patch('prospectos/{lead:uuid}/responsable', [LeadController::class, 'assign'])->name('prospectos.responsable');
        Route::patch('prospectos/{lead:uuid}/seguimiento', [LeadController::class, 'followUp'])->name('prospectos.seguimiento');
        Route::post('prospectos/{lead:uuid}/bitacora', [LeadController::class, 'storeActivity'])->name('prospectos.bitacora');
        Route::post('prospectos/{lead:uuid}/asesorias', [ConsultationController::class, 'storeForLead'])->name('prospectos.asesorias.store');
        Route::post('prospectos/{lead:uuid}/precalificacion', [PrequalificationController::class, 'start'])->name('prospectos.precalificacion.store');

        // Asesorías
        Route::get('asesorias', [ConsultationController::class, 'index'])->name('asesorias.index');
        Route::get('asesorias/disponibilidad', [ConsultationController::class, 'availability'])->name('asesorias.disponibilidad');
        Route::get('asesorias/{consultation:uuid}', [ConsultationController::class, 'show'])->name('asesorias.show');
        Route::patch('asesorias/{consultation:uuid}', [ConsultationController::class, 'update'])->name('asesorias.update');
        Route::patch('asesorias/{consultation:uuid}/estado', [ConsultationController::class, 'updateStatus'])->name('asesorias.estado');
        Route::post('asesorias/{consultation:uuid}/resultado', [ConsultationController::class, 'outcome'])->name('asesorias.resultado');
        Route::post('asesorias/{consultation:uuid}/reprogramar', [ConsultationController::class, 'reschedule'])->name('asesorias.reprogramar');
        Route::post('asesorias/{consultation:uuid}/cancelar', [ConsultationController::class, 'cancel'])->name('asesorias.cancelar');

        // Precalificaciones
        Route::get('precalificaciones', [PrequalificationController::class, 'index'])->name('precalificaciones.index');
        Route::get('precalificaciones/{prequalification:uuid}', [PrequalificationController::class, 'show'])->name('precalificaciones.show');
        Route::post('precalificaciones/{prequalification:uuid}/estado', [PrequalificationController::class, 'transition'])->name('precalificaciones.estado');
        Route::post('precalificaciones/{prequalification:uuid}/enlace', [PrequalificationController::class, 'resendLink'])->name('precalificaciones.enlace');

        // Clientes
        Route::get('clientes', [CustomerController::class, 'index'])->name('clientes.index');
        Route::get('clientes/{customer:uuid}', [CustomerController::class, 'show'])->name('clientes.show');
        Route::patch('clientes/{customer:uuid}', [CustomerController::class, 'update'])->name('clientes.update');
        Route::post('clientes/{customer:uuid}/domicilios', [CustomerController::class, 'storeAddress'])->name('clientes.domicilios.store');
        Route::put('clientes/{customer:uuid}/domicilios/{address}', [CustomerController::class, 'updateAddress'])->name('clientes.domicilios.update');
        Route::delete('clientes/{customer:uuid}/domicilios/{address}', [CustomerController::class, 'destroyAddress'])->name('clientes.domicilios.destroy');

        // Cobertura operativa
        Route::get('cobertura', [CoverageZoneController::class, 'index'])->name('cobertura.index');
        Route::get('cobertura/verificar', [CoverageZoneController::class, 'check'])->name('cobertura.verificar');
        Route::post('cobertura', [CoverageZoneController::class, 'store'])->name('cobertura.store');
        Route::put('cobertura/{zone}', [CoverageZoneController::class, 'update'])->name('cobertura.update');
        Route::delete('cobertura/{zone}', [CoverageZoneController::class, 'destroy'])->name('cobertura.destroy');
        Route::post('cobertura/{zone}/codigos', [CoverageZoneController::class, 'addPostalCodes'])->name('cobertura.codigos.store');
        Route::delete('cobertura/{zone}/codigos/{postalCode}', [CoverageZoneController::class, 'removePostalCode'])
            ->where('postalCode', '\d{5}')
            ->name('cobertura.codigos.destroy');
        Route::put('cobertura/{zone}/tecnicos', [CoverageZoneController::class, 'syncTechnicians'])->name('cobertura.tecnicos');

        // Agenda (disponibilidad de asesores y técnicos)
        Route::get('agenda', [AgendaController::class, 'index'])->name('agenda.index');
        Route::put('agenda/{user}/horario', [AgendaController::class, 'updateRules'])->name('agenda.horario');
        Route::post('agenda/{user}/excepciones', [AgendaController::class, 'storeException'])->name('agenda.excepciones.store');
        Route::put('agenda/excepciones/{exception}', [AgendaController::class, 'updateException'])->name('agenda.excepciones.update');
        Route::delete('agenda/excepciones/{exception}', [AgendaController::class, 'destroyException'])->name('agenda.excepciones.destroy');
    });
