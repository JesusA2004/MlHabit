<?php

namespace App\Http\Requests\Projects;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Encabezado + partidas de cotización. Los importes llegan como texto
 * decimal y el backend recalcula todos los totales (QuoteCalculator).
 */
class QuoteRequest extends FormRequest
{
    private const MONEY = 'regex:/^\d{1,12}(\.\d{1,2})?$/';

    public function authorize(): bool
    {
        return $this->user()?->can('quotes.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items');
        if (! is_array($items)) {
            return;
        }

        $this->merge(['items' => array_map(function ($item) {
            if (! is_array($item)) {
                return $item;
            }
            foreach (['quantity', 'unit_price', 'discount_amount'] as $key) {
                if (isset($item[$key]) && (is_string($item[$key]) || is_numeric($item[$key]))) {
                    $item[$key] = str_replace([',', ' ', '$'], '', (string) $item[$key]);
                }
            }
            $item['discount_amount'] = ($item['discount_amount'] ?? '') === '' ? '0' : $item['discount_amount'];

            return $item;
        }, $items)]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'design_version_id' => ['nullable', 'integer', Rule::exists('design_versions', 'id')],
            'inclusions' => ['nullable', 'string', 'max:5000'],
            'exclusions' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:10000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.code' => ['nullable', 'string', 'max:40'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'regex:/^\d{1,9}(\.\d{1,3})?$/'],
            'items.*.unit' => ['required', 'string', 'max:24'],
            'items.*.unit_price' => ['required', self::MONEY],
            'items.*.discount_amount' => ['nullable', self::MONEY],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required' => 'Agrega al menos una partida.',
            'items.*.quantity.regex' => 'Usa una cantidad con hasta 3 decimales, p. ej. 2.5.',
            'items.*.unit_price.regex' => 'Escribe el precio con hasta 2 decimales, p. ej. 12500.00.',
            'items.*.discount_amount.regex' => 'Escribe el descuento con hasta 2 decimales.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => 'título', 'design_version_id' => 'versión de diseño', 'inclusions' => 'incluye',
            'exclusions' => 'no incluye', 'notes' => 'notas', 'terms' => 'condiciones',
            'items.*.description' => 'descripción', 'items.*.unit' => 'unidad', 'items.*.code' => 'clave',
        ];
    }
}
