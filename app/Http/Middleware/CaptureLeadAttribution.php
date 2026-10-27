<?php

namespace App\Http\Middleware;

use App\Support\Crm\Attribution;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarda en sesión la atribución de primer contacto (UTM, referrer externo,
 * página de llegada). Se aplica a las rutas públicas del CRM; puede
 * agregarse al grupo `web` para capturar desde cualquier página de llegada.
 */
class CaptureLeadAttribution
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->header('X-Inertia') || $request->query('utm_source')) {
            Attribution::capture($request);
        }

        return $next($request);
    }
}
