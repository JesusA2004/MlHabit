<?php

namespace App\Enums;

enum BookingStatus: string
{
    /** Apartado temporal mientras se paga/confirma; expira en hold_expires_at. */
    case Held = 'held';
    case Confirmed = 'confirmed';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Held => 'Apartado',
            self::Confirmed => 'Confirmado',
            self::Released => 'Liberado',
        };
    }
}
