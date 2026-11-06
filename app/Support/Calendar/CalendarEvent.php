<?php

namespace App\Support\Calendar;

use App\Models\Consultation;
use App\Models\SiteVisit;
use App\Notifications\Visits\VisitText;
use Carbon\CarbonInterface;

/**
 * Evento de calendario para "Agregar a mi calendario": archivo iCalendar
 * (.ics, RFC 5545, compatible con Google, Outlook y Apple) y enlace directo
 * a Google Calendar. Horas en UTC: cada calendario las muestra en la zona
 * del cliente.
 */
final readonly class CalendarEvent
{
    public function __construct(
        public string $uid,
        public CarbonInterface $start,
        public CarbonInterface $end,
        public string $title,
        public string $description,
        public ?string $location = null,
        public ?string $url = null,
        public bool $cancelled = false,
    ) {}

    /**
     * @param  bool  $withMeeting  El enlace de la videollamada solo se incluye con el pago confirmado.
     */
    public static function forConsultation(Consultation $consultation, string $manageUrl, bool $cancelled = false, ?bool $withMeeting = null): self
    {
        $withMeeting ??= $consultation->meeting_url !== null;
        $meeting = $withMeeting ? $consultation->meeting_url : null;

        $description = "Folio {$consultation->folio}. ".$consultation->channel->description()
            .($meeting ? "\nUnirse a la videollamada: {$meeting}" : '')
            ."\nReprogramar o cancelar: {$manageUrl}";

        return new self(
            uid: $consultation->uuid,
            start: $consultation->starts_at,
            end: $consultation->ends_at,
            title: 'Asesoría ML Habitat ('.$consultation->channel->label().')',
            description: $description,
            location: $meeting,
            url: $meeting,
            cancelled: $cancelled,
        );
    }

    public static function forVisit(SiteVisit $visit, ?string $detailsUrl = null): self
    {
        $visit->loadMissing(['address', 'technician']);

        $description = "Folio {$visit->folio}. Visita técnica de medición a domicilio."
            .($visit->technician !== null ? "\nTe visita: ".VisitText::firstName($visit->technician->name).', de ML Habitat.' : '')
            ."\nLlegamos dentro de la ventana indicada."
            .($detailsUrl !== null ? "\nDetalles: {$detailsUrl}" : '');

        return new self(
            uid: $visit->uuid,
            start: $visit->starts_at,
            end: $visit->ends_at,
            title: 'Visita técnica ML Habitat',
            description: $description,
            location: $visit->address->one_line,
            url: null,
        );
    }

    public function toIcs(): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//ML Habitat//Citas//ES',
            'CALSCALE:GREGORIAN',
            'METHOD:'.($this->cancelled ? 'CANCEL' : 'PUBLISH'),
            'BEGIN:VEVENT',
            'UID:'.$this->uid.'@mlhabitat',
            'SEQUENCE:'.($this->cancelled ? 1 : 0),
            'DTSTAMP:'.self::utc(now()),
            'DTSTART:'.self::utc($this->start),
            'DTEND:'.self::utc($this->end),
            'SUMMARY:'.self::escape($this->title),
            'DESCRIPTION:'.self::escape($this->description),
            'STATUS:'.($this->cancelled ? 'CANCELLED' : 'CONFIRMED'),
        ];
        if ($this->location !== null && $this->location !== '') {
            $lines[] = 'LOCATION:'.self::escape($this->location);
        }
        if ($this->url !== null && $this->url !== '') {
            $lines[] = 'URL:'.$this->url;
        }
        if (! $this->cancelled) {
            array_push($lines, 'BEGIN:VALARM', 'TRIGGER:-PT1H', 'ACTION:DISPLAY', 'DESCRIPTION:'.self::escape($this->title), 'END:VALARM');
        }
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    public function googleCalendarUrl(): string
    {
        return 'https://calendar.google.com/calendar/render?'.http_build_query(array_filter([
            'action' => 'TEMPLATE',
            'text' => $this->title,
            'dates' => self::utc($this->start).'/'.self::utc($this->end),
            'details' => $this->description,
            'location' => $this->location,
            'ctz' => 'America/Mexico_City',
        ]), '', '&', PHP_QUERY_RFC3986);
    }

    private static function utc(CarbonInterface $date): string
    {
        return $date->copy()->utc()->format('Ymd\THis\Z');
    }

    private static function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $value);
    }

    /** Líneas de máximo 75 octetos sin partir caracteres UTF-8. */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $out = '';
        while (strlen($line) > 75) {
            $cut = 75;
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $out .= substr($line, 0, $cut)."\r\n ";
            $line = substr($line, $cut);
        }

        return $out.$line;
    }
}
