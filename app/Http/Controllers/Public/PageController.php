<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageEntry;
use App\Services\PublicSiteDataService;
use App\Studio\StudioPagePresenter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Páginas institucionales. Mi historia y Mi proceso se componen en Studio;
 * su texto, portada y entradas siguen administrándose en /admin/paginas y
 * Studio los muestra por referencia.
 */
class PageController extends Controller
{
    public function historia(PublicSiteDataService $siteData, StudioPagePresenter $studio): Response
    {
        $page = Page::query()->where('key', 'mi-historia')->firstOrFail();

        return Inertia::render('public/MiHistoria', [
            ...$siteData->base(),
            'page' => $this->pageData($page),
            'studio' => $studio->published('mi-historia'),
        ]);
    }

    public function proceso(PublicSiteDataService $siteData, StudioPagePresenter $studio): Response
    {
        $page = Page::query()->where('key', 'mi-proceso')->firstOrFail();
        $entries = $page->entries()->where('is_visible', true)->with('cover')->get();

        return Inertia::render('public/MiProceso', [
            ...$siteData->base(),
            'page' => $this->pageData($page),
            'entries' => $entries->map(fn (PageEntry $entry) => [
                'id' => $entry->id,
                'title' => $entry->title,
                'date' => $entry->entry_date?->toIso8601String(),
                'coverUrl' => $entry->cover?->url(),
                'body' => $entry->body,
            ]),
            'studio' => $studio->published('mi-proceso'),
        ]);
    }

    public function privacidad(PublicSiteDataService $siteData): Response
    {
        $page = Page::query()->where('key', 'aviso-privacidad')->firstOrFail();

        return Inertia::render('public/AvisoPrivacidad', [
            ...$siteData->base(),
            'page' => [
                ...$this->pageData($page),
                'updatedAt' => $page->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function pageData(Page $page): array
    {
        return [
            'title' => $page->title,
            'intro' => $page->intro,
            'body' => $page->body,
            'coverUrl' => $page->cover?->url(),
            'seoTitle' => $page->seo_title,
            'seoDescription' => $page->seo_description,
        ];
    }
}
