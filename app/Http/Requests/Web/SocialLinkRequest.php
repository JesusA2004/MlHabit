<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SocialLinkRequest extends FormRequest
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
            'platform' => ['required', Rule::in(['instagram', 'facebook', 'tiktok', 'youtube', 'pinterest', 'whatsapp', 'linkedin', 'otro'])],
            'username' => ['nullable', 'string', 'max:100'],
            'url' => ['required', 'url', 'max:500', 'starts_with:https://'],
            'is_visible' => ['boolean'],
            'open_new_tab' => ['boolean'],
            'order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
