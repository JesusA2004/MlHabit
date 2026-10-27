<?php

namespace App\Payments;

/** Resultado de solicitar un reembolso a la pasarela. */
final readonly class RefundResult
{
    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    /** Sin respuesta concluyente: queda pendiente y se concilia después. */
    public const UNKNOWN = 'unknown';

    public function __construct(
        public string $outcome,
        public ?string $refundId = null,
        public ?string $errorCode = null,
        public ?string $safeMessage = null,
        public ?int $httpStatus = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->outcome === self::COMPLETED;
    }
}
