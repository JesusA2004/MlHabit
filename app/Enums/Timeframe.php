<?php

namespace App\Enums;

/** ¿Para cuándo quiere su proyecto el prospecto? */
enum Timeframe: string
{
    case Asap = 'asap';
    case OneToThreeMonths = 'one_three_months';
    case ThreeToSixMonths = 'three_six_months';
    case Exploring = 'exploring';

    public function label(): string
    {
        return match ($this) {
            self::Asap => 'Lo antes posible',
            self::OneToThreeMonths => 'En 1 a 3 meses',
            self::ThreeToSixMonths => 'En 3 a 6 meses',
            self::Exploring => 'Solo estoy explorando',
        };
    }
}
