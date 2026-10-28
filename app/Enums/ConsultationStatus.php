<?php

namespace App\Enums;

/** Estado de una asesoría remota. Toda asesoría tiene costo: nace en pending_payment. */
enum ConsultationStatus: string
{
    /** Horario apartado (hold_expires_at) mientras el cliente paga con tarjeta. */
    case PendingPayment = 'pending_payment';
    case Scheduled = 'scheduled';
    /** Pagada: queda firme en la agenda. */
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';
    /** La cita se movió: existe otra asesoría con rescheduled_from_id = esta. */
    case Rescheduled = 'rescheduled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pendiente de pago',
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
            self::PendingPayment => 'warning',
            self::Scheduled => 'info',
            self::Confirmed => 'brand',
            self::Completed => 'success',
            self::NoShow => 'warning',
            self::Cancelled, self::Rescheduled => 'neutral',
        };
    }

    /** ¿Sigue ocupando la agenda (o su apartado) y puede moverse o cancelarse? */
    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }

    /** ¿Ya está firme en la agenda (pagada)? Recordatorios, métricas y portal. */
    public function isBooked(): bool
    {
        return in_array($this, [self::Scheduled, self::Confirmed], true);
    }

    public function isAwaitingPayment(): bool
    {
        return $this === self::PendingPayment;
    }

    /** @return list<self> */
    public static function active(): array
    {
        return [self::PendingPayment, self::Scheduled, self::Confirmed];
    }
}
