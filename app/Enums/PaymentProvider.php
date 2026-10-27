<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PaymentProvider: string
{
    use HasOptions;

    case Openpay = 'openpay';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Openpay => 'Openpay',
            self::Manual => 'Registro manual',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Openpay => 'brand',
            self::Manual => 'neutral',
        };
    }
}
