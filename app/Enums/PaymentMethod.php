<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PaymentMethod: string
{
    use HasOptions;

    case Card = 'card';
    case Transfer = 'transfer';
    case Cash = 'cash';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Card => 'Tarjeta',
            self::Transfer => 'Transferencia',
            self::Cash => 'Efectivo',
            self::Other => 'Otro',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Card => 'brand',
            self::Transfer => 'info',
            self::Cash => 'success',
            self::Other => 'neutral',
        };
    }

    /**
     * Métodos que se registran manualmente (con referencia), nunca por pasarela.
     *
     * @return list<self>
     */
    public static function manualCases(): array
    {
        return [self::Transfer, self::Cash, self::Other];
    }
}
