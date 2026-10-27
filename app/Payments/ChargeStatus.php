<?php

namespace App\Payments;

/** Estado normalizado de una transacción del proveedor (independiente de Openpay). */
enum ChargeStatus: string
{
    /** completed */
    case Completed = 'completed';
    /** charge_pending / in_progress (p. ej. esperando 3D Secure). */
    case Pending = 'pending';
    /** Transacción con status failed. */
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case ChargebackPending = 'chargeback_pending';
    case ChargebackAccepted = 'chargeback_accepted';
    case ChargebackAdjustment = 'chargeback_adjustment';
    /** El API rechazó el cargo con un objeto Error (4xx): tarjeta declinada, datos inválidos… */
    case Declined = 'declined';
    /** 1006: ya existe una transacción con ese order_id. */
    case DuplicateOrder = 'duplicate_order';
    /** La consulta no encontró la transacción. */
    case NotFound = 'not_found';
    /** Sin respuesta concluyente (timeout, red, 5xx): el cargo pudo o no crearse. */
    case Unknown = 'unknown';

    public function isChargeback(): bool
    {
        return in_array($this, [self::ChargebackPending, self::ChargebackAccepted, self::ChargebackAdjustment], true);
    }

    /** Rechazo definitivo del cargo. */
    public function isFailure(): bool
    {
        return in_array($this, [self::Failed, self::Cancelled, self::Declined], true);
    }
}
