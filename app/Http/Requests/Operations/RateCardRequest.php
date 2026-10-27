<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RateCardRequest extends FormRequest
{
    private const MONEY = 'regex:/^\d{1,12}(\.\d{1,2})?$/';

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('rate_cards.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->route('rateCard') === null;

        return [
            'name' => $creating ? ['required', 'string', 'max:160'] : ['prohibited'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'effective_from' => ['nullable', 'date'],
            'mobilization_fee' => ['required', 'string', self::MONEY],
            'minimum_charge' => ['required', 'string', self::MONEY],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['array', 'max:300'],
            'items.*.code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/'],
            'items.*.description' => ['required', 'string', 'max:300'],
            'items.*.unit' => ['required', 'string', 'max:24'],
            'items.*.unit_price' => ['required', 'string', self::MONEY],
        ];
    }

    public function messages(): array
    {
        return [
            'mobilization_fee.regex' => 'Escribe un importe válido (máximo 2 decimales).',
            'minimum_charge.regex' => 'Escribe un importe válido (máximo 2 decimales).',
            'items.*.unit_price.regex' => 'Escribe un precio válido (máximo 2 decimales).',
            'items.*.code.regex' => 'La clave solo admite letras, números, punto, guion y guion bajo.',
            'name.prohibited' => 'El nombre no cambia entre versiones.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($v) => is_string($v) ? str_replace([',', '$', ' '], '', $v) : $v;

        $this->merge([
            'mobilization_fee' => $clean($this->input('mobilization_fee', '0')) ?: '0',
            'minimum_charge' => $clean($this->input('minimum_charge', '0')) ?: '0',
            'items' => collect((array) $this->input('items', []))->map(fn ($i) => is_array($i) ? [...$i, 'unit_price' => $clean($i['unit_price'] ?? null)] : $i)->all(),
        ]);
    }
}
