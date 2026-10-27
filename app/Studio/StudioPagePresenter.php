<?php

namespace App\Studio;

use App\Models\Page;
use App\Models\Post;
use App\Models\StudioPage;

/**
 * Prepara el prop `studio` que consumen las páginas públicas renderizadas
 * con Studio (Home, MiHistoria, MiProceso, Redes, StudioPage).
 */
class StudioPagePresenter
{
    public function __construct(
        private readonly StudioService $studio,
        private readonly StudioResolver $resolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function published(string $key): array
    {
        $page = $this->studio->ensureSystemPage($key);

        return $this->payload($page, $this->studio->publishedDocument($page) ?? $this->studio->draftDocument($page));
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function payload(StudioPage $page, array $document, ?Post $currentPost = null): array
    {
        // Al renderizar una publicación, el documento (plantilla de tips) se
        // resuelve contra el post actual y el meta toma sus propios datos SEO.
        if ($currentPost !== null) {
            return [
                'document' => $document,
                'resolved' => $this->resolver->resolve($document, $currentPost->slug, $currentPost),
                'meta' => [
                    'key' => $page->key,
                    'title' => $currentPost->title,
                    'seoTitle' => $currentPost->seo_title ?? $currentPost->title,
                    'seoDescription' => $currentPost->seo_description ?? $currentPost->excerpt,
                ],
            ];
        }

        $cmsPage = $page->isSystem() ? Page::query()->where('key', $page->key)->first() : null;

        return [
            'document' => $document,
            'resolved' => $this->resolver->resolve($document, $cmsPage?->key),
            'meta' => [
                'key' => $page->key,
                'title' => $cmsPage->title ?? $page->title,
                'seoTitle' => $page->seo_title ?? $cmsPage?->seo_title,
                'seoDescription' => $page->seo_description ?? $cmsPage?->seo_description,
            ],
        ];
    }
}
