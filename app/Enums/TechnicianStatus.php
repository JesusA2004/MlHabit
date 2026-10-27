<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Disponibilidad del técnico/instalador. */
enum TechnicianStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Unavailable = 'unavailable';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Unavailable => 'No disponible',
            self::Inactive => 'Inactivo',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Unavailable => 'warning',
            self::Inactive => 'neutral',
        };
    }
}
