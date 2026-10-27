<?php

namespace App\Http\Requests\Crm;

use App\Enums\BudgetRange;
use App\Enums\PaymentPreference;
use App\Enums\PreferredContact;
use App\Enums\Timeframe;
use App\Services\Crm\CustomerMatcher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Formulario público /solicitar. Las fotos se validan por su MIME real
 * (contenido, no extensión): solo JPG, PNG o WebP de hasta 10 MB.
 */
class SubmitLeadRequest extends FormRequest
{
    public const MAX_PHOTOS = 6;

    public const MAX_PHOTO_KB = 10240;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => CustomerMatcher::normalizePhone($this->input('phone')),
            'email' => CustomerMatcher::normalizeEmail($this->input('email')),
            'postal_code' => preg_replace('/\D/', '', (string) $this->input('postal_code')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'submission_key' => ['required', 'uuid'],
            'category_ids' => ['required', 'array', 'min:1', 'max:10'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists('product_categories', 'id')->whereNull('deleted_at')],
            'postal_code' => ['required', 'digits:5'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:80'],
            'budget_range' => ['nullable', Rule::enum(BudgetRange::class)],
            'payment_preference' => ['nullable', Rule::enum(PaymentPreference::class)],
            'timeframe' => ['nullable', Rule::enum(Timeframe::class)],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['required', 'digits:10'],
            'email' => ['nullable', 'required_if:preferred_contact,email', 'email', 'max:255'],
            'preferred_contact' => ['required', Rule::enum(PreferredContact::class)],
            'photos' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.self::MAX_PHOTO_KB],
            'measurements_note' => ['nullable', 'string', 'max:1000'],
            'comments' => ['nullable', 'string', 'max:2000'],
            'privacy' => ['accepted'],
            'utm_source' => ['nullable', 'string', 'max:120'],
            'utm_medium' => ['nullable', 'string', 'max:120'],
            'utm_campaign' => ['nullable', 'string', 'max:160'],
            'utm_term' => ['nullable', 'string', 'max:160'],
            'utm_content' => ['nullable', 'string', 'max:160'],
            'referrer' => ['nullable', 'string', 'max:500'],
            'landing_url' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_ids.required' => 'Elige al menos un espacio que quieras renovar.',
            'category_ids.min' => 'Elige al menos un espacio que quieras renovar.',
            'postal_code.required' => 'Escribe tu código postal.',
            'postal_code.digits' => 'El código postal tiene 5 dígitos.',
            'first_name.required' => 'Escribe tu nombre.',
            'phone.required' => 'Escribe un teléfono para contactarte.',
            'phone.digits' => 'Escribe tu teléfono a 10 dígitos, sin lada internacional.',
            'email.required_if' => 'Escribe tu correo para contactarte por ese medio.',
            'email.email' => 'Revisa tu correo; parece incompleto.',
            'preferred_contact.required' => 'Elige cómo prefieres que te contactemos.',
            'photos.max' => 'Puedes adjuntar hasta '.self::MAX_PHOTOS.' fotos.',
            'photos.*.mimetypes' => 'Las fotos deben ser JPG, PNG o WebP.',
            'photos.*.max' => 'Cada foto puede pesar hasta 10 MB.',
            'photos.*.file' => 'No pudimos leer una de las fotos. Intenta de nuevo.',
            'privacy.accepted' => 'Para continuar, acepta el aviso de privacidad.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'nombre',
            'last_name' => 'apellidos',
            'phone' => 'teléfono',
            'email' => 'correo',
            'postal_code' => 'código postal',
            'city' => 'ciudad',
            'state' => 'estado',
        ];
    }
}
