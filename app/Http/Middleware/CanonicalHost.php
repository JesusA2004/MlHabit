<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dominio canónico (solo en producción): redirige con 301 las peticiones
 * GET/HEAD que llegan por http o por el alias www al host de APP_URL
 * (https://mlhabitat.com.mx). Evita contenido duplicado para Google y
 * concentra enlaces y vistas previas en una sola URL.
 *
 * - Solo redirige alias conocidos (www.<host> ↔ <host>), nunca IPs ni
 *   hosts ajenos (chequeos de salud del balanceador, previews).
 * - POST/PUT/DELETE no se redirigen (webhooks, formularios).
 * - Detrás de un proxy/CDN que termina TLS se respeta X-Forwarded-Proto
 *   para no crear bucles de redirección.
 */
class CanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->isProduction() || ! config('app.canonical_redirect', true) || ! $request->isMethodSafe()) {
            return $next($request);
        }

        $target = parse_url((string) config('app.url'));
        $host = strtolower((string) ($target['host'] ?? ''));
        $scheme = strtolower((string) ($target['scheme'] ?? 'https'));

        if ($host === '' || $request->is('up')) {
            return $next($request);
        }

        $current = strtolower($request->getHost());
        $bare = preg_replace('/^www\./', '', $host) ?? $host;
        $isAlias = $current === $host || $current === $bare || $current === 'www.'.$bare;

        if (! $isAlias) {
            return $next($request);
        }

        $secure = $request->isSecure() || strtolower((string) $request->header('X-Forwarded-Proto')) === 'https';
        $wrongScheme = $scheme === 'https' && ! $secure;

        if ($current === $host && ! $wrongScheme) {
            return $next($request);
        }

        $port = isset($target['port']) ? ':'.$target['port'] : '';

        return redirect()->to($scheme.'://'.$host.$port.$request->getRequestUri(), 301);
    }
}
