<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Respuesta del cliente a la evidencia de instalación. */
enum ClientConformity: string
{
    use HasOptions;

    case Conforming = 'conforming';
    case Pending = 'pending_items';

    public function label(): string
    {
        return match ($this) {
            self::Conforming => 'Todo conforme',
            self::Pending => 'Hay pendientes',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Conforming => 'success',
            self::Pending => 'warning',
        };
    }
}
