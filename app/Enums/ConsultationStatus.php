<?php

namespace App\Enums;

/** Estado de una asesoría remota. */
enum ConsultationStatus: string
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';
    /** La cita se movió: existe otra asesoría con rescheduled_from_id = esta. */
    case Rescheduled = 'rescheduled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Agendada',
            self::Confirmed => 'Confirmada',
            self::Completed => 'Realizada',
            self::NoShow => 'No se presentó',
            self::Cancelled => 'Cancelada',
            self::Rescheduled => 'Reprogramada',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Scheduled => 'info',
            self::Confirmed => 'brand',
            self::Completed => 'success',
            self::NoShow => 'warning',
            self::Cancelled, self::Rescheduled => 'neutral',
        };
    }

    /** ¿Sigue ocupando la agenda y puede moverse o cancelarse? */
    public function isActive(): bool
    {
        return in_array($this, [self::Scheduled, self::Confirmed], true);
    }

    /** @return list<self> */
    public static function active(): array
    {
        return [self::Scheduled, self::Confirmed];
    }
}
