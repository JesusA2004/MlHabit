<?php

namespace App\Enums;

enum AvailabilityExceptionType: string
{
    case Blocked = 'blocked';
    case Vacation = 'vacation';
    /** Horario adicional fuera de la disponibilidad recurrente. */
    case Extra = 'extra';

    public function label(): string
    {
        return match ($this) {
            self::Blocked => 'Bloqueo',
            self::Vacation => 'Vacaciones',
            self::Extra => 'Horario adicional',
        };
    }

    public function removesTime(): bool
    {
        return $this !== self::Extra;
    }
}
