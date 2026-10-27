<?php

namespace App\Enums;

/** Medio por el que se realiza la asesoría remota. */
enum ConsultationChannel: string
{
    case Video = 'video';
    case Phone = 'phone';
    case Whatsapp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Video => 'Videollamada',
            self::Phone => 'Llamada telefónica',
            self::Whatsapp => 'WhatsApp',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Video => 'Te enviamos el enlace por correo antes de la cita. Puedes compartir fotos de tu espacio en pantalla.',
            self::Phone => 'Tu asesor te llama al número que registraste.',
            self::Whatsapp => 'Tu asesor te escribe o llama por WhatsApp; ideal para enviar fotos y medidas.',
        };
    }
}
