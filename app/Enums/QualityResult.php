<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Resultado de una inspección. */
enum QualityResult: string
{
    use HasOptions;

    case Pending = 'pending';
    case Approved = 'approved';
    case RequiresFixes = 'requires_fixes';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Approved => 'Aprobada',
            self::RequiresFixes => 'Requiere correcciones',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::Approved => 'success',
            self::RequiresFixes => 'danger',
        };
    }
}
