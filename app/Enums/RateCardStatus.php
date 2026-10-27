<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Versión de tarifario: solo el borrador es editable. */
enum RateCardStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Active = 'active';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Active => 'Vigente',
            self::Retired => 'Retirado',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Active => 'success',
            self::Retired => 'warning',
        };
    }
}
