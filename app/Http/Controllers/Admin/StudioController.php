<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\SocialLink;
use App\Models\StudioPage;
use App\Models\StudioVersion;
use App\Services\ActivityLogger;
use App\Services\PublicSiteDataService;
use App\Studio\StudioPagePresenter;
use App\Studio\StudioResolver;
use App\Studio\StudioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * MR. INSIGHT Studio (/admin/studio): constructor visual por bloques.
 *
 * Las operaciones del editor (autoguardado, resolución de datos,
 * publicación, restauración) responden JSON para no recargar el editor;
 * el listado, la creación y la vista previa usan Inertia.
 */
class StudioController extends Controller
{
    public function __construct(private readonly StudioService $studio) {}

    public function index(): Response
    {
        $this->authorizeStudio();
        $this->studio->ensureSystemPages();

        $pages = StudioPage::query()
            ->with(['publishedVersion:id,number,published_at', 'draftEditor:id,name'])
            ->withCount('versions')
            ->orderByRaw("case when kind = 'system' then 0 else 1 end")
            ->orderBy('id')
            ->get();

        return Inertia::render('admin/studio/Index', [
            'pages' => $pages->map(fn (StudioPage $page) => [
                'id' => $page->id,
                'key' => $page->key,
                'kind' => $page->kind,
                'title' => $page->title,
                'path' => $page->publicPath(),
                'isPublished' => $page->published_version_id !== null,
                'hasChanges' => $page->hasUnpublishedChanges(),
                'publishedAt' => $page->published_at?->toIso8601String(),
                'publishedNumber' => $page->publishedVersion?->number,
                'draftUpdatedAt' => $page->draft_updated_at?->toIso8601String(),
                'draftUpdatedBy' => $page->draftEditor?->name,
                'versionsCount' => $page->versions_count,
                'sectionsCount' => count($page->draft['sections'] ?? []),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeStudio();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'slug' => [
                'required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(StudioPage::RESERVED_SLUGS),
                Rule::unique('studio_pages', 'slug'),
            ],
        ], [
            'slug.regex' => 'Usa solo minúsculas, números y guiones (ej. "mi-taller").',
            'slug.not_in' => 'Esa dirección está reservada por el sitio.',
            'slug.unique' => 'Ya existe una página con esa dirección.',
        ]);

        $page = $this->studio->createCustomPage($data['title'], $data['slug']);

        return redirect()->route('admin.studio.edit', $page)->with('success', 'Página creada. Empieza a diseñarla.');
    }

    public function edit(StudioPage $page, StudioResolver $resolver): Response
    {
        $this->authorizeStudio();

        $document = $this->studio->draftDocument($page);
        $published = $this->studio->publishedDocument($page);
        $cmsKey = $page->isSystem() && Page::query()->where('key', $page->key)->exists() ? $page->key : null;

        // La plantilla de Tips se resuelve contra una publicación de muestra.
        $sample = $page->key === 'tip' ? $this->sampleTipPost() : null;

        return Inertia::render('admin/studio/Editor', [
            'page' => $this->pagePayload($page),
            'document' => $document,
            'published' => $published,
            'resolved' => $resolver->resolve($document, $cmsKey, $sample),
            'versions' => $this->versionsPayload($page),
            'pages' => StudioPage::query()->orderBy('id')->get(['id', 'title', 'key', 'kind'])->map(fn (StudioPage $p) => [
                'id' => $p->id, 'title' => $p->title, 'key' => $p->key, 'kind' => $p->kind, 'path' => $p->publicPath(),
            ]),
            'options' => [
                'categories' => Category::query()->orderBy('order')->get(['slug', 'name'])->map(fn ($c) => ['value' => $c->slug, 'label' => $c->name]),
                'cmsPages' => Page::query()->orderBy('title')->get(['key', 'title'])->map(fn ($p) => ['value' => $p->key, 'label' => $p->title]),
                'social' => SocialLink::query()->orderBy('order')->pluck('platform'),
                'links' => $this->internalLinks(),
                'currentCmsPage' => $cmsKey,
            ],
            'canvasUrl' => route('admin.studio.canvas', $page),
            'previewUrl' => route('admin.studio.preview', $page),
        ]);
    }

    /**
     * Documento del iframe del canvas: misma plantilla pública, pero el
     * contenido llega por postMessage desde el editor.
     */
    public function canvas(StudioPage $page, PublicSiteDataService $siteData, StudioPagePresenter $presenter): Response
    {
        $this->authorizeStudio();

        $sample = $page->key === 'tip' ? $this->sampleTipPost() : null;

        return Inertia::render('public/StudioCanvas', [
            ...$siteData->base(),
            'studio' => $presenter->payload($page, $this->studio->draftDocument($page), $sample),
        ]);
    }

    public function saveDraft(Request $request, StudioPage $page): JsonResponse
    {
        $this->authorizeStudio();

        $request->validate([
            'document' => ['required', 'array'],
            'revision' => ['required', 'integer', 'min:0'],
            'force' => ['sometimes', 'boolean'],
        ]);

        $result = $this->studio->saveDraft($page, $request->input('document'), (int) $request->input('revision'), $request->boolean('force'));

        return match ($result['status']) {
            'invalid' => response()->json(['message' => 'El documento contiene valores no válidos.', 'errors' => $result['errors']], 422),
            'conflict' => response()->json([
                'message' => 'Otra sesión guardó cambios en esta página.',
                'revision' => $result['revision'],
            ], 409),
            default => response()->json([
                'revision' => $result['revision'],
                'savedAt' => now()->toIso8601String(),
            ]),
        };
    }

    public function resolve(Request $request, StudioPage $page, StudioResolver $resolver): JsonResponse
    {
        $this->authorizeStudio();
        $request->validate(['document' => ['required', 'array']]);

        // La plantilla de Tips se resuelve contra una publicación de muestra.
        if ($page->key === 'tip') {
            return response()->json($resolver->resolve((array) $request->input('document'), null, $this->sampleTipPost()));
        }

        $cmsKey = $page->isSystem() ? $page->key : null;

        return response()->json($resolver->resolve((array) $request->input('document'), $cmsKey));
    }

    public function publish(Request $request, StudioPage $page): JsonResponse
    {
        $this->authorizeStudio();
        $data = $request->validate(['summary' => ['nullable', 'string', 'max:255']]);

        $result = $this->studio->publish($page, $data['summary'] ?? null);

        if ($result['status'] === 'invalid') {
            return response()->json(['message' => 'No se puede publicar todavía.', 'problems' => $result['errors']], 422);
        }

        $page->refresh();

        return response()->json([
            'page' => $this->pagePayload($page),
            'published' => $this->studio->publishedDocument($page),
            'versions' => $this->versionsPayload($page),
        ]);
    }

    public function discard(StudioPage $page): JsonResponse
    {
        $this->authorizeStudio();
        $page = $this->studio->discardDraft($page);

        return response()->json([
            'page' => $this->pagePayload($page),
            'document' => $this->studio->draftDocument($page),
        ]);
    }

    public function version(StudioPage $page, StudioVersion $version): JsonResponse
    {
        $this->authorizeStudio();
        abort_unless($version->studio_page_id === $page->id, 404);

        return response()->json(['document' => $version->document, 'number' => $version->number]);
    }

    public function restore(StudioPage $page, StudioVersion $version): JsonResponse
    {
        $this->authorizeStudio();
        abort_unless($version->studio_page_id === $page->id, 404);

        $page = $this->studio->restore($page, $version);

        return response()->json([
            'page' => $this->pagePayload($page),
            'document' => $this->studio->draftDocument($page),
            'versions' => $this->versionsPayload($page),
        ]);
    }

    public function preview(StudioPage $page, PublicSiteDataService $siteData, StudioPagePresenter $presenter): Response
    {
        $this->authorizeStudio();

        $sample = $page->key === 'tip' ? $this->sampleTipPost() : null;

        return Inertia::render('public/StudioPage', [
            ...$siteData->base(),
            'studio' => $presenter->payload($page, $this->studio->draftDocument($page), $sample),
            'previewing' => true,
        ]);
    }

    public function share(StudioPage $page, ActivityLogger $activity): JsonResponse
    {
        $this->authorizeStudio();

        $expires = now()->addHours(48);
        $url = URL::temporarySignedRoute('studio.shared-preview', $expires, ['page' => $page->id]);
        $activity->log('studio.vista_previa_compartida', $page, ['page' => $page->title]);

        return response()->json(['url' => $url, 'expiresAt' => $expires->toIso8601String()]);
    }

    public function updateSettings(Request $request, StudioPage $page, ActivityLogger $activity): JsonResponse
    {
        $this->authorizeStudio();

        $rules = [
            'title' => ['required', 'string', 'max:120'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:255'],
        ];
        if (! $page->isSystem()) {
            $rules['slug'] = [
                'required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(StudioPage::RESERVED_SLUGS),
                Rule::unique('studio_pages', 'slug')->ignore($page->id),
            ];
        }

        $page->update($request->validate($rules));
        $activity->log('studio.ajustes_actualizados', $page, ['page' => $page->title]);

        return response()->json(['page' => $this->pagePayload($page->refresh())]);
    }

    public function destroy(StudioPage $page, ActivityLogger $activity): RedirectResponse
    {
        $this->authorizeStudio();
        abort_if($page->isSystem(), 403, 'Las páginas del sistema no se pueden eliminar.');

        $title = $page->title;
        $page->delete();
        $activity->log('studio.pagina_eliminada', null, ['page' => $title]);

        return redirect()->route('admin.studio.index')->with('success', "Página «{$title}» eliminada.");
    }

    private function authorizeStudio(): void
    {
        abort_unless(request()->user()?->canManagePosts(), 403);
    }

    /**
     * Publicación de muestra para previsualizar la plantilla de Tips.
     */
    private function sampleTipPost(): ?Post
    {
        return Post::query()
            ->published()
            ->with(['category', 'cover', 'author', 'galleryMedia'])
            ->latest('published_at')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function pagePayload(StudioPage $page): array
    {
        $page->loadMissing(['publishedVersion:id,number', 'draftEditor:id,name']);

        return [
            'id' => $page->id,
            'key' => $page->key,
            'kind' => $page->kind,
            'title' => $page->title,
            'slug' => $page->slug,
            'seoTitle' => $page->seo_title,
            'seoDescription' => $page->seo_description,
            'path' => $page->publicPath(),
            'revision' => $page->draft_revision,
            'draftUpdatedAt' => $page->draft_updated_at?->toIso8601String(),
            'draftUpdatedBy' => $page->draftEditor?->name,
            'publishedAt' => $page->published_at?->toIso8601String(),
            'publishedNumber' => $page->publishedVersion?->number,
            'hasChanges' => $page->hasUnpublishedChanges(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function versionsPayload(StudioPage $page): array
    {
        return $page->versions()
            ->with('author:id,name')
            ->limit(50)
            ->get(['id', 'studio_page_id', 'number', 'kind', 'summary', 'created_by', 'published_at', 'created_at'])
            ->map(fn (StudioVersion $v) => [
                'id' => $v->id,
                'number' => $v->number,
                'kind' => $v->kind,
                'summary' => $v->summary,
                'author' => $v->author?->name,
                'createdAt' => $v->created_at?->toIso8601String(),
                'isCurrent' => $v->id === $page->published_version_id,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function internalLinks(): array
    {
        $links = [
            ['value' => '/', 'label' => 'Inicio'],
            ['value' => '/tips', 'label' => 'Tips'],
            ['value' => '/mi-historia', 'label' => 'Mi historia'],
            ['value' => '/mi-proceso', 'label' => 'Mi proceso'],
            ['value' => '/redes', 'label' => 'Redes'],
            ['value' => '/aviso-de-privacidad', 'label' => 'Aviso de privacidad'],
        ];

        foreach (Category::query()->where('status', 'active')->orderBy('order')->get(['slug', 'name']) as $category) {
            $links[] = ['value' => "/tips?categoria={$category->slug}", 'label' => "Tips · {$category->name}"];
        }

        foreach (StudioPage::query()->where('kind', 'custom')->whereNotNull('slug')->get(['slug', 'title']) as $custom) {
            $links[] = ['value' => '/'.$custom->slug, 'label' => $custom->title];
        }

        return $links;
    }
}
