<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Partida de instalación. */
enum InstallationItemStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Done = 'done';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Done => 'Terminada',
            self::Rejected => 'Extra rechazado',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::Done => 'success',
            self::Rejected => 'danger',
        };
    }
}
