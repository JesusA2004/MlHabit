<?php

namespace App\Support\Crm;

use App\Models\Consultation;
use App\Models\Lead;
use App\Models\Media;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

/**
 * Enlaces firmados del CRM para el cliente (sin sesión). Las firmas no
 * caducan porque viajan en correos; cada pantalla valida además el estado
 * de la entidad (p. ej. no se reprograma una asesoría ya realizada).
 */
final class CrmLinks
{
    public static function thankYou(Lead $lead): string
    {
        return URL::signedRoute('public.solicitar.gracias', ['lead' => $lead->uuid]);
    }

    public static function bookConsultation(Lead $lead): string
    {
        return URL::signedRoute('public.asesoria.create', ['lead' => $lead->uuid]);
    }

    public static function manageConsultation(Consultation $consultation): string
    {
        return URL::signedRoute('public.asesoria.show', ['consultation' => $consultation->uuid]);
    }

    public static function prequalification(Lead $lead): string
    {
        return URL::signedRoute('public.precalificacion.create', ['lead' => $lead->uuid]);
    }

    /** Reserva de visita técnica (módulo visits). Null si la ruta aún no existe. */
    public static function bookVisit(Lead $lead): ?string
    {
        return Route::has('public.visita.inicio')
            ? URL::signedRoute('public.visita.inicio', ['lead' => $lead->uuid])
            : null;
    }

    /** Agenda abierta de visitas que pide CP primero (módulo visits). */
    public static function openVisit(): ?string
    {
        return Route::has('public.visita.abierta') ? route('public.visita.abierta') : null;
    }

    /** Descarga autorizada de un archivo privado (módulo web: archivos.show). */
    public static function privateMedia(Media $media, ?string $variant = null): string
    {
        $params = ['media' => $media->uuid];
        if ($variant) {
            $params['variante'] = $variant;
        }

        return Route::has('archivos.show')
            ? route('archivos.show', $params)
            : url('/archivos/'.$media->uuid).($variant ? '?variante='.$variant : '');
    }
}
