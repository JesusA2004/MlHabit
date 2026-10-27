<?php

namespace App\Http\Requests\Web;

use App\Cms\Field;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ajustes del sitio por grupo (`brand`, `contact`, `seo`, `footer`).
 */
class SiteSettingsRequest extends FormRequest
{
    public const GROUPS = ['brand', 'contact', 'seo', 'footer'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return match ($this->route('group')) {
            'brand' => [
                'tagline' => ['nullable', 'string', 'max:160'],
                'logo_media_id' => ['nullable', 'integer', Field::publicImageRule()],
                'logo_dark_media_id' => ['nullable', 'integer', Field::publicImageRule()],
                'favicon_media_id' => ['nullable', 'integer', Field::publicImageRule()],
                'og_media_id' => ['nullable', 'integer', Field::publicImageRule()],
            ],
            'contact' => [
                'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\s.-]{7,30}$/'],
                'whatsapp' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\s.-]{10,30}$/'],
                'email' => ['nullable', 'email', 'max:160'],
                'address' => ['nullable', 'string', 'max:300'],
                'hours' => ['nullable', 'string', 'max:200'],
            ],
            'seo' => [
                'title' => ['required', 'string', 'max:120'],
                'description' => ['required', 'string', 'max:320'],
                'twitter_handle' => ['nullable', 'string', 'max:40', 'regex:/^@?[A-Za-z0-9_]{1,30}$/'],
            ],
            'footer' => [
                'text' => ['nullable', 'string', 'max:400'],
                'legal_name' => ['nullable', 'string', 'max:160'],
            ],
            default => [],
        };
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'tagline' => 'frase de marca',
            'phone' => 'teléfono',
            'whatsapp' => 'WhatsApp',
            'email' => 'correo',
            'address' => 'dirección',
            'hours' => 'horario',
            'title' => 'título',
            'description' => 'descripción',
            'twitter_handle' => 'usuario de X',
            'text' => 'texto',
            'legal_name' => 'razón social',
        ];
    }
}
