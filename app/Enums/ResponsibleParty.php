<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Responsable de atender/absorber la incidencia. */
enum ResponsibleParty: string
{
    use HasOptions;

    case Supplier = 'supplier';
    case Internal = 'internal';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Supplier => 'Proveedor',
            self::Internal => 'ML Habitat',
            self::Client => 'Cliente',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Supplier => 'warning',
            self::Internal => 'brand',
            self::Client => 'info',
        };
    }
}
