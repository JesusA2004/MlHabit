<?php

namespace App\Http\Requests\Web;

use App\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;

class ProductCategoryRequest extends FormRequest
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
        $category = $this->route('category');
        $id = $category instanceof ProductCategory ? $category->id : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => Rules::slug('product_categories', $id),
            'tagline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cover_media_id' => Rules::publicImage(),
            'is_published' => ['boolean'],
            'is_featured' => ['boolean'],
            ...Rules::seo(),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nombre', 'slug' => 'URL', 'cover_media_id' => 'portada'];
    }
}
