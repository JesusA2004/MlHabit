<?php

namespace App\Http\Requests\Projects;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Alta manual (desde cliente) y edición de datos de gestión del proyecto. */
class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('projects.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'customer_uuid' => [Rule::requiredIf($creating), 'nullable', 'uuid', Rule::exists('customers', 'uuid')->whereNull('deleted_at')],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:160'],
            'address_id' => ['sometimes', 'nullable', 'integer', Rule::exists('addresses', 'id')],
            'product_category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('product_categories', 'id')],
            'advisor_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'designer_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'operations_owner_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'target_install_date' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'customer_uuid' => 'cliente', 'name' => 'nombre', 'address_id' => 'domicilio', 'product_category_id' => 'categoría',
            'advisor_id' => 'asesor', 'designer_id' => 'diseñador', 'operations_owner_id' => 'responsable de operación',
            'target_install_date' => 'fecha objetivo de instalación',
        ];
    }
}
