<?php

namespace App\Http\Requests\Web;

use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;

class PageRequest extends FormRequest
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
        $page = $this->route('page');
        $id = $page instanceof Page ? $page->id : null;

        return [
            'title' => ['required', 'string', 'max:160'],
            'slug' => Rules::slug('pages', $id),
            'summary' => ['nullable', 'string', 'max:320'],
            'body' => ['required', 'string', 'max:200000'],
            'show_in_footer' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['title' => 'título', 'slug' => 'URL', 'summary' => 'resumen', 'body' => 'contenido'];
    }
}
