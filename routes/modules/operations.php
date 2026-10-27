<?php

use App\Http\Controllers\Admin\Operations\FabricationOrderController;
use App\Http\Controllers\Admin\Operations\IncidentController;
use App\Http\Controllers\Admin\Operations\InstallationOrderController;
use App\Http\Controllers\Admin\Operations\ProjectLookupController;
use App\Http\Controllers\Admin\Operations\QualityController;
use App\Http\Controllers\Admin\Operations\RateCardController;
use App\Http\Controllers\Admin\Operations\SupplierController;
use App\Http\Controllers\Admin\Operations\SupplierPayoutController;
use App\Http\Controllers\Admin\Operations\TechnicianController;
use App\Http\Controllers\Admin\Operations\WarrantyController;
use App\Http\Controllers\Portal\Operations\IncidentController as PortalIncidentController;
use App\Http\Controllers\Portal\Operations\InstallationController as PortalInstallationController;
use App\Http\Controllers\Technician\Operations\InstallationController as TechnicianInstallationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Operaciones (módulo ops)
|--------------------------------------------------------------------------
| Proveedores, técnicos, tarifarios, fabricación, instalación, calidad,
| pagos a proveedores, incidencias y garantías. La autorización fina vive
| en Policies/Gates; aquí solo se separan las áreas.
*/

