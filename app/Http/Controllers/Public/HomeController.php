<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Web\HomePage;
use App\Services\Web\PublicPage;
use App\Services\Web\PublicSite;
use App\Services\Web\Seo;
use Inertia\Response;

/**
 * Portada: secciones del CMS en el orden y visibilidad guardados.
 */
class HomeController extends Controller
{
    public function __invoke(HomePage $home, PublicPage $page, Seo $seo): Response
    {
        $sections = $home->sections();
        $first = $sections[0] ?? null;
        $heroImage = is_array($first) && $first['type'] === 'hero' && is_array($first['image'] ?? null) ? $first['image'] : null;

        return $page->render('public/Home', [
            'sections' => $sections,
            'heroImage' => $heroImage,
        ], $seo->page(
            image: is_array($heroImage) ? url((string) $heroImage['src']) : null,
            jsonLd: [
                $seo->organization(),
                ['@type' => 'WebSite', '@id' => url('/').'#sitio', 'name' => PublicSite::name(), 'url' => url('/'), 'inLanguage' => 'es-MX'],
            ],
            canonical: url('/'),
        ), overlay: is_array($first) && $first['type'] === 'hero');
    }
}
