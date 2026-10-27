<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class AddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('customer')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['postal_code' => preg_replace('/\D/', '', (string) $this->input('postal_code'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:60'],
            'street' => ['required', 'string', 'max:160'],
            'exterior_number' => ['required', 'string', 'max:20'],
            'interior_number' => ['nullable', 'string', 'max:20'],
            'neighborhood' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'digits:5'],
            'municipality' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:80'],
            'references' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'street' => 'calle',
            'exterior_number' => 'número exterior',
            'interior_number' => 'número interior',
            'neighborhood' => 'colonia',
            'postal_code' => 'código postal',
            'municipality' => 'municipio o alcaldía',
            'state' => 'estado',
            'references' => 'referencias',
        ];
    }
}
