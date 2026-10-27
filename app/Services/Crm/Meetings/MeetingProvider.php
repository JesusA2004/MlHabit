<?php

namespace App\Services\Crm\Meetings;

use App\Models\Consultation;
use Illuminate\Container\Attributes\Bind;

/**
 * Proveedor de videollamadas para asesorías. Hoy el asesor pega el enlace
 * a mano (ManualMeetingProvider); para integrar una plataforma (Google
 * Meet, Zoom, Teams…) basta con otra implementación y cambiar el binding.
 */
#[Bind(ManualMeetingProvider::class)]
interface MeetingProvider
{
    /** Identificador corto que se guarda en consultations.meeting_provider. */
    public function key(): string;

    /** Crea (o reserva) la reunión al agendar. Devuelve la URL o null si se captura después. */
    public function create(Consultation $consultation): ?string;

    /** Actualiza la reunión al reprogramar. */
    public function update(Consultation $consultation): ?string;

    /** Cancela la reunión en la plataforma, si aplica. */
    public function cancel(Consultation $consultation): void;
}
