<?php

namespace App\Payments;

use App\Support\Money;

/** Datos para crear un cargo con token. No contiene datos de tarjeta: solo el token de un solo uso. */
final readonly class ChargeRequest
{
    /**
     * @param  array{name: string, last_name?: string|null, email: string, phone_number?: string|null}  $customer
     */
    public function __construct(
        public string $orderId,
        public string $tokenId,
        public string $deviceSessionId,
        public Money $amount,
        public string $currency,
        public string $description,
        public array $customer,
        public string $customerIp,
        public bool $use3ds = false,
        public ?string $redirectUrl = null,
    ) {}
}
