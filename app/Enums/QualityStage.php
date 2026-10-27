<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Etapa de inspección: autoevaluación del instalador o revisión interna. */
enum QualityStage: string
{
    use HasOptions;

    case Installer = 'installer';
    case Quality = 'quality';

    public function label(): string
    {
        return match ($this) {
            self::Installer => 'Autoevaluación del instalador',
            self::Quality => 'Revisión de calidad',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Installer => 'neutral',
            self::Quality => 'info',
        };
    }
}
