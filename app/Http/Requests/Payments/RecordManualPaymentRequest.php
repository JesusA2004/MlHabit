<?php

namespace App\Http\Requests\Payments;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::in(array_map(fn (PaymentMethod $m) => $m->value, PaymentMethod::manualCases()))],
            'reference' => ['required', 'string', 'min:3', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['method' => 'método', 'reference' => 'referencia'];
    }
}
