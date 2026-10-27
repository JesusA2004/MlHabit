<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PageEntryRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'entry_date' => ['nullable', 'date'],
            'body' => ['nullable', 'array'],
            'cover_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'is_visible' => ['boolean'],
            'order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
