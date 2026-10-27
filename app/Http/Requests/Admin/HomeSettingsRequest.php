<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class HomeSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManagePosts() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hero.eyebrow' => ['required', 'string', 'max:50'],
            'hero.title_lines' => ['required', 'array', 'min:1', 'max:4'],
            'hero.title_lines.*' => ['string', 'max:50'],
            'hero.primary_cta_label' => ['nullable', 'string', 'max:50'],
            'hero.secondary_cta_label' => ['nullable', 'string', 'max:50'],
            'hero.tertiary_link_label' => ['nullable', 'string', 'max:50'],
            'hero.photo_media_id' => ['nullable', 'integer', 'exists:media,id'],

            'empieza_aqui.title' => ['required', 'string', 'max:100'],
            'tips_section.title' => ['required', 'string', 'max:100'],
            'tips_section.posts_per_page' => ['required', 'integer', 'min:3', 'max:24'],

            'historia_preview.title' => ['required', 'string', 'max:100'],
            'historia_preview.photo_media_id' => ['nullable', 'integer', 'exists:media,id'],

            'proceso_preview.title' => ['required', 'string', 'max:100'],

            'footer.tagline' => ['nullable', 'string', 'max:200'],

            'sections' => ['required', 'array'],
            'sections.*.section_key' => ['required', 'string'],
            'sections.*.is_visible' => ['boolean'],
            'sections.*.order' => ['integer', 'min:0'],
        ];
    }
}
