<?php

namespace App\Http\Requests\Payments;

use App\Payments\Openpay\OpenpayConfig;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Credenciales de Openpay capturadas en /admin/openpay.
 *
 * Reglas de captura:
 *  - merchant_id, public_key, webhook_user: el valor enviado reemplaza al
 *    guardado; vacío = usar el del .env.
 *  - private_key, webhook_password (secretos): solo se envían cuando el
 *    usuario pulsa "Reemplazar". Si no vienen, se conserva lo guardado.
 *    `clear_secrets` permite quitarlos del panel (vuelven al .env).
 */
class UpdateOpenpayCredentialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('openpay.diagnostics') && $user->can('settings.manage');
    }

    protected function prepareForValidation(): void
    {
        foreach (['merchant_id', 'public_key', 'private_key', 'webhook_user', 'webhook_password'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                // Se toleran espacios y saltos de línea al pegar.
                $this->merge([$field => trim($value)]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'merchant_id' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9]{10,40}$/'],
            'public_key' => ['nullable', 'string', 'max:80', 'regex:/^pk_[A-Za-z0-9]{8,76}$/'],
            'private_key' => ['sometimes', 'nullable', 'string', 'max:80', 'regex:/^sk_[A-Za-z0-9]{8,76}$/'],
            'webhook_user' => ['nullable', 'string', 'min:6', 'max:80', 'regex:/^[A-Za-z0-9._@-]+$/'],
            'webhook_password' => ['sometimes', 'nullable', 'string', 'min:16', 'max:120'],
            'production' => ['required', 'boolean'],
            'three_ds_mode' => ['required', 'string', 'in:'.implode(',', OpenpayConfig::THREE_DS_MODES)],
            'clear_secrets' => ['sometimes', 'array'],
            'clear_secrets.*' => ['string', 'in:private_key,webhook_password'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'merchant_id.regex' => 'El ID de comercio solo tiene letras y números (lo ves en el dashboard de Openpay, arriba a la derecha o en "Mi cuenta").',
            'public_key.regex' => 'La llave pública empieza con pk_ seguida de letras y números.',
            'private_key.regex' => 'La llave privada empieza con sk_ seguida de letras y números. Revisa que no hayas pegado la pública.',
            'webhook_user.regex' => 'Usa solo letras, números, punto, guion, guion bajo o @.',
            'webhook_user.min' => 'El usuario del webhook debe tener al menos 6 caracteres.',
            'webhook_password.min' => 'Usa una contraseña de al menos 16 caracteres (puedes generarla con el botón).',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'merchant_id' => 'ID de comercio',
            'public_key' => 'llave pública',
            'private_key' => 'llave privada',
            'webhook_user' => 'usuario del webhook',
            'webhook_password' => 'contraseña del webhook',
            'production' => 'ambiente',
            'three_ds_mode' => 'modo 3D Secure',
        ];
    }

    /**
     * Valores listos para OpenpayCredentialStore::put().
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        $data = $this->validated();

        $values = [
            'merchant_id' => (string) ($data['merchant_id'] ?? ''),
            'public_key' => (string) ($data['public_key'] ?? ''),
            'webhook_user' => (string) ($data['webhook_user'] ?? ''),
            'production' => (bool) $data['production'],
            'three_ds_mode' => (string) $data['three_ds_mode'],
        ];

        // Secreto enviado y no vacío → se reemplaza; si no, se conserva.
        foreach (['private_key', 'webhook_password'] as $secret) {
            if (is_string($data[$secret] ?? null) && $data[$secret] !== '') {
                $values[$secret] = $data[$secret];
            }
        }

        foreach ((array) ($data['clear_secrets'] ?? []) as $secret) {
            if (! isset($values[$secret])) {
                $values[$secret] = '';
            }
        }

        return $values;
    }
}
