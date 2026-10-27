<?php

namespace App\Enums;

enum FaqTopic: string
{
    case General = 'general';
    case Process = 'proceso';
    case Visit = 'visita';
    case Payments = 'pagos';
    case Warranty = 'garantia';
    case Product = 'producto';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Process => 'Proceso',
            self::Visit => 'Visita técnica',
            self::Payments => 'Pagos',
            self::Warranty => 'Garantía',
            self::Product => 'Productos',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::General => 'neutral',
            self::Process => 'brand',
            self::Visit => 'info',
            self::Payments => 'warning',
            self::Warranty => 'success',
            self::Product => 'neutral',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
