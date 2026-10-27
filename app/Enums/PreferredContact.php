<?php

namespace App\Enums;

/** Medio de contacto preferido del cliente. */
enum PreferredContact: string
{
    case Whatsapp = 'whatsapp';
    case Phone = 'phone';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Whatsapp => 'WhatsApp',
            self::Phone => 'Llamada',
            self::Email => 'Correo electrónico',
        };
    }
}
