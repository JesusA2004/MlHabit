<?php

namespace App\Payments\Openpay;

/**
 * Lectura centralizada de config('services.openpay'). Expone solo lo que
 * cada capa necesita: el frontend recibe jsConfig() (merchant, llave
 * pública y modo sandbox), nunca la llave privada.
 */
class OpenpayConfig
{
    public const SANDBOX_API = 'https://sandbox-api.openpay.mx';

    public const PRODUCTION_API = 'https://api.openpay.mx';

    public const THREE_DS_MODES = ['always', 'auto', 'never'];

    public function merchantId(): string
    {
        return trim((string) config('services.openpay.merchant_id'));
    }

    public function publicKey(): string
    {
        return trim((string) config('services.openpay.public_key'));
    }

    public function privateKey(): string
    {
        return trim((string) config('services.openpay.private_key'));
    }

    public function isProduction(): bool
    {
        return (bool) config('services.openpay.production', false);
    }

    public function isConfigured(): bool
    {
        return $this->merchantId() !== '' && $this->privateKey() !== '' && $this->publicKey() !== '';
    }

    /** URI base con merchant: https://sandbox-api.openpay.mx/v1/{MERCHANT_ID} */
    public function apiBase(): string
    {
        return ($this->isProduction() ? self::PRODUCTION_API : self::SANDBOX_API).'/v1/'.rawurlencode($this->merchantId());
    }

    public function threeDsMode(): string
    {
        $mode = strtolower((string) config('services.openpay.3ds_mode', 'auto'));

        return in_array($mode, self::THREE_DS_MODES, true) ? $mode : 'auto';
    }

    public function timeout(): int
    {
        return max(5, (int) config('services.openpay.timeout', 30));
    }

    public function connectTimeout(): int
    {
        return max(2, (int) config('services.openpay.connect_timeout', 10));
    }

    public function unknownGraceSeconds(): int
    {
        return max(30, (int) config('services.openpay.unknown_grace_seconds', 120));
    }

    public function pending3dsMinutes(): int
    {
        return max(5, (int) config('services.openpay.pending_3ds_minutes', 30));
    }

    public function webhookUser(): string
    {
        return (string) config('services.openpay.webhook_user');
    }

    public function webhookPassword(): string
    {
        return (string) config('services.openpay.webhook_password');
    }

    public function webhookConfigured(): bool
    {
        return $this->webhookUser() !== '' && $this->webhookPassword() !== '';
    }

    /**
     * Lo ÚNICO que se comparte con el navegador.
     *
     * @return array{merchantId: string, publicKey: string, sandbox: bool, configured: bool}
     */
    public function jsConfig(): array
    {
        return [
            'merchantId' => $this->merchantId(),
            'publicKey' => $this->publicKey(),
            'sandbox' => ! $this->isProduction(),
            'configured' => $this->isConfigured(),
        ];
    }

    /** Últimos 4 caracteres del merchant id para el diagnóstico (nunca el valor completo). */
    public function maskedMerchantId(): ?string
    {
        $id = $this->merchantId();

        return $id === '' ? null : '••••'.substr($id, -4);
    }
}
