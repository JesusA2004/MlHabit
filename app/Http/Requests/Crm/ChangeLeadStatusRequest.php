<?php

namespace App\Http\Requests\Crm;

use App\Enums\LeadStatus;
use App\Enums\LostReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeLeadStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('lead')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(LeadStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
            'lost_reason' => ['nullable', 'required_if:status,lost', Rule::enum(LostReason::class)],
            'lost_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['lost_reason.required_if' => 'Indica por qué se perdió el prospecto.'];
    }
}
