<?php

namespace App\Http\Requests\Crm;

use App\Enums\EmploymentType;
use App\Enums\IncomeRange;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Información de precalificación que envía el cliente (enlace firmado). */
class SubmitPrequalificationRequest extends FormRequest
{
    public const TERMS = [3, 6, 9, 12, 18, 24];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $amount = $this->input('requested_amount');
        if (is_string($amount)) {
            $this->merge(['requested_amount' => str_replace([',', '$', ' '], '', $amount)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'requested_amount' => ['nullable', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'preferred_term_months' => ['nullable', 'integer', Rule::in(self::TERMS)],
            'income_range' => ['required', Rule::enum(IncomeRange::class)],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'applicant_notes' => ['nullable', 'string', 'max:2000'],
            'documents' => ['nullable', 'array', 'max:8'],
            'documents.*' => ['file', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:10240'],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'requested_amount.regex' => 'Escribe el monto solo con números, por ejemplo 85000.',
            'income_range.required' => 'Elige un rango de ingreso mensual.',
            'employment_type.required' => 'Elige tu tipo de ocupación.',
            'documents.max' => 'Puedes adjuntar hasta 8 documentos.',
            'documents.*.mimetypes' => 'Los documentos deben ser PDF, JPG o PNG.',
            'documents.*.max' => 'Cada documento puede pesar hasta 10 MB.',
            'consent.accepted' => 'Confirma que entiendes que esto es una precalificación y no una aprobación.',
        ];
    }
}
