<?php

namespace App\Services\Crm\Meetings;

use App\Models\Consultation;

/**
 * Sin integración: el asesor captura el enlace de la reunión desde el
 * panel (/admin/asesorias/{uuid}). Conserva el enlace al reprogramar.
 */
class ManualMeetingProvider implements MeetingProvider
{
    public function key(): string
    {
        return 'manual';
    }

    public function create(Consultation $consultation): ?string
    {
        return null;
    }

    public function update(Consultation $consultation): ?string
    {
        return $consultation->meeting_url;
    }

    public function cancel(Consultation $consultation): void
    {
        //
    }
}
