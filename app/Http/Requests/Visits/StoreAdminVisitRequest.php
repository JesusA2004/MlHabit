<?php

namespace App\Http\Requests\Visits;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Alta de visita desde el panel: enviar enlace de pago o registrar pago manual. */
class StoreAdminVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $manual = $this->input('payment_action') === 'manual';

        return [
            'idempotency_key' => ['required', 'uuid'],
            'lead' => ['nullable', 'uuid'],
            ...BookVisitRequest::contactRules(),
            'technician_id' => ['nullable', 'integer', 'exists:users,id'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
            'payment_action' => ['required', Rule::in(['link', 'manual'])],
            'method' => [Rule::requiredIf($manual), 'nullable', Rule::in(array_map(fn (PaymentMethod $m) => $m->value, PaymentMethod::manualCases()))],
            'reference' => [Rule::requiredIf($manual), 'nullable', 'string', 'min:3', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return BookVisitRequest::labels() + ['technician_id' => 'técnico', 'method' => 'método', 'reference' => 'referencia'];
    }
}
