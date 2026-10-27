<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Módulo CRM: limitadores con nombre de los endpoints públicos.
 *
 * - `crm-lead-submissions`: envíos de /solicitar (config crm.lead_submissions_per_hour, 5/h por IP).
 * - `crm-coverage`: verificación de CP en vivo (30/min por IP).
 * - `crm-availability`: fechas/horarios de asesoría (60/min por IP).
 * - `crm-customer-actions`: acciones con enlace firmado (reservar, reprogramar, precalificación). 10/min por IP.
 */
class CrmServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('crm-lead-submissions', fn (Request $request) => Limit::perHour(max(1, (int) config('crm.lead_submissions_per_hour', 5)))
            ->by('crm-leads|'.$request->ip())
            // Redirección normal (302) con error para que Inertia lo muestre en el formulario.
            ->response(fn () => back()->withErrors([
                'form' => 'Recibimos varias solicitudes desde tu conexión en poco tiempo. Intenta de nuevo más tarde o escríbenos por WhatsApp.',
            ])));

        RateLimiter::for('crm-coverage', fn (Request $request) => Limit::perMinute(30)->by('crm-coverage|'.$request->ip()));

        RateLimiter::for('crm-availability', fn (Request $request) => Limit::perMinute(60)->by('crm-availability|'.$request->ip()));

        RateLimiter::for('crm-customer-actions', fn (Request $request) => Limit::perMinute(10)->by('crm-customer|'.$request->ip()));
    }
}
