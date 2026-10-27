<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cobro con tarjeta: SOLO token de Openpay.js, device_session_id y la clave
 * del intento. Los datos de la tarjeta nunca llegan a este servidor.
 */
class ChargeCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'token_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'device_session_id' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
            'attempt_key' => ['required', 'uuid'],
        ];
    }
}
