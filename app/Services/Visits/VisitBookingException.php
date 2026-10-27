<?php

namespace App\Services\Visits;

use RuntimeException;

/** No se puede reservar (sin política activa, sin cobertura…). Mensaje seguro para el cliente. */
class VisitBookingException extends RuntimeException
{
    public const NO_POLICY = 'no_policy';

    public const NO_COVERAGE = 'no_coverage';

    public const INVALID = 'invalid';

    public function __construct(string $message, public readonly string $reason = self::INVALID)
    {
        parent::__construct($message);
    }
}
