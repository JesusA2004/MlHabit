<?php

namespace App\Enums;

/**
 * Estado editorial de productos del catálogo y proyectos de inspiración.
 * Solo `published` es visible en el sitio público.
 */
enum PublicationStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Published => 'Publicado',
            self::Archived => 'Archivado',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Published => 'success',
            self::Archived => 'neutral',
        };
    }

    /** @return list<array{value: string, label: string, tone: string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label(), 'tone' => $s->tone()], self::cases());
    }
}
