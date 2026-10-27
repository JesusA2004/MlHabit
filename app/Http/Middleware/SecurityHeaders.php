<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad para todas las respuestas web (config/security.php).
 *
 * La CSP es compatible con Vite (servidor de desarrollo y build), Inertia,
 * Bunny/Google Fonts y Openpay. Los <script> de Vite reciben un nonce; los
 * scripts en línea propios deben usar `nonce="{{ Vite::cspNonce() }}"`.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $cspEnabled = (bool) config('security.csp.enabled', true);

        if ($cspEnabled) {
            Vite::useCspNonce();
        }

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Permissions-Policy', (string) config('security.permissions_policy'));
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');

        if (app()->isProduction() && $request->isSecure()) {
            $hsts = 'max-age='.(int) config('security.hsts.max_age', 31536000);
            if (config('security.hsts.include_subdomains', true)) {
                $hsts .= '; includeSubDomains';
            }
            $headers->set('Strict-Transport-Security', $hsts);
        }

        if ($cspEnabled && ! $headers->has('Content-Security-Policy')) {
            $header = config('security.csp.enforce', false)
                ? 'Content-Security-Policy'
                : 'Content-Security-Policy-Report-Only';

            $headers->set($header, $this->contentSecurityPolicy());
        }

        return $response;
    }

    public function contentSecurityPolicy(): string
    {
        /** @var array<string, list<string>> $extra */
        $extra = (array) config('security.csp.sources', []);
        $nonce = Vite::cspNonce();
        $dev = $this->viteDevOrigins();

        $directives = [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
            'frame-ancestors' => ["'self'"],
            'form-action' => ["'self'"],
            'script-src' => ["'self'", ...($nonce ? ["'nonce-{$nonce}'"] : []), ...$dev['http']],
            // Vue y las variables de tema usan estilos en línea.
            'style-src' => ["'self'", "'unsafe-inline'", ...$dev['http']],
            'font-src' => ["'self'", 'data:', ...$dev['http']],
            'img-src' => ["'self'", 'data:', 'blob:', ...$dev['http']],
            'media-src' => ["'self'", 'blob:', 'data:'],
            'connect-src' => ["'self'", ...$dev['http'], ...$dev['ws']],
            'frame-src' => ["'self'"],
            'worker-src' => ["'self'", 'blob:'],
            'manifest-src' => ["'self'"],
        ];

        foreach ($extra as $directive => $sources) {
            $directives[$directive] = [...($directives[$directive] ?? ["'self'"]), ...$sources];
        }

        $parts = [];
        foreach ($directives as $directive => $sources) {
            $parts[] = $directive.' '.implode(' ', array_values(array_unique($sources)));
        }

        if (app()->isProduction()) {
            $parts[] = 'upgrade-insecure-requests';
        }

        $reportUri = config('security.csp.report_uri');
        if (is_string($reportUri) && $reportUri !== '') {
            $parts[] = 'report-uri '.$reportUri;
        }

        return implode('; ', $parts);
    }

    /**
     * Orígenes del servidor de desarrollo de Vite (archivo public/hot).
     *
     * @return array{http: list<string>, ws: list<string>}
     */
    private function viteDevOrigins(): array
    {
        if (! Vite::isRunningHot()) {
            return ['http' => [], 'ws' => []];
        }

        $url = trim((string) @file_get_contents(public_path('hot')));
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['host'])) {
            return ['http' => [], 'ws' => []];
        }

        $host = str_contains($parts['host'], ':') && ! str_starts_with($parts['host'], '[')
            ? '['.$parts['host'].']'
            : $parts['host'];
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $scheme = $parts['scheme'] ?? 'http';
        $ws = $scheme === 'https' ? 'wss' : 'ws';

        return [
            'http' => ["{$scheme}://{$host}{$port}"],
            'ws' => ["{$ws}://{$host}{$port}"],
        ];
    }
}
