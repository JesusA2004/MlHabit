<?php

namespace App\Http\Requests\Visits;

use Illuminate\Foundation\Http\FormRequest;

class CancelVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:200'],
            'refund' => ['boolean'],
            'refund_amount' => ['nullable', 'required_if_accepted:refund', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'idempotency_key' => ['nullable', 'required_if_accepted:refund', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['reason' => 'motivo', 'refund_amount' => 'monto a reembolsar'];
    }
}
