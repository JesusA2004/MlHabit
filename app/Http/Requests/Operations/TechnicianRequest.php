<?php

namespace App\Http\Requests\Operations;

use App\Enums\TechnicianStatus;
use App\Models\TechnicianProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TechnicianRequest extends FormRequest
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
        /** @var TechnicianProfile|null $profile */
        $profile = $this->route('technicianProfile');
        $creating = $profile === null;

        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => $creating ? ['required', 'email', 'max:255', Rule::unique('users', 'email')] : ['prohibited'],
            'phone' => ['nullable', 'string', 'max:20'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'status' => ['required', Rule::enum(TechnicianStatus::class)],
            'does_visits' => ['required', 'boolean'],
            'does_installations' => ['required', 'boolean'],
            'skills' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            'zone_ids' => ['array'],
            'zone_ids.*' => ['integer', 'exists:coverage_zones,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Ya existe una cuenta con este correo.',
            'email.prohibited' => 'El correo de acceso no se cambia desde aquí.',
        ];
    }

    /** @return list<int> */
    public function zoneIds(): array
    {
        return array_values(array_map('intval', $this->validated('zone_ids', [])));
    }
}
