<?php

namespace App\Http\Requests\Projects;

use App\Enums\MeasurementKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Borrador de medición (encabezado + renglones en milímetros enteros). */
class MeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('measurements.execute') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'space_type' => ['nullable', 'string', 'max:64'],
            'access_notes' => ['nullable', 'string', 'max:2000'],
            'existing_installations' => ['nullable', 'string', 'max:2000'],
            'general_notes' => ['nullable', 'string', 'max:4000'],
            'items' => ['present', 'array', 'max:300'],
            'items.*.area' => ['required', 'string', 'max:80'],
            'items.*.kind' => ['required', Rule::enum(MeasurementKind::class)],
            'items.*.label' => ['nullable', 'string', 'max:160'],
            'items.*.value_mm' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.*.value_mm.integer' => 'Escribe la medida en milímetros enteros.',
            'items.*.value_mm.max' => 'La medida parece demasiado grande (máximo 100 m).',
            'items.*.area.required' => 'Indica el área (p. ej. Muro A).',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'space_type' => 'tipo de espacio', 'access_notes' => 'accesos', 'existing_installations' => 'instalaciones existentes',
            'general_notes' => 'notas', 'items.*.kind' => 'tipo de medida', 'items.*.label' => 'descripción', 'items.*.value_mm' => 'medida',
        ];
    }
}
