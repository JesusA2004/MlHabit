<?php

namespace App\Studio;

use App\Enums\MediaStatus;
use App\Models\Category;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageEntry;
use App\Models\Post;
use App\Models\SocialLink;
use App\Services\PostPresenter;

/**
 * Resuelve los datos que un documento de Studio solo guarda como
 * referencia o configuración:
 *
 * - media:      ID → URL pública (vía Media::url(), nunca rutas del disco/NAS)
 * - feeds:      sección "posts" → publicaciones publicadas que cumplen su filtro
 * - categories: sección "categories" → categoría + su tip destacado
 * - entries:    clave de página → entradas visibles (timeline)
 * - pages:      clave de página → título, intro, cuerpo Tiptap y portada
 * - social:     redes visibles
 *
 * El mismo resultado alimenta el sitio público, la vista previa y el canvas
 * del editor, por lo que lo que se ve en Studio es lo que se publica.
 */
class StudioResolver
{
    public function __construct(
        private readonly StudioReferences $references,
        private readonly PostPresenter $presenter,
    ) {}

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function resolve(array $document, ?string $currentPageKey = null, ?Post $currentPost = null): array
    {
        // Al renderizar una publicación, la "página actual" es su slug y el
        // propio post se inyecta como página para que los binds (title/intro/
        // cover/page-body) funcionen sin cambios en el frontend.
        if ($currentPost !== null) {
            $currentPageKey = $currentPost->slug;
        }

        $pageKeys = $this->references->pageKeys($document);
        if ($currentPageKey !== null && $currentPost === null && $this->usesCurrentPage($document)) {
            $pageKeys[] = $currentPageKey;
        }
        $pages = $this->pages(array_values(array_unique($pageKeys)));

        $postObj = null;
        if ($currentPost !== null) {
            $pages[$currentPost->slug] = [
                'key' => $currentPost->slug,
                'title' => $currentPost->title,
                'intro' => $currentPost->excerpt,
                'body' => $currentPost->body,
                'coverMediaId' => $currentPost->cover_media_id,
            ];
            $postObj = $this->post($currentPost);
        }

        $mediaIds = $this->references->mediaIds($document);
        foreach ($pages as $page) {
            if ($page['coverMediaId'] !== null) {
                $mediaIds[] = $page['coverMediaId'];
            }
        }

        $feeds = [];
        $needsCategories = false;
        $entryPages = [];
        $needsSocial = false;

        foreach (($document['sections'] ?? []) as $section) {
            match ($section['type'] ?? null) {
                'posts' => $feeds[$section['id']] = ($currentPost !== null && ($section['props']['source'] ?? null) === 'related')
                    ? $this->relatedFeed($currentPost, $section['props'] ?? [])
                    : $this->feed($section['props'] ?? []),
                'categories' => $needsCategories = true,
                'timeline' => ($section['props']['source'] ?? '') === 'entries' && ($section['props']['page'] ?? '') !== ''
                    ? $entryPages[] = $section['props']['page']
                    : null,
                'social' => $needsSocial = true,
                default => null,
            };
            foreach (($section['slots'] ?? []) as $children) {
                foreach ((array) $children as $child) {
                    if (($child['type'] ?? null) === 'social-links') {
                        $needsSocial = true;
                    }
                }
            }
        }

        $entries = [];
        foreach (array_unique($entryPages) as $key) {
            $entries[$key] = $this->entries($key);
        }

        return [
            'media' => $this->media(array_values(array_unique($mediaIds))),
            'feeds' => (object) $feeds,
            'categories' => $needsCategories ? $this->categories() : [],
            'entries' => (object) $entries,
            'pages' => (object) $pages,
            'social' => $needsSocial ? $this->social() : [],
            'currentPage' => $currentPageKey,
            'post' => $postObj,
        ];
    }

    /**
     * Metadatos propios de una publicación (categoría, autor, fecha, tiempo de
     * lectura y galería) que consumen los bloques específicos de tips desde
     * resolved.post. Solo se incluyen imágenes de galería con URL pública.
     *
     * @return array<string, mixed>
     */
    private function post(Post $post): array
    {
        return [
            'slug' => $post->slug,
            'category' => $post->category ? [
                'name' => $post->category->name,
                'slug' => $post->category->slug,
                'color' => $post->category->color,
            ] : null,
            'author' => $post->author?->name,
            'publishedAt' => $post->published_at?->toIso8601String(),
            'readingMinutes' => $this->presenter->readingMinutes($post),
            'gallery' => $post->galleryMedia
                ->map(fn (Media $media) => ['id' => $media->id, 'url' => $media->url(), 'alt' => $media->alt_text])
                ->filter(fn (array $item) => $item['url'] !== null)
                ->values()
                ->all(),
        ];
    }

