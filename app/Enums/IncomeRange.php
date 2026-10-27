<?php

namespace App\Enums;

/** Rango de ingreso mensual declarado en la precalificación. */
enum IncomeRange: string
{
    case Under15k = 'under_15k';
    case From15kTo30k = '15k_30k';
    case From30kTo60k = '30k_60k';
    case From60kTo100k = '60k_100k';
    case Over100k = 'over_100k';
    case PreferNot = 'prefer_not';

    public function label(): string
    {
        return match ($this) {
            self::Under15k => 'Menos de $15,000',
            self::From15kTo30k => '$15,000 a $30,000',
            self::From30kTo60k => '$30,000 a $60,000',
            self::From60kTo100k => '$60,000 a $100,000',
            self::Over100k => 'Más de $100,000',
            self::PreferNot => 'Prefiero no decirlo',
        };
    }
}
