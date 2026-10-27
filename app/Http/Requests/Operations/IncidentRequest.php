<?php

namespace App\Http\Requests\Operations;

use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\ResponsibleParty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('incidents.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->route('incident') === null;

        return [
            'project_id' => $creating ? ['required', 'integer', 'exists:projects,id'] : ['prohibited'],
            'installation_order_id' => $creating ? ['nullable', 'integer', Rule::exists('installation_orders', 'id')->where('project_id', $this->input('project_id'))] : ['prohibited'],
            'fabrication_order_id' => $creating ? ['nullable', 'integer', Rule::exists('fabrication_orders', 'id')->where('project_id', $this->input('project_id'))] : ['prohibited'],
            'category' => ['required', Rule::enum(IncidentCategory::class)],
            'severity' => ['required', Rule::enum(IncidentSeverity::class)],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:5000'],
            'responsible_party' => ['nullable', Rule::enum(ResponsibleParty::class)],
            'responsible_supplier_id' => ['nullable', 'required_if:responsible_party,supplier', 'integer', Rule::exists('suppliers', 'id')],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'action_plan' => ['nullable', 'string', 'max:5000'],
            'cost' => ['nullable', 'string', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'due_on' => ['nullable', 'date'],
            'visible_to_client' => ['boolean'],
            'media_ids' => ['array', 'max:20'],
            'media_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'cost.regex' => 'Escribe un importe válido (máximo 2 decimales).',
            'responsible_supplier_id.required_if' => 'Elige el proveedor responsable.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('cost'))) {
            $this->merge(['cost' => str_replace([',', '$', ' '], '', $this->input('cost'))]);
        }
    }
}
