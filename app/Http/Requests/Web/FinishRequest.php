<?php

namespace App\Http\Requests\Web;

use App\Models\Finish;
use Illuminate\Foundation\Http\FormRequest;

class FinishRequest extends FormRequest
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
        $finish = $this->route('finish');
        $id = $finish instanceof Finish ? $finish->id : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => Rules::slug('finishes', $id),
            'material' => ['nullable', 'string', 'max:120'],
            'color_hex' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'description' => ['nullable', 'string', 'max:1000'],
            'media_id' => Rules::publicImage(),
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nombre', 'color_hex' => 'color', 'media_id' => 'muestra'];
    }
}
