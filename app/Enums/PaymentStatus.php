<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Estado de un pago (columna payments.status). Solo PaymentService lo cambia. */
enum PaymentStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Processing = 'processing';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
    case Disputed = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Processing => 'Procesando',
            self::Paid => 'Pagado',
            self::Failed => 'Rechazado',
            self::Cancelled => 'Cancelado',
            self::PartiallyRefunded => 'Reembolso parcial',
            self::Refunded => 'Reembolsado',
            self::Disputed => 'En contracargo',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::Processing => 'info',
            self::Paid => 'success',
            self::Failed => 'danger',
            self::Cancelled => 'neutral',
            self::PartiallyRefunded => 'warning',
            self::Refunded => 'neutral',
            self::Disputed => 'danger',
        };
    }

    /** El dinero se recibió (aunque después se haya reembolsado o disputado). */
    public function isSettled(): bool
    {
        return in_array($this, [self::Paid, self::PartiallyRefunded, self::Refunded, self::Disputed], true);
    }

    /** Se puede iniciar un intento de cobro nuevo. */
    public function acceptsAttempts(): bool
    {
        return in_array($this, [self::Pending, self::Failed], true);
    }

    /** Se puede reembolsar (total o parcial). */
    public function isRefundable(): bool
    {
        return in_array($this, [self::Paid, self::PartiallyRefunded], true);
    }

    /** @return list<string> */
    public static function settledValues(): array
    {
        return array_values(array_map(fn (self $s) => $s->value, array_filter(self::cases(), fn (self $s) => $s->isSettled())));
    }
}
