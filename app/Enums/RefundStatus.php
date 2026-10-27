<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RefundStatus: string
{
    use HasOptions;

    /** Solicitado; la pasarela aún no lo confirma (p. ej. tiempo de espera agotado). */
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Por confirmar',
            self::Completed => 'Completado',
            self::Failed => 'Rechazado',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }
}
