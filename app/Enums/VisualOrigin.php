<?php

namespace App\Enums;

/**
 * Origen de una imagen o proyecto de inspiración. El sitio público SIEMPRE
 * etiqueta la diferencia: un render es una propuesta conceptual, no un
 * trabajo entregado.
 */
enum VisualOrigin: string
{
    case Render = 'render';
    case RealPhoto = 'real_photo';

    public function label(): string
    {
        return match ($this) {
            self::Render => 'Render conceptual',
            self::RealPhoto => 'Proyecto real',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Render => 'info',
            self::RealPhoto => 'success',
        };
    }

    /** @return list<array{value: string, label: string, tone: string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label(), 'tone' => $s->tone()], self::cases());
    }
}
