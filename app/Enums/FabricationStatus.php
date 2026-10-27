<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Orden de fabricación. */
enum FabricationStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Issued = 'issued';
    case InProduction = 'in_production';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Issued => 'Emitida',
            self::InProduction => 'En producción',
            self::Ready => 'Lista para entrega',
            self::Delivered => 'Entregada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft, self::Cancelled => 'neutral',
            self::Issued => 'info',
            self::InProduction => 'brand',
            self::Ready, self::Delivered => 'success',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Issued, self::Cancelled],
            self::Issued => [self::InProduction, self::Ready, self::Cancelled],
            self::InProduction => [self::Ready, self::Cancelled],
            self::Ready => [self::Delivered],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /** ¿La orden ya terminó su fabricación (lista o entregada)? */
    public function isFinished(): bool
    {
        return in_array($this, [self::Ready, self::Delivered], true);
    }
}