    /**
     * Resúmenes de las publicaciones relacionadas con la publicación actual,
     * usados por las secciones "posts" cuya fuente es "related".
     *
     * @param  array<string, mixed>  $props
     * @return array<int, array<string, mixed>>
     */
    private function relatedFeed(Post $post, array $props): array
    {
        $limit = max(1, min(12, (int) ($props['limit'] ?? 3)));

        return $post->relatedPosts()
            ->take($limit)
            ->map(fn (Post $related) => $this->presenter->summary($related))
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $ids
     */
    public function media(array $ids): object
    {
        if ($ids === []) {
            return (object) [];
        }

        $out = [];
        Media::query()
            ->with('variants')
            ->whereIn('id', $ids)
            ->where('status', MediaStatus::Ready)
            ->get()
            ->each(function (Media $media) use (&$out) {
                $out[$media->id] = self::mediaPayload($media);
            });

        return (object) $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function mediaPayload(Media $media): array
    {
        return [
            'id' => $media->id,
            'type' => $media->type->value,
            'mime' => $media->mime_type,
            'url' => $media->url(),
            'posterUrl' => $media->variantUrl('poster'),
            'width' => $media->width,
            'height' => $media->height,
            'duration' => $media->duration_seconds,
            'alt' => $media->alt_text,
            'filename' => $media->original_filename,
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<int, array<string, mixed>>
     */
    private function feed(array $props): array
    {
        $limit = max(1, min(12, (int) ($props['limit'] ?? 6)));

        $query = Post::query()->published()->with(['category', 'cover']);

        match ($props['source'] ?? 'latest') {
            'featured' => $query->where('is_featured', true),
            'category' => $query->whereHas('category', fn ($q) => $q->where('slug', (string) ($props['category'] ?? ''))),
            default => null,
        };

        return $query->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (Post $post) => $this->presenter->summary($post))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function categories(): array
    {
        return Category::query()
            ->where('status', 'active')
            ->orderBy('order')
            ->get()
            ->map(function (Category $category) {
                $post = Post::query()
                    ->where('category_id', $category->id)
                    ->published()
                    ->orderByDesc('is_featured')
                    ->orderByDesc('published_at')
                    ->with(['cover', 'category'])
                    ->first();

                return [
                    'category' => [
                        'name' => $category->name,
                        'slug' => $category->slug,
                        'color' => $category->color,
                        'description' => $category->description,
                    ],
                    'post' => $post ? $this->presenter->summary($post) : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function entries(string $pageKey): array
    {
        $page = Page::query()->where('key', $pageKey)->first();
        if ($page === null) {
            return [];
        }

        return $page->entries()
            ->where('is_visible', true)
            ->with('cover')
            ->get()
            ->map(fn (PageEntry $entry) => [
                'id' => $entry->id,
                'title' => $entry->title,
                'date' => $entry->entry_date?->toIso8601String(),
                'coverUrl' => $entry->cover?->url(),
                'coverAlt' => $entry->cover?->alt_text,
                'body' => $entry->body,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, array<string, mixed>>
     */
    private function pages(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        return Page::query()
            ->whereIn('key', $keys)
            ->get()
            ->mapWithKeys(fn (Page $page) => [$page->key => [
                'key' => $page->key,
                'title' => $page->title,
                'intro' => $page->intro,
                'body' => $page->body,
                'coverMediaId' => $page->cover_media_id,
            ]])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function social(): array
    {
        return SocialLink::query()
            ->visible()
            ->get(['platform', 'username', 'url', 'icon', 'open_new_tab'])
            ->toArray();
    }

    /**
     * ¿Algún bloque se vincula a "la página actual" (bindPage vacío)?
     *
     * @param  array<string, mixed>  $document
     */
    private function usesCurrentPage(array $document): bool
    {
        foreach (($document['sections'] ?? []) as $section) {
            foreach (($section['slots'] ?? []) as $children) {
                foreach ((array) $children as $child) {
                    $props = $child['props'] ?? [];
                    if (($props['bind'] ?? 'none') !== 'none' && ($props['bindPage'] ?? '') === '') {
                        return true;
                    }
                    if (($child['type'] ?? null) === 'page-body' && ($props['page'] ?? '') === '') {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
