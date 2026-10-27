<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ShowcaseProject;
use App\Models\SiteSection;
use App\Services\Web\PublicSite;
use App\Support\ResponsiveImage;
use Carbon\CarbonInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * SEO técnico: sitemap.xml (solo contenido publicado, con lastmod e
 * imágenes; índice de sitemaps si crece), robots.txt y site.webmanifest.
 */
class SeoController extends Controller
{
    /** Límite por archivo (el estándar permite 50 000; se deja margen). */
    public const PER_FILE = 5000;

    private const XML_HEADERS = ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600', 'X-Robots-Tag' => 'noindex'];

    public function sitemap(): Response
    {
        $urls = $this->urls();

        if (count($urls) <= self::PER_FILE) {
            return response($this->urlset($urls), 200, self::XML_HEADERS);
        }

        // Índice: /sitemap.xml → /sitemap-1.xml, /sitemap-2.xml…
        $chunks = array_chunk($urls, self::PER_FILE);
        $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($chunks as $i => $chunk) {
            $lastmod = collect($chunk)->pluck('lastmod')->filter()->max();
            $body .= '  <sitemap><loc>'.e(url('/sitemap-'.($i + 1).'.xml')).'</loc>'
                .($lastmod instanceof CarbonInterface ? '<lastmod>'.$lastmod->toAtomString().'</lastmod>' : '')
                .'</sitemap>'."\n";
        }

        return response($body.'</sitemapindex>'."\n", 200, self::XML_HEADERS);
    }

    public function sitemapPart(int $part): Response
    {
        $chunks = array_chunk($this->urls(), self::PER_FILE);
        abort_unless($part >= 1 && isset($chunks[$part - 1]) && count($chunks) > 1, 404);

        return response($this->urlset($chunks[$part - 1]), 200, self::XML_HEADERS);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /portal',
            'Disallow: /tecnico',
            'Disallow: /archivos',
            'Disallow: /subidas',
            'Disallow: /settings',
            'Disallow: /agendar-visita/pago',
            'Disallow: /solicitar/gracias',
            'Disallow: /solicitud/',
            'Disallow: /asesoria/',
            'Disallow: /pagos/',
            'Disallow: /login',
            'Disallow: /forgot-password',
            'Disallow: /reset-password',
            'Disallow: /two-factor-challenge',
            'Disallow: /invitacion',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function manifest(): Response
    {
        $name = PublicSite::name();

        $manifest = [
            'name' => $name,
            'short_name' => $name,
            'description' => 'Cocinas integrales, clósets y muebles a la medida.',
            'lang' => 'es-MX',
            'dir' => 'ltr',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#F7F5F2',
            'theme_color' => '#F7F5F2',
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => '/favicon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml'],
            ],
        ];

        return response(
            (string) json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            200,
            ['Content-Type' => 'application/manifest+json; charset=UTF-8', 'Cache-Control' => 'public, max-age=86400'],
        );
    }

    /**
     * @return list<array{loc: string, lastmod: CarbonInterface|null, images: list<array{loc: string}>}>
     */
    private function urls(): array
    {
        /** @var list<array{loc: string, lastmod: CarbonInterface|null, images: list<array{loc: string}>}> */
        return Cache::remember('web.sitemap.v2', 3600, function (): array {
            $categories = ProductCategory::query()->published()->ordered()->with('cover.variants')->get();
            $products = Product::query()->published()
                ->whereHas('category', fn ($q) => $q->where('is_published', true))
                ->with(['category:id,slug,is_published', 'cover.variants', 'media' => fn ($q) => $q->wherePivot('role', 'gallery')->with('variants')])
                ->orderBy('id')->get();
            $projects = ShowcaseProject::query()->published()
                ->with(['cover.variants', 'media' => fn ($q) => $q->wherePivot('role', 'gallery')->with('variants')])
                ->orderBy('id')->get();
            $pages = Page::query()->published()->orderBy('id')->get(['slug', 'title', 'updated_at']);

            $latest = fn (iterable $items): ?CarbonInterface => collect($items)->pluck('updated_at')->filter()->max();
            $homeUpdated = SiteSection::query()->max('updated_at');

            $urls = [
                ['loc' => url('/'), 'lastmod' => $homeUpdated !== null ? Carbon::parse((string) $homeUpdated) : null, 'images' => []],
            ];
            if ($categories->isNotEmpty()) {
                $urls[] = ['loc' => url('/catalogo'), 'lastmod' => $latest($categories), 'images' => []];
            }
            if ($projects->isNotEmpty()) {
                $urls[] = ['loc' => url('/inspiracion'), 'lastmod' => $latest($projects), 'images' => []];
            }
            $urls[] = ['loc' => url('/preguntas-frecuentes'), 'lastmod' => null, 'images' => []];
            $urls[] = ['loc' => url('/solicitar'), 'lastmod' => null, 'images' => []];

            foreach ($categories as $category) {
                $urls[] = ['loc' => url('/catalogo/'.$category->slug), 'lastmod' => $category->updated_at, 'images' => $this->images([$category->cover])];
            }

            foreach ($products as $product) {
                $urls[] = [
                    'loc' => url('/catalogo/'.$product->category->slug.'/'.$product->slug),
                    'lastmod' => $product->updated_at,
                    'images' => $this->images([$product->cover, ...$product->media->all()]),
                ];
            }

            foreach ($projects as $project) {
                $urls[] = [
                    'loc' => url('/inspiracion/'.$project->slug),
                    'lastmod' => $project->updated_at,
                    'images' => $this->images([$project->cover, ...$project->media->all()]),
                ];
            }

            foreach ($pages as $page) {
                $urls[] = ['loc' => url('/legal/'.$page->slug), 'lastmod' => $page->updated_at, 'images' => []];
            }

            return $urls;
        });
    }

    /**
     * @param  array<int, Media|null>  $media
     * @return list<array{loc: string}>
     */
    private function images(array $media): array
    {
        $out = [];
        foreach ($media as $item) {
            $url = ResponsiveImage::largeUrl($item);
            if ($item !== null && $url !== null && ! isset($out[$url])) {
                $out[$url] = ['loc' => $url];
            }
        }

        return array_values(array_slice($out, 0, 20));
    }

    /**
     * @param  array<int, array{loc: string, lastmod: CarbonInterface|null, images: list<array{loc: string}>}>  $urls
     */
    private function urlset(array $urls): string
    {
        $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'."\n";

        foreach ($urls as $url) {
            $body .= '  <url><loc>'.e($url['loc']).'</loc>';
            if ($url['lastmod'] instanceof CarbonInterface) {
                $body .= '<lastmod>'.$url['lastmod']->toAtomString().'</lastmod>';
            }
            foreach ($url['images'] as $image) {
                $body .= '<image:image><image:loc>'.e($image['loc']).'</image:loc></image:image>';
            }
            $body .= '</url>'."\n";
        }

        return $body.'</urlset>'."\n";
    }
}
