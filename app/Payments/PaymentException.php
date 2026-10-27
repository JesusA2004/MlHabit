<?php

namespace App\Payments;

use RuntimeException;

/**
 * Error de negocio de pagos con mensaje SEGURO para el cliente (español,
 * sin datos del proveedor). `reason` permite a la UI decidir qué mostrar.
 */
class PaymentException extends RuntimeException
{
    public const ALREADY_PAID = 'already_paid';

    public const IN_PROGRESS = 'in_progress';

    public const NOT_PAYABLE = 'not_payable';

    public const INVALID_AMOUNT = 'invalid_amount';

    public const GATEWAY = 'gateway';

    public const NOT_CONFIGURED = 'not_configured';

    public const IDEMPOTENCY_MISMATCH = 'idempotency_mismatch';

    public function __construct(string $message, public readonly string $reason = self::GATEWAY)
    {
        parent::__construct($message);
    }
}
