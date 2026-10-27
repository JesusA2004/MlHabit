<?php

namespace App\Notifications\Visits;

use App\Models\SiteVisit;
use Carbon\CarbonInterface;

/** Textos de fecha/hora en español de México para correos de visitas. */
final class VisitText
{
    private const DAYS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

    private const MONTHS = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    public static function date(CarbonInterface $date): string
    {
        return self::DAYS[$date->dayOfWeek].' '.$date->day.' de '.self::MONTHS[$date->month].' de '.$date->year;
    }

    public static function when(SiteVisit $visit): string
    {
        return self::date($visit->starts_at).', entre '.$visit->starts_at->format('H:i').' y '.$visit->ends_at->format('H:i').' h';
    }

    /** Ruta interna (path + query) a partir de una URL absoluta, para el centro de notificaciones. */
    public static function path(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
        $query = parse_url($url, PHP_URL_QUERY);

        return $path.(is_string($query) && $query !== '' ? '?'.$query : '');
    }

    /** Solo el nombre de pila (privacidad del técnico). */
    public static function firstName(?string $name): string
    {
        $first = trim(explode(' ', trim((string) $name))[0]);

        return $first !== '' ? $first : 'un técnico';
    }
}
