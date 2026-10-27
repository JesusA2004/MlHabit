<?php

namespace App\Notifications\Crm;

use Carbon\CarbonInterface;

/** Fechas legibles en español de México para correos. */
final class When
{
    /** "martes 3 de noviembre de 2026, 10:00 a. m." */
    public static function long(CarbonInterface $date): string
    {
        return $date->copy()->locale('es_MX')->isoFormat('dddd D [de] MMMM [de] YYYY, h:mm a');
    }

    public static function time(CarbonInterface $date): string
    {
        return $date->copy()->locale('es_MX')->isoFormat('h:mm a');
    }
}
