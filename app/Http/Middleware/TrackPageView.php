<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use App\Models\Post;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registra, de forma anónima y agregada, las visitas a las pantallas
 * públicas de MR. INSIGHT para alimentar el panel de estadísticas.
 *
 * Privacidad por diseño:
 * - No se guarda IP, cookie ni URL de referido completa.
 * - El visitante se identifica con un hash que incluye la fecha, por lo que
 *   rota cada día y no permite seguimiento entre días.
 *
 * La escritura ocurre en terminate(), después de enviar la respuesta, para
 * no añadir latencia perceptible. Solo cuenta respuestas HTML 200 de rutas
 * públicas servidas por GET; descarta administración, descargas, vistas
 * previas y bots.
 */
class TrackPageView
{
    /** Nombres de ruta → [clave de página de Studio, etiqueta legible]. */
    private const SCREENS = [
        'home' => ['home', 'Inicio'],
        'tips.index' => [null, 'Tips'],
        'tips.show' => ['tip', null],
        'pages.historia' => ['mi-historia', 'Mi historia'],
        'pages.proceso' => ['mi-proceso', 'Mi proceso'],
        'pages.privacidad' => [null, 'Aviso de privacidad'],
        'social.index' => ['redes', 'Redes'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $this->shouldTrack($request, $response)) {
            return;
        }

        $routeName = $request->route()?->getName();
        [$studioKey, $label] = self::SCREENS[$routeName] ?? [null, null];

        $postId = null;
        if ($routeName === 'tips.show') {
            $slug = (string) $request->route('slug');
            $post = Post::query()->where('slug', $slug)->first(['id', 'title']);
            $postId = $post?->id;
            $label = $post?->title ?? 'Tip';
        }

        $path = '/'.ltrim($request->path(), '/');
        $label ??= $this->labelFromPath($path);

        $visitorHash = $this->visitorHash($request);
        $viewedOn = now()->toDateString();

        $isUnique = ! PageView::query()
            ->where('visitor_hash', $visitorHash)
            ->where('path', $path)
            ->whereDate('viewed_on', $viewedOn)
            ->exists();

        PageView::query()->create([
            'path' => mb_substr($path, 0, 2048),
            'post_id' => $postId,
            'studio_page_key' => $studioKey,
            'label' => $label !== null ? mb_substr($label, 0, 255) : null,
            'referrer_host' => $this->referrerHost($request),
            'visitor_hash' => $visitorHash,
            'is_unique' => $isUnique,
            'viewed_on' => $viewedOn,
        ]);
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        // Solo documentos HTML (descarta JSON de Inertia parcial, descargas…).
        $contentType = (string) $response->headers->get('Content-Type', '');
        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return false;
        }

        // Peticiones parciales de Inertia: ya se contó la carga completa.
        if ($request->headers->has('X-Inertia')) {
            return false;
        }

        $path = ltrim($request->path(), '/');

        // Rutas no públicas o técnicas.
        foreach (['admin', 'settings', 'login', 'register', 'media', 'vista-previa', 'up', 'storage', 'build'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return false;
            }
        }

        return ! $this->isBot((string) $request->userAgent());
    }

    private function isBot(string $userAgent): bool
    {
        if ($userAgent === '') {
            return true;
        }

        return (bool) preg_match(
            '/bot|crawl|spider|slurp|bing|google|yandex|baidu|duckduck|facebookexternalhit|embedly|preview|headless|lighthouse|pingdom|uptime|monitor|curl|wget|python-requests|axios|node-fetch/i',
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

        // Las visitas internas no cuentan como referido externo.
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

        $last = (string) (collect(explode('/', $trimmed))->last() ?? $trimmed);

        return mb_convert_case(str_replace('-', ' ', $last), MB_CASE_TITLE);
    }
}
