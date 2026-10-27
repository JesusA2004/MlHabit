<?php

namespace App\Payments;

/** Resultado de la prueba de conexión del diagnóstico de Openpay. */
final readonly class ConnectivityResult
{
    public function __construct(
        public bool $ok,
        public string $message,
        public ?int $httpStatus = null,
        public ?string $errorCode = null,
        public ?int $latencyMs = null,
    ) {}

    /** @return array{ok: bool, message: string, httpStatus: int|null, errorCode: string|null, latencyMs: int|null} */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'message' => $this->message,
            'httpStatus' => $this->httpStatus,
            'errorCode' => $this->errorCode,
            'latencyMs' => $this->latencyMs,
        ];
    }
}
