<?php

namespace App\Http\Requests\Web;

use App\Enums\PublicationStatus;
use App\Enums\VisualOrigin;
use App\Models\ShowcaseProject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowcaseProjectRequest extends FormRequest
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
        $project = $this->route('showcase');
        $id = $project instanceof ShowcaseProject ? $project->id : null;

        return [
            'product_category_id' => ['nullable', 'integer', Rule::exists('product_categories', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:160'],
            'slug' => Rules::slug('showcase_projects', $id, 180),
            'summary' => ['nullable', 'string', 'max:400'],
            'story' => ['nullable', 'string', 'max:20000'],
            'location_label' => ['nullable', 'string', 'max:120'],
            'origin' => ['required', Rule::enum(VisualOrigin::class)],
            'cover_media_id' => Rules::publicImage(),
            ...Rules::gallery(),
            'status' => ['required', Rule::enum(PublicationStatus::class)],
            'is_featured' => ['boolean'],
            ...Rules::seo(),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => 'título',
            'slug' => 'URL',
            'origin' => 'origen',
            'location_label' => 'ubicación',
            'gallery.*.id' => 'imagen',
        ];
    }
}
