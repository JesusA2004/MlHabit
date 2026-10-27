<?php

namespace App\Enums;

/** Agenda a la que pertenece una disponibilidad o reserva. */
enum BookingKind: string
{
    /** Agenda central de asesores (asesoría remota). */
    case Consultation = 'consultation';
    /** Agenda local de técnicos (visita a domicilio). */
    case Visit = 'visit';
    /** Bloque de instalación. */
    case Installation = 'installation';

    public function label(): string
    {
        return match ($this) {
            self::Consultation => 'Asesoría',
            self::Visit => 'Visita técnica',
            self::Installation => 'Instalación',
        };
    }
}
