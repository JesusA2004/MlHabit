<?php

namespace App\Http\Requests\Visits;

use App\Enums\VisitCreditType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVisitPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $creditable = $this->boolean('is_creditable');

        return [
            // Toda visita se cobra: la tarifa nunca puede ser cero.
            'fee_amount' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/', 'numeric', 'min:1'],
            'is_creditable' => ['required', 'boolean'],
            'credit_type' => [Rule::requiredIf($creditable), 'nullable', Rule::enum(VisitCreditType::class)],
            'credit_value' => [Rule::requiredIf($creditable), 'nullable', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'credit_validity_days' => [Rule::requiredIf($creditable), 'nullable', 'integer', 'min:1', 'max:730'],
            'credit_conditions' => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'buffer_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'hold_minutes' => ['required', 'integer', 'min:5', 'max:120'],
            'min_notice_hours' => ['required', 'integer', 'min:0', 'max:336'],
            'booking_window_days' => ['required', 'integer', 'min:1', 'max:120'],
            'scope' => ['required', 'string', 'min:10', 'max:5000'],
            'cancellation_policy' => ['required', 'string', 'min:10', 'max:5000'],
            'reschedule_policy' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('credit_type') === VisitCreditType::Percent->value && is_numeric($this->input('credit_value')) && bccomp((string) $this->input('credit_value'), '100', 2) > 0) {
                    $validator->errors()->add('credit_value', 'El porcentaje no puede ser mayor a 100.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fee_amount.min' => 'Toda visita técnica se cobra: la tarifa debe ser de al menos $1.00 MXN.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'fee_amount' => 'tarifa',
            'credit_type' => 'tipo de crédito',
            'credit_value' => 'valor del crédito',
            'credit_validity_days' => 'vigencia del crédito',
            'duration_minutes' => 'duración',
            'buffer_minutes' => 'margen entre visitas',
            'hold_minutes' => 'minutos de apartado',
            'min_notice_hours' => 'anticipación mínima',
            'booking_window_days' => 'ventana de reserva',
            'scope' => 'alcance',
            'cancellation_policy' => 'política de cancelación',
            'reschedule_policy' => 'política de reprogramación',
        ];
    }
}
