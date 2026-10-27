<?php

namespace App\Http\Requests\Web;

use App\Enums\FaqTopic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FaqRequest extends FormRequest
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
            'topic' => ['required', Rule::enum(FaqTopic::class)],
            'product_category_id' => ['nullable', 'integer', Rule::exists('product_categories', 'id')->whereNull('deleted_at')],
            'question' => ['required', 'string', 'max:300'],
            'answer' => ['required', 'string', 'max:4000'],
            'is_published' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['topic' => 'tema', 'question' => 'pregunta', 'answer' => 'respuesta', 'product_category_id' => 'categoría'];
    }
}
