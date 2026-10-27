<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Estado del proveedor. */
enum SupplierStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Paused = 'paused';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Paused => 'En pausa',
            self::Inactive => 'Inactivo',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Paused => 'warning',
            self::Inactive => 'neutral',
        };
    }
}
