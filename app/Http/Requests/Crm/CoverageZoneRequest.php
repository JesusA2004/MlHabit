<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class CoverageZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('coverage.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:80'],
            'municipality' => ['nullable', 'string', 'max:120'],
            'is_active' => ['boolean'],
            'visits_enabled' => ['boolean'],
            'center_lat' => ['nullable', 'required_with:radius_km', 'numeric', 'between:-90,90'],
            'center_lng' => ['nullable', 'required_with:radius_km', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:0.5', 'max:500'],
            'travel_buffer_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'max_visits_per_day' => ['nullable', 'integer', 'min:1', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'center_lat.required_with' => 'Para usar radio indica la latitud del punto base.',
            'center_lng.required_with' => 'Para usar radio indica la longitud del punto base.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'state' => 'estado',
            'municipality' => 'municipio',
            'travel_buffer_minutes' => 'tiempo de traslado',
            'max_visits_per_day' => 'visitas por día',
            'radius_km' => 'radio',
        ];
    }
}
