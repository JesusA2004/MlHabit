<?php

namespace App\Enums;

/** Presupuesto aproximado que declara el prospecto (orientativo, no es cotización). */
enum BudgetRange: string
{
    case Under50k = 'under_50k';
    case From50kTo100k = '50k_100k';
    case From100kTo200k = '100k_200k';
    case From200kTo400k = '200k_400k';
    case Over400k = 'over_400k';
    case Undecided = 'undecided';

    public function label(): string
    {
        return match ($this) {
            self::Under50k => 'Menos de $50,000',
            self::From50kTo100k => '$50,000 a $100,000',
            self::From100kTo200k => '$100,000 a $200,000',
            self::From200kTo400k => '$200,000 a $400,000',
            self::Over400k => 'Más de $400,000',
            self::Undecided => 'Aún no lo sé',
        };
    }
}