Route::middleware(['auth', 'verified', 'staff'])->prefix('admin')->name('admin.')->group(function () {
    // Proveedores
    Route::get('proveedores', [SupplierController::class, 'index'])->name('proveedores.index');
    Route::post('proveedores', [SupplierController::class, 'store'])->name('proveedores.store');
    Route::get('proveedores/{supplier}', [SupplierController::class, 'show'])->name('proveedores.show');
    Route::put('proveedores/{supplier}', [SupplierController::class, 'update'])->name('proveedores.update');
    Route::delete('proveedores/{supplier}', [SupplierController::class, 'destroy'])->name('proveedores.destroy');
    Route::post('proveedores/{supplier}/contactos', [SupplierController::class, 'storeContact'])->name('proveedores.contactos.store');
    Route::put('proveedores/{supplier}/contactos/{contact}', [SupplierController::class, 'updateContact'])->name('proveedores.contactos.update');
    Route::delete('proveedores/{supplier}/contactos/{contact}', [SupplierController::class, 'destroyContact'])->name('proveedores.contactos.destroy');
    Route::post('proveedores/{supplier}/evaluaciones', [SupplierController::class, 'storeEvaluation'])->name('proveedores.evaluaciones.store');
    Route::post('proveedores/{supplier}/documentos', [SupplierController::class, 'attachDocuments'])->name('proveedores.documentos.store');
    Route::delete('proveedores/{supplier}/documentos/{media}', [SupplierController::class, 'detachDocument'])->whereNumber('media')->name('proveedores.documentos.destroy');

    // Técnicos e instaladores
    Route::get('tecnicos', [TechnicianController::class, 'index'])->name('tecnicos.index');
    Route::post('tecnicos', [TechnicianController::class, 'store'])->name('tecnicos.store');
    Route::get('tecnicos/{technicianProfile}', [TechnicianController::class, 'show'])->name('tecnicos.show');
    Route::put('tecnicos/{technicianProfile}', [TechnicianController::class, 'update'])->name('tecnicos.update');
    Route::post('tecnicos/{technicianProfile}/invitacion', [TechnicianController::class, 'resendInvite'])->middleware('throttle:6,1')->name('tecnicos.invitacion');
    Route::post('tecnicos/{technicianProfile}/documentos', [TechnicianController::class, 'attachDocuments'])->name('tecnicos.documentos.store');
    Route::delete('tecnicos/{technicianProfile}/documentos/{media}', [TechnicianController::class, 'detachDocument'])->whereNumber('media')->name('tecnicos.documentos.destroy');

    // Tarifarios versionados
    Route::get('tarifarios', [RateCardController::class, 'index'])->name('tarifarios.index');
    Route::post('tarifarios', [RateCardController::class, 'store'])->name('tarifarios.store');
    Route::get('tarifarios/{rateCard}', [RateCardController::class, 'show'])->name('tarifarios.show');
    Route::put('tarifarios/{rateCard}', [RateCardController::class, 'update'])->name('tarifarios.update');
    Route::delete('tarifarios/{rateCard}', [RateCardController::class, 'destroy'])->name('tarifarios.destroy');
    Route::post('tarifarios/{rateCard}/activar', [RateCardController::class, 'activate'])->name('tarifarios.activar');
    Route::post('tarifarios/{rateCard}/nueva-version', [RateCardController::class, 'newVersion'])->name('tarifarios.nueva-version');

    // Fabricación
    Route::get('fabricacion', [FabricationOrderController::class, 'index'])->name('fabricacion.index');
    Route::post('fabricacion', [FabricationOrderController::class, 'store'])->name('fabricacion.store');
    Route::get('fabricacion/{fabricationOrder}', [FabricationOrderController::class, 'show'])->name('fabricacion.show');
    Route::put('fabricacion/{fabricationOrder}', [FabricationOrderController::class, 'update'])->name('fabricacion.update');
    Route::post('fabricacion/{fabricationOrder}/estado', [FabricationOrderController::class, 'transition'])->name('fabricacion.estado');
    Route::post('fabricacion/{fabricationOrder}/documentos', [FabricationOrderController::class, 'attachDocuments'])->name('fabricacion.documentos.store');
    Route::delete('fabricacion/{fabricationOrder}/documentos/{media}', [FabricationOrderController::class, 'detachDocument'])->whereNumber('media')->name('fabricacion.documentos.destroy');
    Route::post('fabricacion/{fabricationOrder}/pago', [FabricationOrderController::class, 'requestPayout'])->name('fabricacion.pago');

    // Instalaciones
    Route::get('instalaciones', [InstallationOrderController::class, 'index'])->name('instalaciones.index');
    Route::post('instalaciones', [InstallationOrderController::class, 'store'])->name('instalaciones.store');
    Route::get('instalaciones/{installationOrder}', [InstallationOrderController::class, 'show'])->name('instalaciones.show');
    Route::put('instalaciones/{installationOrder}', [InstallationOrderController::class, 'update'])->name('instalaciones.update');
    Route::post('instalaciones/{installationOrder}/partidas', [InstallationOrderController::class, 'addItems'])->name('instalaciones.partidas.store');
    Route::put('instalaciones/{installationOrder}/partidas/{item}', [InstallationOrderController::class, 'updateItem'])->name('instalaciones.partidas.update');
    Route::delete('instalaciones/{installationOrder}/partidas/{item}', [InstallationOrderController::class, 'destroyItem'])->name('instalaciones.partidas.destroy');
    Route::post('instalaciones/{installationOrder}/extras', [InstallationOrderController::class, 'storeExtra'])->name('instalaciones.extras.store');
    Route::post('instalaciones/{installationOrder}/extras/{item}/aprobar', [InstallationOrderController::class, 'approveExtra'])->name('instalaciones.extras.aprobar');
    Route::post('instalaciones/{installationOrder}/extras/{item}/rechazar', [InstallationOrderController::class, 'rejectExtra'])->name('instalaciones.extras.rechazar');
    Route::post('instalaciones/{installationOrder}/programar', [InstallationOrderController::class, 'schedule'])->name('instalaciones.programar');
    Route::post('instalaciones/{installationOrder}/cancelar', [InstallationOrderController::class, 'cancel'])->name('instalaciones.cancelar');
    Route::post('instalaciones/{installationOrder}/enviar-a-calidad', [InstallationOrderController::class, 'skipClientReview'])->name('instalaciones.enviar-a-calidad');
    Route::post('instalaciones/{installationOrder}/pago', [InstallationOrderController::class, 'requestPayout'])->name('instalaciones.pago');

    // Calidad
    Route::get('calidad', [QualityController::class, 'index'])->name('calidad.index');
    Route::get('calidad/{installationOrder}', [QualityController::class, 'show'])->name('calidad.show');
    Route::post('calidad/{installationOrder}/partidas/{item}', [QualityController::class, 'reviewItem'])->name('calidad.partidas.revisar');
    Route::post('calidad/{installationOrder}/aprobar', [QualityController::class, 'approve'])->name('calidad.aprobar');
    Route::post('calidad/{installationOrder}/correcciones', [QualityController::class, 'requireFixes'])->name('calidad.correcciones');

    // Pagos a proveedores
    Route::get('pagos-proveedores', [SupplierPayoutController::class, 'index'])->name('pagos-proveedores.index');
    Route::post('pagos-proveedores/{payout}/autorizar', [SupplierPayoutController::class, 'authorizePayout'])->name('pagos-proveedores.autorizar');
    Route::post('pagos-proveedores/{payout}/pagar', [SupplierPayoutController::class, 'pay'])->name('pagos-proveedores.pagar');
    Route::post('pagos-proveedores/{payout}/rechazar', [SupplierPayoutController::class, 'reject'])->name('pagos-proveedores.rechazar');
    Route::post('pagos-proveedores/{payout}/reenviar', [SupplierPayoutController::class, 'resubmit'])->name('pagos-proveedores.reenviar');

    // Incidencias
    Route::get('incidencias', [IncidentController::class, 'index'])->name('incidencias.index');
    Route::post('incidencias', [IncidentController::class, 'store'])->name('incidencias.store');
    Route::get('incidencias/{incident}', [IncidentController::class, 'show'])->name('incidencias.show');
    Route::put('incidencias/{incident}', [IncidentController::class, 'update'])->name('incidencias.update');
    Route::post('incidencias/{incident}/estado', [IncidentController::class, 'transition'])->name('incidencias.estado');
    Route::post('incidencias/{incident}/evidencia', [IncidentController::class, 'attachEvidence'])->name('incidencias.evidencia.store');
    Route::delete('incidencias/{incident}/evidencia/{media}', [IncidentController::class, 'detachEvidence'])->whereNumber('media')->name('incidencias.evidencia.destroy');

    // Garantías
    Route::get('garantias', [WarrantyController::class, 'index'])->name('garantias.index');
    Route::post('garantias', [WarrantyController::class, 'store'])->name('garantias.store');
    Route::get('garantias/{warranty}', [WarrantyController::class, 'show'])->name('garantias.show');
    Route::put('garantias/{warranty}', [WarrantyController::class, 'update'])->name('garantias.update');
    Route::post('garantias/{warranty}/anular', [WarrantyController::class, 'void'])->name('garantias.anular');
    Route::post('garantias/{warranty}/reclamos', [WarrantyController::class, 'storeClaim'])->name('garantias.reclamos.store');
    Route::put('garantias/{warranty}/reclamos/{claim}', [WarrantyController::class, 'updateClaim'])->name('garantias.reclamos.update');
    Route::post('garantias/{warranty}/reclamos/{claim}/evidencia', [WarrantyController::class, 'attachClaimEvidence'])->name('garantias.reclamos.evidencia');

    // Búsqueda de proyectos para selectores de operaciones
    Route::get('operaciones/proyectos', ProjectLookupController::class)->name('operaciones.proyectos');
});

