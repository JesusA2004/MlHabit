<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Criterios del checklist de instalación. */
enum QualityCriterion: string
{
    use HasOptions;

    case Pieces = 'pieces';
    case Finish = 'finish';
    case Stability = 'stability';
    case Anchors = 'anchors';
    case Leveling = 'leveling';
    case Alignment = 'alignment';
    case Doors = 'doors';
    case Drawers = 'drawers';
    case Hardware = 'hardware';
    case Damage = 'damage';
    case Cleaning = 'cleaning';

    public function label(): string
    {
        return match ($this) {
            self::Pieces => 'Piezas completas',
            self::Finish => 'Acabados',
            self::Stability => 'Estabilidad',
            self::Anchors => 'Anclajes',
            self::Leveling => 'Nivelación',
            self::Alignment => 'Alineación',
            self::Doors => 'Puertas',
            self::Drawers => 'Cajones',
            self::Hardware => 'Herrajes',
            self::Damage => 'Sin daños',
            self::Cleaning => 'Limpieza',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pieces, self::Finish, self::Stability, self::Anchors, self::Leveling, self::Alignment, self::Doors, self::Drawers, self::Hardware, self::Damage, self::Cleaning => 'neutral',
        };
    }
}
