<?php

namespace App\Payments\Openpay;

/**
 * Lectura centralizada de las credenciales de Openpay. Expone solo lo que
 * cada capa necesita: el frontend recibe jsConfig() (merchant, llave
 * pública y modo sandbox), nunca la llave privada.
 *
 * Origen de cada valor (precedencia): lo capturado en /admin/openpay
 * (OpenpayCredentialStore, cifrado en la base de datos) → config
 * ('services.openpay', que lee el .env) → vacío. Compatible con
 * `config:cache`: la parte de base de datos no pasa por config().
 */
class OpenpayConfig
{
    public const SANDBOX_API = 'https://sandbox-api.openpay.mx';

    public const PRODUCTION_API = 'https://api.openpay.mx';

    public const THREE_DS_MODES = ['always', 'auto', 'never'];

    public const SOURCE_PANEL = 'panel';

    public const SOURCE_ENV = 'env';

    public function __construct(private readonly OpenpayCredentialStore $store) {}

    public function merchantId(): string
    {
        return $this->text('merchant_id');
    }

    public function publicKey(): string
    {
        return $this->text('public_key');
    }

    public function privateKey(): string
    {
        return $this->text('private_key');
    }

    public function isProduction(): bool
    {
        return $this->store->production() ?? (bool) config('services.openpay.production', false);
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
        $mode = $this->store->threeDsMode() ?? strtolower((string) config('services.openpay.3ds_mode', 'auto'));

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
        return $this->text('webhook_user');
    }

    public function webhookPassword(): string
    {
        return $this->text('webhook_password');
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
        return self::mask($this->merchantId());
    }

    /**
     * De dónde sale el valor efectivo de un campo: 'panel', 'env' o null (vacío).
     */
    public function source(string $field): ?string
    {
        if ($field === 'production') {
            return $this->store->production() !== null ? self::SOURCE_PANEL : self::SOURCE_ENV;
        }
        if ($field === 'three_ds_mode') {
            return $this->store->threeDsMode() !== null ? self::SOURCE_PANEL : self::SOURCE_ENV;
        }

        return match (true) {
            $this->store->text($field) !== '' => self::SOURCE_PANEL,
            trim((string) config('services.openpay.'.$field)) !== '' => self::SOURCE_ENV,
            default => null,
        };
    }

    /**
     * Huella (no reversible) de las credenciales activas: sirve para saber si
     * la última prueba de conexión corresponde a lo configurado hoy.
     */
    public function fingerprint(): string
    {
        $material = implode('|', [$this->merchantId(), $this->privateKey(), $this->publicKey(), $this->isProduction() ? 'production' : 'sandbox']);

        return substr(hash_hmac('sha256', $material, (string) config('app.key')), 0, 16);
    }

    /** "••••1234" con los últimos 4 caracteres, o null si está vacío. */
    public static function mask(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : '••••'.mb_substr($value, -4);
    }

    private function text(string $field): string
    {
        $stored = $this->store->text($field);

        return $stored !== '' ? $stored : trim((string) config('services.openpay.'.$field));
    }
}
