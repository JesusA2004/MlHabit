<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Estado de una visita técnica a domicilio. */
enum VisitStatus: string
{
    use HasOptions;

    case PendingPayment = 'pending_payment';
    case PaymentProcessing = 'payment_processing';
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pendiente de pago',
            self::PaymentProcessing => 'Confirmando pago',
            self::Scheduled => 'Agendada',
            self::Confirmed => 'Confirmada',
            self::InProgress => 'En curso',
            self::Completed => 'Realizada',
            self::NoShow => 'Cliente ausente',
            self::Cancelled => 'Cancelada',
            self::Expired => 'Apartado vencido',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::PendingPayment => 'warning',
            self::PaymentProcessing => 'info',
            self::Scheduled, self::Confirmed => 'brand',
            self::InProgress => 'info',
            self::Completed => 'success',
            self::NoShow => 'danger',
            self::Cancelled, self::Expired => 'neutral',
        };
    }

    /** Aún sin pago confirmado (apartado temporal). */
    public function isAwaitingPayment(): bool
    {
        return in_array($this, [self::PendingPayment, self::PaymentProcessing], true);
    }

    /** Pagada y por realizarse (se puede reprogramar/reasignar/cancelar). */
    public function isUpcoming(): bool
    {
        return in_array($this, [self::Scheduled, self::Confirmed], true);
    }

    /** Estado final: ya no cambia. */
    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::NoShow, self::Cancelled, self::Expired], true);
    }

    /** @return list<string> Estados que cuentan contra el cupo diario de la zona. */
    public static function occupyingValues(): array
    {
        return [
            self::PendingPayment->value, self::PaymentProcessing->value, self::Scheduled->value,
            self::Confirmed->value, self::InProgress->value, self::Completed->value,
        ];
    }
}
