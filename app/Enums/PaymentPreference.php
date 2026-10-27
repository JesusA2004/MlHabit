<?php

namespace App\Enums;

/** Forma de pago preferida (declarativa; no implica crédito). */
enum PaymentPreference: string
{
    case Contado = 'contado';
    case Plazos = 'plazos';
    case AunNoSe = 'aun_no_se';

    public function label(): string
    {
        return match ($this) {
            self::Contado => 'De contado',
            self::Plazos => 'A plazos',
            self::AunNoSe => 'Aún no lo sé',
        };
    }
}
