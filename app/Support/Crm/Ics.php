<?php

namespace App\Support\Crm;

use App\Models\Consultation;
use App\Support\Calendar\CalendarEvent;

/**
 * Archivo iCalendar (.ics) de una asesoría para Google Calendar, Outlook o
 * Apple. La construcción vive en App\Support\Calendar\CalendarEvent
 * (compartida con las visitas técnicas).
 */
final class Ics
{
    public static function forConsultation(Consultation $consultation, string $manageUrl, bool $cancelled = false): string
    {
        return CalendarEvent::forConsultation($consultation, $manageUrl, $cancelled)->toIcs();
    }
}
