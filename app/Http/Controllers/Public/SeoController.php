<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ShowcaseProject;
use Carbon\CarbonInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * sitemap.xml (solo contenido publicado) y robots.txt.
 */
class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $xml = Cache::remember('web.sitemap.v1', 3600, function (): string {
            $urls = [
                ['loc' => url('/'), 'lastmod' => null, 'priority' => '1.0'],
                ['loc' => url('/catalogo'), 'lastmod' => null, 'priority' => '0.9'],
                ['loc' => url('/inspiracion'), 'lastmod' => null, 'priority' => '0.7'],
                ['loc' => url('/preguntas-frecuentes'), 'lastmod' => null, 'priority' => '0.6'],
            ];

            foreach (ProductCategory::query()->published()->ordered()->get(['slug', 'updated_at']) as $category) {
                $urls[] = ['loc' => url('/catalogo/'.$category->slug), 'lastmod' => $category->updated_at, 'priority' => '0.8'];
            }

            foreach (Product::query()->published()->with('category:id,slug')->orderBy('id')->get(['id', 'slug', 'product_category_id', 'updated_at']) as $product) {
                $urls[] = ['loc' => url('/catalogo/'.$product->category->slug.'/'.$product->slug), 'lastmod' => $product->updated_at, 'priority' => '0.8'];
            }

            foreach (ShowcaseProject::query()->published()->orderBy('id')->get(['slug', 'updated_at']) as $project) {
                $urls[] = ['loc' => url('/inspiracion/'.$project->slug), 'lastmod' => $project->updated_at, 'priority' => '0.6'];
            }

            foreach (Page::query()->published()->orderBy('id')->get(['slug', 'updated_at']) as $page) {
                $urls[] = ['loc' => url('/legal/'.$page->slug), 'lastmod' => $page->updated_at, 'priority' => '0.3'];
            }

            $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
            foreach ($urls as $url) {
                $body .= '  <url><loc>'.e($url['loc']).'</loc>';
                if ($url['lastmod'] instanceof CarbonInterface) {
                    $body .= '<lastmod>'.$url['lastmod']->toAtomString().'</lastmod>';
                }
                $body .= '<priority>'.$url['priority'].'</priority></url>'."\n";
            }

            return $body.'</urlset>'."\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /portal',
            'Disallow: /tecnico',
            'Disallow: /archivos',
            'Disallow: /subidas',
            'Disallow: /settings',
            'Allow: /',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
