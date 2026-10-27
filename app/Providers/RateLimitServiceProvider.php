<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Limitadores con nombre compartidos por los módulos:
 *
 * - `public-forms`: formularios públicos sin sesión (solicitar asesoría,
 *   contacto, reserva de visita). 5/min y 30/h por IP.
 * - `public-uploads`: fotos opcionales en formularios públicos. 20/h por IP.
 * - `sensitive-actions`: acciones administrativas delicadas (reenviar
 *   invitaciones, restablecer 2FA). 10/min por usuario.
 * - `notifications`: centro de notificaciones (sondeo cada 60 s). 30/min.
 *
 * Uso: ->middleware('throttle:public-forms').
 */
class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('public-forms', fn (Request $request) => [
            Limit::perMinute(5)->by('public-forms|'.$request->ip()),
            Limit::perHour(30)->by('public-forms-h|'.$request->ip()),
        ]);

        RateLimiter::for('public-uploads', fn (Request $request) => Limit::perHour(20)->by('public-uploads|'.$request->ip()));

        RateLimiter::for('sensitive-actions', fn (Request $request) => Limit::perMinute(10)
            ->by('sensitive|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('notifications', fn (Request $request) => Limit::perMinute(30)
            ->by('notifications|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
