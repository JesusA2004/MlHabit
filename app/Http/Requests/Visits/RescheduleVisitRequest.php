<?php

namespace App\Http\Requests\Visits;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'date_format:H:i'],
            'technician_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['date' => 'fecha', 'start' => 'horario', 'technician_id' => 'técnico'];
    }
}
