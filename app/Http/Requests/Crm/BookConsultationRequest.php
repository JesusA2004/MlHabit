<?php

namespace App\Http\Requests\Crm;

use App\Enums\ConsultationChannel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Reservar o reprogramar una asesoría (público con enlace firmado o panel). */
class BookConsultationRequest extends FormRequest
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
            'starts_at' => ['required', 'date'],
            'channel' => [$this->isMethod('POST') && ! $this->routeIs('*.reprogramar') ? 'required' : 'nullable', Rule::enum(ConsultationChannel::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'advisor_id' => ['nullable', 'integer', 'exists:users,id'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'starts_at.required' => 'Elige un horario.',
            'starts_at.date' => 'Elige un horario válido.',
            'channel.required' => 'Elige cómo prefieres tu asesoría.',
            'email.email' => 'Revisa tu correo; parece incompleto.',
        ];
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->validated('starts_at'))
            ->setTimezone((string) config('app.timezone'))
            ->startOfMinute();
    }

    public function channel(): ?ConsultationChannel
    {
        $value = $this->validated('channel');

        return is_string($value) ? ConsultationChannel::from($value) : null;
    }
}
