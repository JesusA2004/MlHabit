<?php

namespace App\Enums;

/** Estado de una versión de medición. Una versión enviada nunca se edita. */
enum MeasurementStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Submitted => 'Enviada',
            self::Superseded => 'Reemplazada',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Submitted => 'success',
            self::Superseded => 'neutral',
        };
    }

    /** @return array{value: string, label: string, tone: string} */
    public function toArray(): array
    {
        return ['value' => $this->value, 'label' => $this->label(), 'tone' => $this->tone()];
    }
}
