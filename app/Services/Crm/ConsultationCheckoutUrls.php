<?php

namespace App\Services\Crm;

use App\Models\Consultation;
use App\Models\Payment;
use Illuminate\Support\Facades\URL;

/**
 * URLs firmadas del pago de asesorías. El uuid no es adivinable y la firma
 * impide manipular el enlace. El retorno de 3D Secure usa el genérico
 * `pagos.retorno` (concilia por API) y vuelve a `next_url` = manage().
 */
final class ConsultationCheckoutUrls
{
    public static function pay(Consultation $consultation): string
    {
        return URL::temporarySignedRoute('public.asesoria.pago', now()->addDays(7), ['consultation' => $consultation->uuid]);
    }

    public static function charge(Consultation $consultation): string
    {
        return URL::temporarySignedRoute('public.asesoria.cobrar', now()->addDay(), ['consultation' => $consultation->uuid]);
    }

    /** Pantalla de la asesoría (estado del pago, reprogramar, cancelar). */
    public static function manage(Consultation $consultation): string
    {
        return URL::signedRoute('public.asesoria.show', ['consultation' => $consultation->uuid]);
    }

    /** Retorno de 3D Secure: sin firma (Openpay agrega ?id=…); solo concilia y redirige a next_url. */
    public static function threeDsReturn(Payment $payment): string
    {
        return route('pagos.retorno', ['payment' => $payment->uuid]);
    }

    public static function receipt(Payment $payment): string
    {
        return URL::signedRoute('pagos.comprobante', ['payment' => $payment->uuid]);
    }
}
