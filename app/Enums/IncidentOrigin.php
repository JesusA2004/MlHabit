<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Quién detectó la incidencia. */
enum IncidentOrigin: string
{
    use HasOptions;

    case Client = 'client';
    case Installer = 'installer';
    case Quality = 'quality';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'Cliente',
            self::Installer => 'Instalador',
            self::Quality => 'Calidad',
            self::Internal => 'Equipo interno',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Client => 'brand',
            self::Installer, self::Quality => 'info',
            self::Internal => 'neutral',
        };
    }
}
