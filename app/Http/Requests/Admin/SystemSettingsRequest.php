<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validación por grupo de /admin/configuracion/{group}. Los porcentajes y
 * montos llegan como texto decimal y se normalizan con bcmath (nunca float).
 *
 * - commercial.tax_rate se captura como porcentaje (16) y se guarda como
 *   fracción con 4 decimales ("0.1600").
 */
class SystemSettingsRequest extends FormRequest
{
    public const CHANNELS = ['video', 'phone', 'whatsapp'];

    private const DECIMAL = 'regex:/^\d{1,9}(\.\d{1,2})?$/';

    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return match ($this->route('group')) {
            'commercial' => [
                'deposit_percent' => ['required', 'string', self::DECIMAL, 'numeric', 'min:0', 'max:100'],
                'tax_rate' => ['required', 'string', self::DECIMAL, 'numeric', 'min:0', 'max:100'],
                'prices_include_tax' => ['required', 'boolean'],
                'quote_validity_days' => ['required', 'integer', 'min:1', 'max:365'],
            ],
            'consultation' => [
                'enabled' => ['required', 'boolean'],
                'duration_minutes' => ['required', 'integer', 'min:10', 'max:240'],
                'buffer_minutes' => ['required', 'integer', 'min:0', 'max:120'],
                'price' => ['required', 'string', self::DECIMAL, 'numeric', 'min:0', 'max:100000'],
                'booking_window_days' => ['required', 'integer', 'min:1', 'max:120'],
                'min_notice_hours' => ['required', 'integer', 'min:0', 'max:168'],
                'channels' => ['present', 'array'],
                'channels.*' => ['string', 'distinct', 'in:'.implode(',', self::CHANNELS)],
            ],
            'leads' => [
                'follow_up_hours' => ['required', 'integer', 'min:1', 'max:336'],
            ],
            'security' => [
                'require_2fa_for_staff' => ['required', 'boolean'],
            ],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        foreach (['deposit_percent', 'tax_rate', 'price'] as $field) {
            $value = $this->input($field);
            if (is_int($value) || is_float($value)) {
                $this->merge([$field => (string) $value]);
            } elseif (is_string($value)) {
                $this->merge([$field => trim(str_replace([',', '$', '%', ' '], '', $value))]);
            }
        }
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $group = $this->route('group');

            if ($group === 'consultation' && $this->boolean('enabled') && count((array) $this->input('channels', [])) === 0) {
                $validator->errors()->add('channels', 'Elige al menos un canal para las asesorías.');
            }

            /** @var User|null $user */
            $user = $this->user();
            if ($group === 'security' && $this->boolean('require_2fa_for_staff') && $user?->two_factor_confirmed_at === null) {
                $validator->errors()->add('require_2fa_for_staff', 'Activa primero tu propia verificación en dos pasos (Cuenta → Seguridad) para no perder el acceso.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'deposit_percent' => 'porcentaje de anticipo',
            'tax_rate' => 'tasa de IVA',
            'prices_include_tax' => 'precios con IVA',
            'quote_validity_days' => 'vigencia de cotizaciones',
            'enabled' => 'asesorías activas',
            'duration_minutes' => 'duración',
            'buffer_minutes' => 'margen entre citas',
            'price' => 'precio',
            'booking_window_days' => 'ventana de reserva',
            'min_notice_hours' => 'anticipación mínima',
            'channels' => 'canales',
            'follow_up_hours' => 'horas para el primer contacto',
            'require_2fa_for_staff' => 'verificación en dos pasos obligatoria',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'deposit_percent.regex' => 'Escribe un porcentaje con máximo dos decimales.',
            'tax_rate.regex' => 'Escribe un porcentaje con máximo dos decimales.',
            'price.regex' => 'Escribe un monto con máximo dos decimales.',
        ];
    }

    /**
     * Valores normalizados listos para Settings::put().
     *
     * @return array<string, mixed>
     */
    public function normalized(): array
    {
        $data = $this->validated();

        return match ($this->route('group')) {
            'commercial' => [
                'deposit_percent' => Money::of((string) $data['deposit_percent'])->amount,
                'tax_rate' => bcdiv((string) $data['tax_rate'], '100', 4),
                'prices_include_tax' => (bool) $data['prices_include_tax'],
                'quote_validity_days' => (int) $data['quote_validity_days'],
            ],
            'consultation' => [
                'enabled' => (bool) $data['enabled'],
                'duration_minutes' => (int) $data['duration_minutes'],
                'buffer_minutes' => (int) $data['buffer_minutes'],
                'price' => Money::of((string) $data['price'])->amount,
                'booking_window_days' => (int) $data['booking_window_days'],
                'min_notice_hours' => (int) $data['min_notice_hours'],
                'channels' => array_values(array_intersect(self::CHANNELS, (array) $data['channels'])),
            ],
            'leads' => [
                'follow_up_hours' => (int) $data['follow_up_hours'],
            ],
            'security' => [
                'require_2fa_for_staff' => (bool) $data['require_2fa_for_staff'],
            ],
            default => [],
        };
    }
}
