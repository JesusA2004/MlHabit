<?php

namespace App\Services\Crm\Meetings;

use App\Models\Consultation;
use Illuminate\Support\Str;

/**
 * Videollamada automática con Jitsi Meet (meet.jit.si, gratuito y sin
 * cuenta para el cliente). Genera una sala con nombre aleatorio de 24
 * caracteres: imposible de adivinar, única por asesoría.
 *
 * Al reprogramar se conserva la misma sala (el enlace del correo anterior
 * sigue sirviendo). El enlace solo se muestra al cliente con el pago
 * confirmado (ConsultationPaymentHandler lo crea en ese momento).
 *
 * Nota: meet.jit.si pide al PRIMER participante (el asesor) iniciar sesión
 * con Google, GitHub o Facebook para abrir la sala; el cliente entra sin cuenta.
 */
class JitsiMeetingProvider implements MeetingProvider
{
    public const BASE_URL = 'https://meet.jit.si/';

    public const ROOM_PREFIX = 'MLHabitat-';

    public function key(): string
    {
        return 'jitsi';
    }

    public function create(Consultation $consultation): ?string
    {
        return self::BASE_URL.self::ROOM_PREFIX.Str::random(24);
    }

    public function update(Consultation $consultation): ?string
    {
        return $consultation->meeting_url ?? $this->create($consultation);
    }

    public function cancel(Consultation $consultation): void
    {
        // Las salas de Jitsi no se reservan: no hay nada que cancelar.
    }

    public static function isJitsiUrl(?string $url): bool
    {
        return is_string($url) && str_starts_with($url, self::BASE_URL.self::ROOM_PREFIX);
    }
}
