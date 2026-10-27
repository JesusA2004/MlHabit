<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Reclamo de garantía. */
enum WarrantyClaimStatus: string
{
    use HasOptions;

    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierto',
            self::InProgress => 'En atención',
            self::Resolved => 'Resuelto',
            self::Rejected => 'No procede',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::InProgress => 'warning',
            self::Resolved => 'success',
            self::Rejected => 'neutral',
        };
    }

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
