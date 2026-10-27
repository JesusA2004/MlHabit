<?php

namespace App\Enums;

/** Resultado de una asesoría. */
enum ConsultationOutcome: string
{
    case InterestedVisit = 'interested_visit';
    case Prequalification = 'prequalification';
    case NeedsFollowUp = 'needs_follow_up';
    case NotInterested = 'not_interested';
    case OutOfCoverage = 'out_of_coverage';

    public function label(): string
    {
        return match ($this) {
            self::InterestedVisit => 'Interesado: agendar visita técnica',
            self::Prequalification => 'Interesado: iniciar precalificación',
            self::NeedsFollowUp => 'Requiere seguimiento',
            self::NotInterested => 'No interesado',
            self::OutOfCoverage => 'Fuera de cobertura',
        };
    }
}
