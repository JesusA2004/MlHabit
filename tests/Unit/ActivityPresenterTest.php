<?php

use App\Http\Controllers\Settings\SecurityController;
use App\Support\Activity\ActivityPresenter;

test('known actions get exact labels', function () {
    expect(ActivityPresenter::actionLabel('usuario.dos_pasos_restablecido'))->toBe('Verificación en dos pasos restablecida')
        ->and(ActivityPresenter::actionLabel('permisos.actualizados'))->toBe('Permisos de rol actualizados');
});

test('module.verb actions are humanized with the module noun', function () {
    expect(ActivityPresenter::actionLabel('pago.confirmado'))->toBe('Pago confirmado')
        ->and(ActivityPresenter::actionLabel('cotizacion.aceptada'))->toBe('Cotización aceptada')
        ->and(ActivityPresenter::actionLabel('fabricacion.orden_emitida'))->toBe('Orden de fabricación orden emitida')
        ->and(ActivityPresenter::actionLabel('desconocido.algo_raro'))->toBe('Desconocido algo raro')
        ->and(ActivityPresenter::actionLabel('solo'))->toBe('Solo');
});

test('subjects map to spanish names with a graceful fallback', function () {
    expect(ActivityPresenter::subjectLabel('App\\Models\\SiteVisit'))->toBe('Visita técnica')
        ->and(ActivityPresenter::subjectLabel('App\\Models\\SupplierPayout'))->toBe('Pago a proveedor')
        ->and(ActivityPresenter::subjectLabel('App\\Models\\SomethingNew'))->toBe('Something New')
        ->and(ActivityPresenter::subjectLabel(null))->toBeNull();
});

test('user agents are summarized without dependencies', function () {
    expect(SecurityController::parseAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36 Edg/130.0'))
        ->toBe(['browser' => 'Edge', 'platform' => 'Windows', 'isMobile' => false])
        ->and(SecurityController::parseAgent('Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/130.0 Mobile Safari/537.36'))
        ->toBe(['browser' => 'Chrome', 'platform' => 'Android', 'isMobile' => true]);
});
