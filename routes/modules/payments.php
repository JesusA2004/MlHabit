<?php

use App\Http\Controllers\Admin\Payments\OpenpayDiagnosticsController;
use App\Http\Controllers\Admin\Payments\PaymentController;
use App\Http\Controllers\Payments\PaymentReturnController;
use App\Http\Controllers\Payments\ReceiptController;
use App\Http\Controllers\Webhooks\OpenpayWebhookController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo visits — pagos con Openpay: webhook, comprobantes, retorno 3DS
| genérico y finanzas en el panel.
|--------------------------------------------------------------------------
*/

// Webhook de Openpay: sin CSRF (lo llama Openpay), autenticado con HTTP Basic.
Route::post('webhooks/openpay', OpenpayWebhookController::class)
    // Laravel 13 protege el grupo web con PreventRequestForgery (padre de
    // ValidateCsrfToken): hay que excluir ambas o Openpay recibe 419.
    ->withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class])
    ->middleware('throttle:120,1')
    ->name('webhooks.openpay');

// Comprobante: enlace firmado o usuario autorizado (PaymentPolicy::view).
Route::get('pagos/{payment:uuid}/comprobante', ReceiptController::class)
    ->middleware('throttle:60,1')
    ->name('pagos.comprobante');

// Retorno genérico de 3D Secure (concilia por API; no confía en ?id=).
Route::get('pagos/{payment:uuid}/retorno', PaymentReturnController::class)
    ->middleware('throttle:20,1')
    ->name('pagos.retorno');

Route::middleware(['auth', 'verified', 'staff'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('pagos', [PaymentController::class, 'index'])->name('pagos.index');
        Route::get('pagos/{payment:uuid}', [PaymentController::class, 'show'])->name('pagos.show');
        Route::post('pagos/{payment:uuid}/reembolsos', [PaymentController::class, 'refund'])
            ->middleware('throttle:10,1')
            ->name('pagos.reembolsar');
        Route::post('pagos/{payment:uuid}/pago-manual', [PaymentController::class, 'recordManual'])->name('pagos.manual');
        Route::post('pagos/{payment:uuid}/conciliar', [PaymentController::class, 'reconcile'])
            ->middleware('throttle:20,1')
            ->name('pagos.conciliar');

        Route::get('openpay', [OpenpayDiagnosticsController::class, 'index'])->name('openpay.index');
        Route::post('openpay/probar', [OpenpayDiagnosticsController::class, 'test'])
            ->middleware('throttle:10,1')
            ->name('openpay.probar');
    });
