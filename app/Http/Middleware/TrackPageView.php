<?php

namespace App\Http\Middleware;

use App\Models\Page;
use App\Models\PageView;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ShowcaseProject;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Registra de forma anónima y agregada las visitas al sitio público de
 * ML Habitat para el panel de estadísticas.
 *
 * Privacidad por diseño: no se guarda IP, cookie ni URL de referencia
 * completa; el visitante se identifica con un hash que rota cada día.
 *
 * Cuenta cargas completas (HTML) y navegaciones de Inertia (no recargas
 * parciales ni prefetch). Excluye panel, portal, app de técnicos,
 * archivos, personal interno con sesión y bots. La escritura ocurre en
 * terminate(), después de enviar la respuesta.
 */
class TrackPageView
{
    /** Pantallas fijas: nombre de ruta → etiqueta. */
    private const SCREENS = [
        'home' => 'Inicio',
        'public.catalogo.index' => 'Catálogo',
        'public.inspiracion.index' => 'Inspiración',
        'public.preguntas' => 'Preguntas frecuentes',
    ];

    /** Prefijos de ruta que nunca se registran. */
    private const EXCLUDED = [
        'admin', 'portal', 'tecnico', 'archivos', 'subidas', 'settings', 'login', 'logout', 'register',
        'forgot-password', 'reset-password', 'two-factor-challenge', 'user', 'email', 'media', 'up',
        'storage', 'build', 'sitemap.xml', 'robots.txt', 'webhooks', 'cobertura',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            if (! $this->shouldTrack($request, $response)) {
                return;
            }

            $path = '/'.ltrim($request->path(), '/');
            $visitorHash = $this->visitorHash($request);
            $viewedOn = now()->toDateString();

            $isUnique = ! PageView::query()
                ->where('visitor_hash', $visitorHash)
                ->where('path', $path)
                ->where('viewed_on', $viewedOn)
                ->exists();

            PageView::query()->create([
                'path' => mb_substr($path, 0, 2048),
                'label' => mb_substr($this->label($request, $path), 0, 255),
                'referrer_host' => $this->referrerHost($request),
                'visitor_hash' => $visitorHash,
                'is_unique' => $isUnique,
                'viewed_on' => $viewedOn,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return false;
        }

        $isInertia = $request->headers->has('X-Inertia');
        if ($isInertia) {
            // Recargas parciales y prefetch no son visitas nuevas.
            if ($request->headers->has('X-Inertia-Partial-Data') || $request->headers->has('X-Inertia-Partial-Component')) {
                return false;
            }
            if (strtolower((string) $request->headers->get('Purpose')) === 'prefetch') {
                return false;
            }
        } else {
            $contentType = (string) $response->headers->get('Content-Type', '');
            if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
                return false;
            }
        }

        $path = ltrim($request->path(), '/');
        foreach (self::EXCLUDED as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return false;
            }
        }

        // El personal navegando el sitio no infla las cifras.
        $user = $request->user();
        if ($user instanceof User && $user->isStaff()) {
            return false;
        }

        return ! $this->isBot((string) $request->userAgent());
    }

    private function label(Request $request, string $path): string
    {
        $route = $request->route();
        $name = $route?->getName();

        if ($name !== null && isset(self::SCREENS[$name])) {
            return self::SCREENS[$name];
        }

        $param = fn (string $key) => $route?->parameter($key);

        return match (true) {
            $name === 'public.catalogo.categoria' && $param('category') instanceof ProductCategory => 'Catálogo · '.$param('category')->name,
            $name === 'public.catalogo.producto' && $param('product') instanceof Product => 'Producto · '.$param('product')->name,
            $name === 'public.inspiracion.show' && $param('showcase') instanceof ShowcaseProject => 'Inspiración · '.$param('showcase')->title,
            $name === 'public.legal' && $param('page') instanceof Page => 'Legal · '.$param('page')->title,
            str_starts_with(trim($path, '/'), 'solicitar') => 'Solicitud de asesoría',
            str_starts_with(trim($path, '/'), 'agendar-visita') => 'Agendar visita',
            default => $this->labelFromPath($path),
        };
    }

    private function isBot(string $userAgent): bool
    {
        if ($userAgent === '') {
            return true;
        }

        return (bool) preg_match(
            '/bot|crawl|spider|slurp|bing|google|yandex|baidu|duckduck|facebookexternalhit|embedly|preview|headless|lighthouse|pingdom|uptime|monitor|curl|wget|python-requests|axios|node-fetch|guzzle|httpclient/i',
            $userAgent,
        );
    }

    private function visitorHash(Request $request): string
    {
        return hash('sha256', implode('|', [
            $request->ip() ?? 'unknown',
            $request->userAgent() ?? 'unknown',
            now()->toDateString(),
            config('app.key'),
        ]));
    }

    private function referrerHost(Request $request): ?string
    {
        $referer = $request->headers->get('referer');
        if ($referer === null) {
            return null;
        }

        $host = parse_url($referer, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        if (str_ends_with(strtolower($host), strtolower((string) $request->getHost()))) {
            return null;
        }

        return mb_substr(preg_replace('/^www\./i', '', strtolower($host)) ?? $host, 0, 255);
    }

    private function labelFromPath(string $path): string
    {
        $trimmed = trim($path, '/');
        if ($trimmed === '') {
            return 'Inicio';
        }

        $segments = explode('/', $trimmed);
        $last = end($segments);

        return mb_convert_case(str_replace('-', ' ', $last), MB_CASE_TITLE);
    }
}
