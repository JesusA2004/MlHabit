<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Vigencia de la garantía. */
enum WarrantyStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Expired = 'expired';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Vigente',
            self::Expired => 'Vencida',
            self::Voided => 'Anulada',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Expired => 'neutral',
            self::Voided => 'danger',
        };
    }
}
