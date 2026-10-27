<?php

namespace App\Http\Requests\Operations;

use App\Enums\SupplierStatus;
use App\Enums\SupplierType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('suppliers.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'rfc' => ['nullable', 'string', 'min:12', 'max:13', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/i'],
            'type' => ['required', Rule::enum(SupplierType::class)],
            'status' => ['required', Rule::enum(SupplierStatus::class)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'capabilities' => ['nullable', 'string', 'max:2000'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'zone_ids' => ['array'],
            'zone_ids.*' => ['integer', 'exists:coverage_zones,id'],
        ];
    }

    public function messages(): array
    {
        return ['rfc.regex' => 'El RFC no tiene un formato válido.'];
    }

    /** @return array<string, mixed> */
    public function supplierData(): array
    {
        $data = collect($this->validated())->except('zone_ids')->all();
        if (! empty($data['rfc'])) {
            $data['rfc'] = mb_strtoupper($data['rfc']);
        }

        return $data;
    }

    /** @return list<int> */
    public function zoneIds(): array
    {
        return array_values(array_map('intval', $this->validated('zone_ids', [])));
    }
}
