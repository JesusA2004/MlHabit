<?php

namespace App\Services\Crm\Meetings;

use App\Models\Consultation;
use Illuminate\Container\Attributes\Bind;

/**
 * Proveedor de videollamadas para asesorías. ConfiguredMeetingProvider
 * elige según Configuración → Asesorías: Jitsi automático (por defecto) o
 * manual (el asesor pega el enlace). Para otra plataforma (Google Meet,
 * Zoom, Teams…) basta otra implementación y agregarla a la opción.
 */
#[Bind(ConfiguredMeetingProvider::class)]
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
