<?php

namespace App\Payments;

use App\Support\Money;

/**
 * Resultado normalizado de crear/consultar un cargo. Solo contiene datos
 * seguros: marca y últimos 4 dígitos, nunca el número completo ni el CVV.
 * `safeMessage` está listo para mostrarse al cliente en español.
 */
final readonly class ChargeResult
{
    public function __construct(
        public ChargeStatus $status,
        public ?string $transactionId = null,
        public ?string $orderId = null,
        public ?string $authorization = null,
        public ?string $redirectUrl = null,
        public ?string $cardBrand = null,
        public ?string $last4 = null,
        public ?string $errorCode = null,
        public ?string $safeMessage = null,
        public ?Money $amount = null,
        public ?Money $fee = null,
        public ?Money $refundedAmount = null,
        public ?string $refundId = null,
        public ?int $httpStatus = null,
    ) {}

    public static function unknown(?int $httpStatus = null, ?string $errorCode = null): self
    {
        return new self(
            status: ChargeStatus::Unknown,
            errorCode: $errorCode,
            safeMessage: 'Estamos confirmando tu pago con el banco. No vuelvas a intentarlo todavía.',
            httpStatus: $httpStatus,
        );
    }

    public static function notFound(): self
    {
        return new self(status: ChargeStatus::NotFound);
    }

    public function requiresRedirect(): bool
    {
        return $this->status === ChargeStatus::Pending && $this->redirectUrl !== null;
    }

    /**
     * Datos seguros para bitácoras/eventos (sin token, sin tarjeta completa).
     *
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return array_filter([
            'status' => $this->status->value,
            'transaction_id' => $this->transactionId,
            'order_id' => $this->orderId,
            'authorization' => $this->authorization,
            'card_brand' => $this->cardBrand,
            'card_last4' => $this->last4,
            'error_code' => $this->errorCode,
            'amount' => $this->amount?->amount,
            'fee' => $this->fee?->amount,
            'refunded_amount' => $this->refundedAmount?->amount,
            'http_status' => $this->httpStatus,
            'three_ds' => $this->redirectUrl !== null ? true : null,
        ], fn ($v) => $v !== null);
    }
}