// App de campo: instalaciones del técnico (solo las suyas).
Route::middleware(['auth', 'role:tecnico'])->prefix('tecnico/instalaciones')->name('tecnico.instalaciones.')->group(function () {
    Route::get('/', [TechnicianInstallationController::class, 'index'])->name('index');
    Route::get('{installationOrder}', [TechnicianInstallationController::class, 'show'])->name('show');
    Route::post('{installationOrder}/iniciar', [TechnicianInstallationController::class, 'start'])->name('iniciar');
    Route::post('{installationOrder}/partidas/{item}', [TechnicianInstallationController::class, 'completeItem'])->name('partidas.completar');
    Route::post('{installationOrder}/extras', [TechnicianInstallationController::class, 'storeExtra'])->name('extras.store');
    Route::post('{installationOrder}/enviar', [TechnicianInstallationController::class, 'submit'])->name('enviar');
});

// Portal del cliente: conformidad de instalación y reporte de incidencias.
Route::middleware(['auth', 'verified', 'role:cliente'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('instalaciones/{installationOrder}', [PortalInstallationController::class, 'show'])->name('instalaciones.show');
    Route::post('instalaciones/{installationOrder}/conformidad', [PortalInstallationController::class, 'conformity'])->name('instalaciones.conformidad');
    Route::get('incidencias/nueva', [PortalIncidentController::class, 'create'])->name('incidencias.create');
    Route::post('incidencias', [PortalIncidentController::class, 'store'])->middleware('throttle:10,1')->name('incidencias.store');
});
