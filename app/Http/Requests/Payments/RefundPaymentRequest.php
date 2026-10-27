<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class RefundPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'reason' => ['required', 'string', 'min:5', 'max:300'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['amount' => 'monto', 'reason' => 'motivo'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['amount.regex' => 'Escribe un monto válido con hasta dos decimales.'];
    }
}
