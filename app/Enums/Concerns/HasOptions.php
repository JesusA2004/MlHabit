<?php

namespace App\Enums\Concerns;

/**
 * Serializa un enum con label()/tone() para el frontend (selectores,
 * chips). Usado por los enums de operaciones.
 */
trait HasOptions
{
    /**
     * @return list<array{value: string, label: string, tone: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => $case->toArray(), self::cases());
    }

    /**
     * @return array{value: string, label: string, tone: string}
     */
    public function toArray(): array
    {
        return ['value' => $this->value, 'label' => $this->label(), 'tone' => $this->tone()];
    }
}
