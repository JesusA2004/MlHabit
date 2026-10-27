<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Tipo de proveedor: fabrica, instala o ambos. */
enum SupplierType: string
{
    use HasOptions;

    case Fabricator = 'fabricator';
    case Installer = 'installer';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Fabricator => 'Fabricante',
            self::Installer => 'Instalador',
            self::Both => 'Fabrica e instala',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Fabricator => 'brand',
            self::Installer => 'info',
            self::Both => 'success',
        };
    }
}
