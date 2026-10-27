<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Concepto de un pago. visit_fee lo atiende el módulo de visitas;
 * deposit/installment/balance los atiende projects (listener de PaymentConfirmed).
 */
enum PaymentConcept: string
{
    use HasOptions;

    case VisitFee = 'visit_fee';
    case Deposit = 'deposit';
    case Installment = 'installment';
    case Balance = 'balance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::VisitFee => 'Visita técnica',
            self::Deposit => 'Anticipo',
            self::Installment => 'Abono',
            self::Balance => 'Liquidación',
            self::Other => 'Otro',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::VisitFee => 'info',
            self::Deposit => 'brand',
            self::Installment, self::Balance => 'success',
            self::Other => 'neutral',
        };
    }
}
