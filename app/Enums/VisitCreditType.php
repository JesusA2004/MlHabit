<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Cómo se calcula el crédito abonable de una visita pagada. */
enum VisitCreditType: string
{
    use HasOptions;

    /** Porcentaje de la tarifa pagada. */
    case Percent = 'percent';
    /** Monto fijo (nunca mayor que la tarifa). */
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Porcentaje de la tarifa',
            self::Fixed => 'Monto fijo',
        };
    }

    public function tone(): string
    {
        return 'neutral';
    }
}
