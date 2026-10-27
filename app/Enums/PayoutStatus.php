<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Pago a proveedor (quien ejecuta no autoriza). */
enum PayoutStatus: string
{
    use HasOptions;

    case PendingAuthorization = 'pending_authorization';
    case Authorized = 'authorized';
    case Paid = 'paid';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PendingAuthorization => 'Por autorizar',
            self::Authorized => 'Autorizado',
            self::Paid => 'Pagado',
            self::Rejected => 'Rechazado',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::PendingAuthorization => 'warning',
            self::Authorized => 'info',
            self::Paid => 'success',
            self::Rejected => 'danger',
        };
    }
}
