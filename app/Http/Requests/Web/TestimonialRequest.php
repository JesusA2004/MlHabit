<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Un testimonio solo puede publicarse con fecha de consentimiento. La
 * regla también se aplica en el modelo (Testimonial::saving).
 */
class TestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'author_name' => ['required', 'string', 'max:120'],
            'author_context' => ['nullable', 'string', 'max:160'],
            'quote' => ['required', 'string', 'max:1200'],
            'showcase_project_id' => ['nullable', 'integer', Rule::exists('showcase_projects', 'id')->whereNull('deleted_at')],
            'media_id' => Rules::publicImage(),
            'consent_obtained_at' => ['nullable', 'date', 'before_or_equal:today'],
            'consent_note' => ['nullable', 'string', 'max:255'],
            'is_published' => ['boolean'],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [
            function ($validator) {
                if ($this->boolean('is_published') && ! $this->filled('consent_obtained_at')) {
                    $validator->errors()->add('consent_obtained_at', 'Registra la fecha en que el cliente autorizó publicar su testimonio.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'author_name' => 'nombre',
            'author_context' => 'contexto',
            'quote' => 'testimonio',
            'consent_obtained_at' => 'fecha de consentimiento',
            'consent_note' => 'nota de consentimiento',
        ];
    }
}
