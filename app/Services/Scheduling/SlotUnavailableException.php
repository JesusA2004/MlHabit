<?php

namespace App\Services\Scheduling;

use RuntimeException;

/** El horario solicitado ya no está disponible (ocupado, bloqueado o fuera de agenda). */
class SlotUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'Ese horario acaba de ocuparse. Elige otro, por favor.')
    {
        parent::__construct($message);
    }
}
