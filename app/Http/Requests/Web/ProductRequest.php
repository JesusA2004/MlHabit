<?php

namespace App\Http\Requests\Web;

use App\Enums\PublicationStatus;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
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
        $product = $this->route('product');
        $id = $product instanceof Product ? $product->id : null;

        return [
            'product_category_id' => ['required', 'integer', Rule::exists('product_categories', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:160'],
            'slug' => Rules::slug('products', $id, 180),
            'summary' => ['nullable', 'string', 'max:400'],
            'description' => ['nullable', 'string', 'max:20000'],
            ...Rules::editorialList('benefits'),
            ...Rules::editorialList('features'),
            'cover_media_id' => Rules::publicImage(),
            ...Rules::gallery(),
            'finish_ids' => ['nullable', 'array', 'max:60'],
            'finish_ids.*' => ['integer', 'distinct', 'exists:finishes,id'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:80'],
            'price_from' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
            'price_to' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2', 'gte:price_from'],
            'show_price' => ['boolean'],
            'status' => ['required', Rule::enum(PublicationStatus::class)],
            'is_featured' => ['boolean'],
            ...Rules::seo(),
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [
            function ($validator) {
                if ($this->boolean('show_price') && ($this->input('price_from') === null || $this->input('price_from') === '')) {
                    $validator->errors()->add('price_from', 'Para mostrar el precio, indica el precio “desde”.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'product_category_id' => 'categoría',
            'name' => 'nombre',
            'slug' => 'URL',
            'price_from' => 'precio desde',
            'price_to' => 'precio hasta',
            'gallery.*.id' => 'imagen',
            'benefits.*.title' => 'beneficio',
            'features.*.title' => 'característica',
        ];
    }
}
