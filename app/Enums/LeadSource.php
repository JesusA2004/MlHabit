<?php

namespace App\Enums;

/** Origen de un prospecto. */
enum LeadSource: string
{
    case WebForm = 'web_form';
    case Campaign = 'campaign';
    case Referral = 'referral';
    case Phone = 'phone';
    case Whatsapp = 'whatsapp';
    case WalkIn = 'walk_in';
    case Social = 'social';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::WebForm => 'Formulario web',
            self::Campaign => 'Campaña',
            self::Referral => 'Recomendación',
            self::Phone => 'Llamada',
            self::Whatsapp => 'WhatsApp',
            self::WalkIn => 'Visita a sala de exhibición',
            self::Social => 'Redes sociales',
            self::Other => 'Otro',
        };
    }
}
