<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Web\PublicPage;
use App\Services\Web\Seo;
use Inertia\Response;

/**
 * Páginas legales e informativas publicadas (/legal/{slug}). El cuerpo ya
 * viene saneado (Html::clean al guardar).
 */
class LegalPageController extends Controller
{
    public function __invoke(Page $page, PublicPage $render, Seo $seo): Response
    {
        abort_unless($page->is_published, 404);

        return $render->render('public/legal/Show', [
            'page' => [
                'title' => $page->title,
                'slug' => $page->slug,
                'summary' => $page->summary,
                'body' => $page->body,
                'version' => $page->version,
                'publishedAt' => $page->published_at?->toIso8601String(),
            ],
        ], $seo->page(
            $page->title,
            $page->summary ?: $page->body,
            jsonLd: [$seo->breadcrumbs([['Inicio', '/'], [$page->title, '/legal/'.$page->slug]])],
            canonical: url('/legal/'.$page->slug),
        ));
    }
}
