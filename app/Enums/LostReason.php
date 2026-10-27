<?php

namespace App\Enums;

/** Motivo por el que se perdió un prospecto. */
enum LostReason: string
{
    case Price = 'price';
    case Competitor = 'competitor';
    case NoResponse = 'no_response';
    case OutOfCoverage = 'out_of_coverage';
    case Timing = 'timing';
    case NotQualified = 'not_qualified';
    case Duplicate = 'duplicate';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Price => 'Precio fuera de su presupuesto',
            self::Competitor => 'Eligió otra empresa',
            self::NoResponse => 'Dejó de responder',
            self::OutOfCoverage => 'Fuera de zona de cobertura',
            self::Timing => 'Pospuso el proyecto',
            self::NotQualified => 'No era un proyecto para nosotros',
            self::Duplicate => 'Solicitud duplicada',
            self::Other => 'Otro motivo',
        };
    }
}
