<?php

namespace App\Http\Requests\Crm;

use App\Enums\BudgetRange;
use App\Enums\LeadSource;
use App\Enums\PaymentPreference;
use App\Enums\PreferredContact;
use App\Enums\Timeframe;
use App\Models\Lead;
use App\Services\Crm\CustomerMatcher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Alta manual de un prospecto desde el panel (llamada, sala, recomendación…). */
class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Lead::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => CustomerMatcher::normalizePhone($this->input('phone')),
            'email' => CustomerMatcher::normalizeEmail($this->input('email')),
            'postal_code' => preg_replace('/\D/', '', (string) $this->input('postal_code')) ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'required_without:email', 'digits:10'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:255'],
            'preferred_contact' => ['nullable', Rule::enum(PreferredContact::class)],
            'source' => ['required', Rule::enum(LeadSource::class)],
            'category_ids' => ['nullable', 'array', 'max:10'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists('product_categories', 'id')->whereNull('deleted_at')],
            'postal_code' => ['nullable', 'digits:5'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:80'],
            'budget_range' => ['nullable', Rule::enum(BudgetRange::class)],
            'payment_preference' => ['nullable', Rule::enum(PaymentPreference::class)],
            'timeframe' => ['nullable', Rule::enum(Timeframe::class)],
            'comments' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required_without' => 'Captura al menos un teléfono o un correo.',
            'email.required_without' => 'Captura al menos un teléfono o un correo.',
            'phone.digits' => 'El teléfono va a 10 dígitos.',
        ];
    }
}
