<?php

namespace App\Support\Crm;

use App\Models\Consultation;
use Carbon\CarbonInterface;

/**
 * Archivo iCalendar (.ics) mínimo y compatible (RFC 5545) para que el
 * cliente agregue su asesoría a Google Calendar, Outlook o Apple.
 */
final class Ics
{
    public static function forConsultation(Consultation $consultation, string $manageUrl, bool $cancelled = false): string
    {
        $summary = 'Asesoría ML Habitat ('.$consultation->channel->label().')';
        $description = "Folio {$consultation->folio}. ".$consultation->channel->description()
            .($consultation->meeting_url ? "\nEnlace: {$consultation->meeting_url}" : '')
            ."\nReprogramar o cancelar: {$manageUrl}";

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//ML Habitat//Asesorias//ES',
            'CALSCALE:GREGORIAN',
            'METHOD:'.($cancelled ? 'CANCEL' : 'PUBLISH'),
            'BEGIN:VEVENT',
            'UID:'.$consultation->uuid.'@mlhabitat',
            'SEQUENCE:'.($cancelled ? 1 : 0),
            'DTSTAMP:'.self::utc(now()),
            'DTSTART:'.self::utc($consultation->starts_at),
            'DTEND:'.self::utc($consultation->ends_at),
            'SUMMARY:'.self::escape($summary),
            'DESCRIPTION:'.self::escape($description),
            'STATUS:'.($cancelled ? 'CANCELLED' : 'CONFIRMED'),
        ];
        if ($consultation->meeting_url) {
            $lines[] = 'URL:'.self::escape($consultation->meeting_url);
        }
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    private static function utc(CarbonInterface $date): string
    {
        return $date->copy()->utc()->format('Ymd\THis\Z');
    }

    private static function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $value);
    }

    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $out = '';
        while (strlen($line) > 75) {
            $cut = 75;
            // No partir caracteres multibyte UTF-8.
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $out .= substr($line, 0, $cut)."\r\n ";
            $line = substr($line, $cut);
        }

        return $out.$line;
    }
}
