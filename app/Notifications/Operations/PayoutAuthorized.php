<?php

namespace App\Notifications\Operations;

use App\Models\SupplierPayout;
use App\Support\Money;

/** A quien solicitó el pago y a quien puede pagarlo: pago autorizado. */
class PayoutAuthorized extends OperationsNotification
{
    public function __construct(public SupplierPayout $payout) {}

    protected function kind(): string
    {
        return 'payout_authorized';
    }

    protected function title(): string
    {
        return "Pago a proveedor {$this->payout->folio} autorizado";
    }

    protected function lines(): array
    {
        return [
            'Se autorizó el pago por '.Money::of($this->payout->amount)->format().'.',
            'Ya puede registrarse el pago con su referencia.',
        ];
    }

    protected function actionLabel(): string
    {
        return 'Ver pagos a proveedores';
    }

    protected function actionUrl(object $notifiable): string
    {
        return $this->link('admin.pagos-proveedores.index', [], '/admin');
    }

    protected function tone(): string
    {
        return 'success';
    }

    protected function icon(): string
    {
        return 'hand-coins';
    }
}
