<?php

namespace App\Payments\Openpay;

use App\Models\SiteSetting;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Credenciales de Openpay capturadas en /admin/openpay. Se guardan en
 * site_settings (clave `openpay_credentials`) como UN bloque cifrado con la
 * APP_KEY (Crypt::encryptString): en la base de datos y en la caché solo
 * existe texto cifrado. Nada de esto pasa por config(), así que es seguro
 * con `php artisan config:cache`.
 *
 * Precedencia (OpenpayConfig): valor guardado aquí → .env → vacío.
 * Un campo ausente o vacío aquí significa "usar el del .env".
 */
#[Scoped]
class OpenpayCredentialStore
{
    public const KEY = 'openpay_credentials';

    /** Campos de texto que se pueden capturar. */
    public const TEXT_FIELDS = ['merchant_id', 'public_key', 'private_key', 'webhook_user', 'webhook_password'];

    /** Campos secretos: nunca regresan al navegador. */
    public const SECRET_FIELDS = ['private_key', 'webhook_password'];

    private ?string $decodedFrom = null;

    /** @var array<string, mixed> */
    private array $decoded = [];

    private bool $unreadable = false;

    /** Lectura de site_settings memorizada durante la petición o el job (instancia con alcance). */
    private mixed $raw = null;

    private bool $loaded = false;

    /**
     * Valores guardados (descifrados). Se memoriza por texto cifrado: si otro
     * proceso cambia el valor, la siguiente lectura lo descifra de nuevo.
     *
     * @return array{merchant_id?: string, public_key?: string, private_key?: string, webhook_user?: string, webhook_password?: string, production?: bool|null, three_ds_mode?: string|null, updated_at?: string, updated_by?: int|null}
     */
    public function all(): array
    {
        $raw = $this->loaded ? $this->raw : SiteSetting::get(self::KEY);
        $this->raw = $raw;
        $this->loaded = true;
        $cipher = is_array($raw) && is_string($raw['payload'] ?? null) ? $raw['payload'] : null;

        if ($cipher === null) {
            $this->decodedFrom = null;
            $this->decoded = [];
            $this->unreadable = false;

            return [];
        }

        if ($cipher !== $this->decodedFrom) {
            $this->decodedFrom = $cipher;
            $this->unreadable = false;

            try {
                $data = json_decode(Crypt::decryptString($cipher), true, 8, JSON_THROW_ON_ERROR);
                $this->decoded = is_array($data) ? $data : [];
            } catch (DecryptException|\JsonException) {
                // APP_KEY distinta (p. ej. se regeneró): se ignora lo guardado y se usa el .env.
                Log::warning('openpay.credentials_unreadable');
                $this->decoded = [];
                $this->unreadable = true;
            }
        }

        /** @var array{merchant_id?: string, public_key?: string, private_key?: string, webhook_user?: string, webhook_password?: string, production?: bool|null, three_ds_mode?: string|null, updated_at?: string, updated_by?: int|null} */
        return $this->decoded;
    }

    /** true si hay un valor guardado que no se pudo descifrar (cambió la APP_KEY). */
    public function isUnreadable(): bool
    {
        $this->all();

        return $this->unreadable;
    }

    public function text(string $field): string
    {
        $value = $this->all()[$field] ?? null;

        return is_string($value) ? trim($value) : '';
    }

    public function production(): ?bool
    {
        $value = $this->all()['production'] ?? null;

        return is_bool($value) ? $value : null;
    }

    public function threeDsMode(): ?string
    {
        $value = $this->all()['three_ds_mode'] ?? null;

        return is_string($value) && in_array($value, OpenpayConfig::THREE_DS_MODES, true) ? $value : null;
    }

    /**
     * Guarda los valores dados (los demás se conservan). Un texto vacío borra
     * el campo (vuelve al .env); null en production/three_ds_mode también.
     *
     * @param  array<string, mixed>  $values
     */
    public function put(array $values, ?int $userId = null): void
    {
        $current = $this->all();

        foreach (self::TEXT_FIELDS as $field) {
            if (array_key_exists($field, $values)) {
                $value = trim((string) $values[$field]);
                if ($value === '') {
                    unset($current[$field]);
                } else {
                    $current[$field] = $value;
                }
            }
        }

        if (array_key_exists('production', $values)) {
            $current['production'] = $values['production'] === null ? null : (bool) $values['production'];
        }
        if (array_key_exists('three_ds_mode', $values)) {
            $mode = $values['three_ds_mode'];
            $current['three_ds_mode'] = is_string($mode) && in_array($mode, OpenpayConfig::THREE_DS_MODES, true) ? $mode : null;
        }

        $current['updated_at'] = now()->toIso8601String();
        $current['updated_by'] = $userId;

        SiteSetting::set(self::KEY, ['payload' => Crypt::encryptString((string) json_encode($current))]);
        $this->decodedFrom = null;
        $this->loaded = false;
    }

    /** Borra todo lo capturado en el panel: vuelve a usarse solo el .env. */
    public function forget(): void
    {
        SiteSetting::query()->where('key', self::KEY)->delete();
        // Misma clave de caché que SiteSetting::get().
        Cache::forget('site_settings.'.self::KEY);
        $this->decodedFrom = null;
        $this->loaded = false;
        $this->decoded = [];
        $this->unreadable = false;
    }

    public function hasAny(): bool
    {
        $all = $this->all();

        foreach (self::TEXT_FIELDS as $field) {
            if (is_string($all[$field] ?? null) && $all[$field] !== '') {
                return true;
            }
        }

        return is_bool($all['production'] ?? null) || is_string($all['three_ds_mode'] ?? null);
    }
}
