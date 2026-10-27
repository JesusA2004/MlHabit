<?php

namespace App\Http\Requests\Visits;

use Illuminate\Foundation\Http\FormRequest;

/** Reserva pública de visita (paso "Continuar al pago"). */
class BookVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'lead_token' => ['nullable', 'string', 'max:2000'],
            ...self::contactRules(),
            'customer_notes' => ['nullable', 'string', 'max:1000'],
            'terms' => ['accepted'],
        ];
    }

    /**
     * Reglas compartidas con el alta desde el panel.
     *
     * @return array<string, mixed>
     */
    public static function contactRules(): array
    {
        return [
            'postal_code' => ['required', 'digits:5'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'date_format:H:i'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['required', 'string', 'regex:/^[\d\s()+-]{10,20}$/'],
            'street' => ['required', 'string', 'max:160'],
            'exterior_number' => ['required', 'string', 'max:20'],
            'interior_number' => ['nullable', 'string', 'max:20'],
            'neighborhood' => ['required', 'string', 'max:120'],
            'municipality' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:80'],
            'references' => ['nullable', 'string', 'max:500'],
            'access_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return self::labels();
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            'postal_code' => 'código postal',
            'date' => 'fecha',
            'start' => 'horario',
            'first_name' => 'nombre',
            'last_name' => 'apellidos',
            'email' => 'correo electrónico',
            'phone' => 'teléfono',
            'street' => 'calle',
            'exterior_number' => 'número exterior',
            'interior_number' => 'número interior',
            'neighborhood' => 'colonia',
            'municipality' => 'municipio o alcaldía',
            'state' => 'estado',
            'references' => 'referencias',
            'access_notes' => 'indicaciones de acceso',
            'terms' => 'aceptación de condiciones',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'terms.accepted' => 'Para continuar acepta el alcance y las condiciones de la visita.',
            'phone.regex' => 'Escribe un teléfono de 10 dígitos.',
            'postal_code.digits' => 'El código postal tiene 5 dígitos.',
        ];
    }
}
