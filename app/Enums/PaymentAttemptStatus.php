<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Resultado de un intento de cobro con tarjeta (payment_attempts.status). */
enum PaymentAttemptStatus: string
{
    use HasOptions;

    /** Petición enviada (o por enviar) a la pasarela. */
    case Sending = 'sending';
    case Approved = 'approved';
    /** Requiere autenticación 3D Secure con el banco. */
    case RequiresAction = 'requires_action';
    case Declined = 'declined';
    /** Sin respuesta concluyente (timeout/5xx): se concilia antes de reintentar. */
    case Unknown = 'unknown';
    /** Se dio por terminado sin cobro (no registrado en Openpay o 3DS abandonado). */
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Sending => 'Enviado',
            self::Approved => 'Aprobado',
            self::RequiresAction => 'Autenticación bancaria',
            self::Declined => 'Rechazado',
            self::Unknown => 'Por confirmar',
            self::Expired => 'Sin completar',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Sending, self::RequiresAction => 'info',
            self::Approved => 'success',
            self::Declined => 'danger',
            self::Unknown => 'warning',
            self::Expired => 'neutral',
        };
    }

    /** Intento cuyo resultado todavía puede cambiar. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Sending, self::RequiresAction, self::Unknown], true);
    }
}
