<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AppearanceRequest extends FormRequest
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
        $hex = ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];

        return [
            'colors.background' => $hex,
            'colors.foreground' => $hex,
            'colors.accent' => $hex,
            'colors.muted' => $hex,

            'fonts.display' => ['required', 'string', 'in:Fraunces,Playfair Display,Cormorant Garamond'],
            'fonts.body' => ['required', 'string', 'in:Instrument Sans,Inter,Work Sans'],

            'buttons.style' => ['required', 'string', 'in:solid,outline'],
            'buttons.radius' => ['required', 'string', 'in:none,sm,md,lg,full'],

            'cards.radius' => ['required', 'string', 'in:none,sm,md,lg,xl'],

            'logo.logo_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'logo.mark_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'logo.favicon_media_id' => ['nullable', 'integer', 'exists:media,id'],

            'site.name' => ['required', 'string', 'max:60'],
            'site.subtitle' => ['nullable', 'string', 'max:80'],
        ];
    }
}
