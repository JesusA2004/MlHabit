<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Tipos de garantía (se registran por separado). */
enum WarrantyType: string
{
    use HasOptions;

    case Product = 'product';
    case Installation = 'installation';
    case AdditionalService = 'additional_service';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Producto',
            self::Installation => 'Instalación',
            self::AdditionalService => 'Servicio adicional',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Product => 'brand',
            self::Installation => 'info',
            self::AdditionalService => 'neutral',
        };
    }
}
