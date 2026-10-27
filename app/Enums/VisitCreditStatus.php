<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Crédito de visita abonable al anticipo del contrato (lo aplica projects). */
enum VisitCreditStatus: string
{
    use HasOptions;

    case Available = 'available';
    case Applied = 'applied';
    case Voided = 'voided';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::Applied => 'Aplicado',
            self::Voided => 'Anulado',
            self::Expired => 'Vencido',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Applied => 'brand',
            self::Voided => 'danger',
            self::Expired => 'neutral',
        };
    }
}
