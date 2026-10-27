<?php

namespace App\Services\Visits;

use App\Models\Lead;
use App\Models\Payment;
use Illuminate\Support\Facades\URL;

/**
 * URLs firmadas del checkout de visitas. El uuid del pago no es
 * adivinable y además la firma impide manipular el enlace.
 */
final class VisitCheckoutUrls
{
    /** Enlace que crm envía al prospecto para agendar y pagar (contrato de EQUIPO.md). */
    public static function forLead(Lead $lead): string
    {
        return URL::signedRoute('public.visita.inicio', ['lead' => $lead->uuid]);
    }

    public static function pay(Payment $payment): string
    {
        return URL::temporarySignedRoute('public.visita.pago', now()->addDays(7), ['payment' => $payment->uuid]);
    }

    public static function charge(Payment $payment): string
    {
        return URL::temporarySignedRoute('public.visita.cobrar', now()->addDay(), ['payment' => $payment->uuid]);
    }

    public static function status(Payment $payment): string
    {
        return URL::temporarySignedRoute('public.visita.estado', now()->addDays(30), ['payment' => $payment->uuid]);
    }

    /** Retorno de 3D Secure: sin firma (Openpay agrega ?id=…); solo concilia y redirige. */
    public static function threeDsReturn(Payment $payment): string
    {
        return route('public.visita.retorno', ['payment' => $payment->uuid]);
    }
}
