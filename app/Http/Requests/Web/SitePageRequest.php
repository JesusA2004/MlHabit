<?php

namespace App\Http\Requests\Web;

use App\Cms\Layout\PageTemplates;
use App\Models\SitePage;
use App\Services\Web\SitePages;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos de una página propia del sitio (no su contenido: ese se edita en
 * el editor visual). El slug no puede chocar con una ruta del sitio.
 */
class SitePageRequest extends FormRequest
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
        $page = $this->route('sitePage');
        $id = $page instanceof SitePage ? $page->id : null;

        return [
            'title' => ['required', 'string', 'max:160'],
            'slug' => [...Rules::slug('site_pages', $id, 120), function (string $attribute, mixed $value, Closure $fail) {
                if (is_string($value) && SitePages::isReserved($value)) {
                    $fail('Esa dirección ya la usa el sitio. Elige otra (por ejemplo, agrega una palabra más).');
                }
            }],
            'seo_title' => ['nullable', 'string', 'max:160'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'og_media_id' => Rules::publicImage(),
            'template' => [$id === null ? 'required' : 'prohibited', 'string', Rule::in(PageTemplates::ids())],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => 'título',
            'slug' => 'dirección (URL)',
            'seo_title' => 'título para buscadores',
            'seo_description' => 'descripción para buscadores',
            'og_media_id' => 'imagen para compartir',
            'template' => 'plantilla',
        ];
    }
}
