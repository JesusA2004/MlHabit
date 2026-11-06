<?php

namespace App\Http\Requests\Help;

use App\Models\UserTourState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTourStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(UserTourState::STATUSES)],
            'step' => ['nullable', 'integer', 'min:0', 'max:500'],
        ];
    }
}
