<?php

namespace App\Enums;

/** Tipo de dato capturado en una medición. Todos los valores en milímetros. */
enum MeasurementKind: string
{
    case Width = 'width';
    case Height = 'height';
    case Depth = 'depth';
    case Diagonal = 'diagonal';
    case Squareness = 'squareness';
    case LevelDifference = 'level_difference';
    case Door = 'door';
    case Window = 'window';
    case Outlet = 'outlet';
    case Plumbing = 'plumbing';
    case Gas = 'gas';
    case BeamColumn = 'beam_column';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Width => 'Ancho',
            self::Height => 'Alto',
            self::Depth => 'Fondo',
            self::Diagonal => 'Diagonal',
            self::Squareness => 'Escuadra',
            self::LevelDifference => 'Desnivel',
            self::Door => 'Puerta',
            self::Window => 'Ventana',
            self::Outlet => 'Contacto eléctrico',
            self::Plumbing => 'Toma de agua / desagüe',
            self::Gas => 'Toma de gas',
            self::BeamColumn => 'Trabe o columna',
            self::Other => 'Otro',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $k) => ['value' => $k->value, 'label' => $k->label()], self::cases());
    }
}
