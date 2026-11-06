<?php

namespace App\Services\Web;

use App\Enums\MediaVisibility;
use App\Models\Media;
use App\Models\SitePage;
use App\Support\ResponsiveImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Inertia\Response;

/**
 * Páginas propias del sitio (armadas con el editor visual): render
 * público con SEO y lista de URLs reservadas.
 *
 * Se sirven en `/{slug}` con una ruta de respaldo (`->fallback()`): Laravel
 * siempre la evalúa al final, así nunca tapa una ruta existente. Además, el
 * slug no puede ser el primer tramo de ninguna ruta registrada ni una
 * carpeta de `public/` (ver reserved()).
 */
class SitePages
{
    /** Tramos que nunca pueden ser una página (aunque hoy no haya ruta). */
    private const ALWAYS_RESERVED = [
        'admin', 'api', 'portal', 'tecnico', 'login', 'logout', 'register', 'registro', 'settings', 'ajustes',
        'media', 'archivos', 'subidas', 'storage', 'build', 'up', 'og', 'sitemap', 'robots', 'favicon',
        'icons', 'fonts', 'images', 'img', 'assets', 'js', 'css', 'vendor', 'livewire', 'sanctum',
        'p', 'pagina', 'paginas', 'inicio', 'home', 'buscar', 'search', 'feed', 'rss', 'wp-admin', 'wp-login',
        'email', 'user', 'password', 'forgot-password', 'reset-password', 'two-factor-challenge', 'invitacion',
        'notificaciones', 'cotizaciones', 'pagos', 'webhooks', 'legal',
    ];

    /** @var list<string>|null */
    private static ?array $reserved = null;

    public function __construct(
        private readonly PublicPage $page,
        private readonly Seo $seo,
        private readonly OgImage $og,
    ) {}

    /**
     * ¿El primer bloque de la página pide la cabecera transparente?
     *
     * @param  list<array<string, mixed>>  $sections
     */
    public static function overlay(array $sections): bool
    {
        $first = $sections[0] ?? null;

        return is_array($first) && (($first['layout']['settings']['overlayHeader'] ?? false) === true);
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     */
    public function render(SitePage $sitePage, array $sections, bool $preview = false): Response
    {
        $title = $sitePage->seo_title ?: $sitePage->title;
        $og = $sitePage->ogMedia;
        $image = $og instanceof Media && $og->visibility === MediaVisibility::Public ? ResponsiveImage::largeUrl($og) : null;
        $first = $sections[0] ?? null;

        return $this->page->render('public/SitePage', [
            'page' => [
                'title' => $sitePage->title,
                'slug' => $sitePage->slug,
            ],
            'sections' => $sections,
            'heroImage' => is_array($first) ? ($first['image'] ?? null) : null,
            'preview' => $preview,
        ], $this->seo->page(
            $title,
            $sitePage->seo_description,
            image: $image,
            jsonLd: $preview ? [] : [$this->seo->breadcrumbs([['Inicio', '/'], [$sitePage->title, $sitePage->path()]])],
            canonical: url($sitePage->path()),
            noindex: $preview,
            og: $image === null ? $this->og->page($sitePage->slug, $title) : null,
        ), overlay: self::overlay($sections));
    }

    /**
     * URLs que no puede usar una página: primer tramo de cada ruta
     * registrada, carpetas de `public/` y una lista fija.
     *
     * @return list<string>
     */
    public static function reserved(): array
    {
        if (self::$reserved !== null) {
            return self::$reserved;
        }

        $out = self::ALWAYS_RESERVED;
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->isFallback) {
                continue;
            }
            $first = explode('/', trim($route->uri(), '/'))[0];
            if ($first !== '' && ! str_contains($first, '{')) {
                $out[] = strtolower((string) preg_replace('/\.[a-z0-9]+$/i', '', $first));
            }
        }
        foreach (File::directories(public_path()) as $dir) {
            $out[] = strtolower(basename($dir));
        }

        return self::$reserved = array_values(array_unique($out));
    }

    /**
     * Patrón del parámetro `{slug}` de la ruta pública: un solo tramo en
     * minúsculas que no sea un tramo fijo reservado (así p. ej. un POST a
     * /register sigue respondiendo 404 y no 405).
     */
    public static function routePattern(): string
    {
        $reserved = implode('|', array_map(fn (string $s) => preg_quote($s, '/'), self::ALWAYS_RESERVED));

        return '(?!(?:'.$reserved.')$)[a-z0-9]+(?:-[a-z0-9]+)*';
    }

    public static function isReserved(string $slug): bool
    {
        return in_array(strtolower($slug), self::reserved(), true);
    }

    /** Vacía la lista calculada (pruebas). */
    public static function forgetReserved(): void
    {
        self::$reserved = null;
    }
}
