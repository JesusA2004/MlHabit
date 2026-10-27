<?php

namespace App\Enums;

/** Tipo de ocupación declarada en la precalificación. */
enum EmploymentType: string
{
    case Employee = 'employee';
    case SelfEmployed = 'self_employed';
    case BusinessOwner = 'business_owner';
    case Retired = 'retired';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Employee => 'Empleado(a) con nómina',
            self::SelfEmployed => 'Independiente / honorarios',
            self::BusinessOwner => 'Dueño(a) de negocio',
            self::Retired => 'Jubilado(a) o pensionado(a)',
            self::Other => 'Otro',
        };
    }
}
