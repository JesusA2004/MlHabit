<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Estado de la incidencia. */
enum IncidentStatus: string
{
    use HasOptions;

    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierta',
            self::InProgress => 'En atención',
            self::Resolved => 'Resuelta',
            self::Closed => 'Cerrada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::InProgress => 'warning',
            self::Resolved => 'success',
            self::Closed, self::Cancelled => 'neutral',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::InProgress, self::Resolved, self::Cancelled],
            self::InProgress => [self::Resolved, self::Open, self::Cancelled],
            self::Resolved => [self::Closed, self::InProgress],
            self::Closed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /** Abierta = bloquea el cierre del proyecto. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Open, self::InProgress], true);
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return [self::Open->value, self::InProgress->value];
    }
}
