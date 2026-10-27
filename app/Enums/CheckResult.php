<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Resultado de un criterio del checklist. */
enum CheckResult: string
{
    use HasOptions;

    case Pass = 'pass';
    case Fail = 'fail';
    case NotApplicable = 'na';

    public function label(): string
    {
        return match ($this) {
            self::Pass => 'Cumple',
            self::Fail => 'No cumple',
            self::NotApplicable => 'No aplica',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pass => 'success',
            self::Fail => 'danger',
            self::NotApplicable => 'neutral',
        };
    }
}
