<?php

namespace App\Payments\Openpay;

/**
 * Limpia payloads del proveedor antes de guardarlos o registrarlos:
 * enmascara número de tarjeta (solo últimos 4), elimina titular, CVV,
 * direcciones, tokens/source_id, device_session_id y datos de contacto.
 */
final class OpenpayRedactor
{
    private const DROP_KEYS = [
        'cvv2', 'cvv', 'source_id', 'device_session_id', 'holder_name', 'address', 'phone_number',
        'email', 'private_key', 'api_key', 'password', 'user', 'token', 'http_context',
    ];

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::DROP_KEYS, true)) {
                $data[$key] = '[oculto]';

                continue;
            }

            if (is_string($key) && strtolower($key) === 'card_number' && is_scalar($value)) {
                $digits = preg_replace('/\D/', '', (string) $value) ?? '';
                $data[$key] = $digits === '' ? '[oculto]' : '••••'.substr($digits, -4);

                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::redact($value);
            } elseif (is_string($value) && preg_match('/\b\d{13,19}\b/', $value)) {
                $data[$key] = preg_replace('/\b\d{13,19}\b/', '[oculto]', $value);
            }
        }

        return $data;
    }
}
