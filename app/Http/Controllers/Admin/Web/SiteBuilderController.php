<?php

namespace App\Http\Controllers\Admin\Web;

use App\Cms\Layout\DynamicBlocks;
use App\Cms\Layout\LayoutValidator;
use App\Cms\Layout\Options;
use App\Cms\Layout\Templates;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\SitePage;
use App\Models\SitePageDraft;
use App\Models\SitePageVersion;
use App\Models\SiteSection;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Web\HomePage;
use App\Services\Web\MediaPresenter;
use App\Services\Web\PublicPage;
use App\Services\Web\Seo;
use App\Services\Web\SiteBuilder;
use App\Services\Web\SitePages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Editor visual (tipo lienzo) de la portada y de las páginas propias del
 * sitio: borrador con autoguardado, vista previa firmada, publicación con
 * historial y restauración. La portada usa la clave `home`; cada página
 * propia, `pagina-{id}` (App\Models\SitePage::pageKey()).
 *
 * Editar el borrador requiere `content.manage`; publicar además
 * `content.publish` (SiteSectionPolicy::publish).
 */
class SiteBuilderController extends Controller
{
    private const HOME = 'home';

    public function __construct(
        private readonly SiteBuilder $builder,
        private readonly HomePage $home,
    ) {}

    // ------------------------------------------------------------------
    // Portada
    // ------------------------------------------------------------------

    public function edit(): Response
    {
        return $this->editor(self::HOME, null);
    }

    public function saveDraft(Request $request): JsonResponse
    {
        return $this->save($request, self::HOME);
    }

    public function publish(Request $request): JsonResponse
    {
        return $this->publishKey($request, self::HOME, null);
    }

    public function restore(Request $request, SitePageVersion $version): JsonResponse
    {
        return $this->restoreKey($request, self::HOME, $version);
    }

    public function discard(Request $request): JsonResponse
    {
        return $this->discardKey($request, self::HOME);
    }

    /** Vista previa del borrador tal como se verá publicado (enlace firmado). */
    public function preview(PublicPage $page, Seo $seo): HttpResponse
    {
        Gate::authorize('content.manage');

        $draft = $this->builder->draft(self::HOME);
        $sections = $this->home->resolve($draft->snapshot['sections']);
        $first = $sections[0] ?? null;

        $response = $page->render('public/Home', [
            'sections' => $sections,
            'heroImage' => is_array($first) ? $first['image'] : null,
            'preview' => true,
        ], $seo->page(noindex: true, canonical: url('/')), overlay: SitePages::overlay($sections))
            ->toResponse(request());
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    // ------------------------------------------------------------------
    // Páginas propias
    // ------------------------------------------------------------------

    public function editPage(SitePage $sitePage): Response
    {
        return $this->editor($sitePage->pageKey(), $sitePage);
    }

    public function saveDraftPage(Request $request, SitePage $sitePage): JsonResponse
    {
        return $this->save($request, $sitePage->pageKey());
    }

    public function publishPage(Request $request, SitePage $sitePage): JsonResponse
    {
        return $this->publishKey($request, $sitePage->pageKey(), $sitePage);
    }

    public function restorePage(Request $request, SitePage $sitePage, SitePageVersion $version): JsonResponse
    {
        return $this->restoreKey($request, $sitePage->pageKey(), $version);
    }

    public function discardPage(Request $request, SitePage $sitePage): JsonResponse
    {
        return $this->discardKey($request, $sitePage->pageKey());
    }

    public function previewPage(SitePage $sitePage, SitePages $pages): HttpResponse
    {
        Gate::authorize('content.manage');

        $draft = $this->builder->draft($sitePage->pageKey());
        $response = $pages->render($sitePage, $this->home->resolve($draft->snapshot['sections']), preview: true)
            ->toResponse(request());
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    // ------------------------------------------------------------------
    // Compartido
    // ------------------------------------------------------------------

    private function editor(string $key, ?SitePage $sitePage): Response
    {
        Gate::authorize('content.manage');

        $draft = $this->builder->draft($key);

        return Inertia::render('admin/sitio/Editor', [
            'page' => [
                'slug' => $sitePage !== null ? $sitePage->slug : self::HOME,
                'id' => $sitePage?->id,
                'kind' => $sitePage !== null ? 'page' : 'home',
                'name' => $sitePage !== null ? $sitePage->title : SiteBuilder::PAGES[self::HOME],
                'publicUrl' => $sitePage !== null ? $sitePage->publicUrl() : url('/'),
                'isPublished' => $sitePage !== null ? $sitePage->is_published : true,
                'backHref' => $sitePage !== null ? route('admin.sitio.index', ['pestana' => 'paginas-web']) : route('admin.sitio.index'),
            ],
            'draft' => $this->draftMeta($draft),
            'sections' => $this->home->resolve($draft->snapshot['sections'], editing: true),
            'library' => $this->library($draft),
            'templates' => Templates::all(),
            'dynamic' => DynamicBlocks::descriptors(),
            'icons' => Options::ICONS,
            'versions' => $this->versions($key),
            'previewUrl' => $sitePage !== null
                ? URL::temporarySignedRoute('admin.sitio.paginas-web.editor.vista-previa', now()->addHours(4), ['sitePage' => $sitePage->id])
                : URL::temporarySignedRoute('admin.sitio.editor.vista-previa', now()->addHours(4)),
            'visitPolicy' => $this->home->visitPolicy(),
            'can' => [
                'publish' => Gate::allows('publish', SiteSection::class),
                'upload' => Gate::allows('create', Media::class),
                'library' => Gate::allows('viewAny', Media::class),
            ],
        ]);
    }

    private function save(Request $request, string $key): JsonResponse
    {
        Gate::authorize('content.manage');

        $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'sections' => ['present', 'array'],
        ]);

        $draft = $this->builder->saveDraft($key, ['sections' => $request->input('sections')], (int) $request->integer('revision'), $this->user($request));

        return response()->json([
            ...$this->draftMeta($draft),
            'resolved' => $this->resolvedExtras($draft),
        ]);
    }

    private function publishKey(Request $request, string $key, ?SitePage $sitePage): JsonResponse
    {
        Gate::authorize('publish', SiteSection::class);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:300']]);
        $version = $this->builder->publish($key, $this->user($request), $data['note'] ?? null);
        $draft = $this->builder->draft($key);

        $message = "Publicaste la versión {$version->number}. Los cambios ya se ven en el sitio.";
        if ($sitePage !== null && ! $sitePage->is_published) {
            // Publicar desde el editor también hace visible la página.
            $sitePage->forceFill(['is_published' => true, 'published_at' => now(), 'updated_by' => $request->user()?->id])->save();
            app(ActivityLogger::class)->log('sitio.pagina_web_publicada', $sitePage, ['slug' => $sitePage->slug]);
            $message = "Publicaste la versión {$version->number}. La página ya se ve en {$sitePage->path()}.";
        }

        return response()->json([
            'message' => $message,
            'draft' => $this->draftMeta($draft),
            'versions' => $this->versions($key),
        ]);
    }

    private function restoreKey(Request $request, string $key, SitePageVersion $version): JsonResponse
    {
        Gate::authorize('content.manage');
        abort_unless($version->page === $key, 404);

        $draft = $this->builder->restore($version, $this->user($request));

        return $this->fullDraft($draft, "Restauraste la versión {$version->number} en el borrador. Publica para que se vea en el sitio.");
    }

    private function discardKey(Request $request, string $key): JsonResponse
    {
        Gate::authorize('content.manage');

        $draft = $this->builder->discard($key, $this->user($request));

        return $this->fullDraft($draft, 'Descartaste los cambios sin publicar.');
    }

    private function fullDraft(SitePageDraft $draft, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'draft' => $this->draftMeta($draft),
            'sections' => $this->home->resolve($draft->snapshot['sections'], editing: true),
            'library' => $this->library($draft),
        ]);
    }

    /** @return array<string, mixed> */
    private function draftMeta(SitePageDraft $draft): array
    {
        $draft->loadMissing('editor:id,name');

        return [
            'revision' => $draft->revision,
            'savedAt' => $draft->updated_at?->toIso8601String(),
            'savedBy' => $draft->editor?->name,
            'hasChanges' => $this->builder->hasUnpublishedChanges($draft),
        ];
    }

    /**
     * Datos resueltos (imágenes, videos, listas vivas) por sección, para que
     * el editor refresque lo que depende del servidor sin tocar el lienzo.
     *
     * @return array<string, array<string, mixed>>
     */
    private function resolvedExtras(SitePageDraft $draft): array
    {
        $out = [];
        foreach ($this->home->resolve($draft->snapshot['sections'], editing: true) as $section) {
            $out[$section['key']] = [
                'images' => $section['images'],
                'videos' => $section['videos'],
                'data' => $section['data'],
            ];
        }

        return $out;
    }

    /**
     * Referencias de la biblioteca para los medios usados en el borrador
     * (id → referencia; en JSON las claves son texto).
     *
     * @return array<int, array<string, mixed>|null>
     */
    private function library(SitePageDraft $draft): array
    {
        $ids = [];
        foreach ($draft->snapshot['sections'] as $section) {
            $ids = [...$ids, ...LayoutValidator::mediaIds(is_array($section['layout'] ?? null) ? $section['layout'] : null)];
        }
        if ($ids === []) {
            return [];
        }

        return Media::query()->with('variants')->whereIn('id', array_unique($ids))->get()
            ->mapWithKeys(fn (Media $m) => [(string) $m->id => MediaPresenter::ref($m)])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function versions(string $key): array
    {
        return array_values(SitePageVersion::query()->where('page', $key)->with('publisher:id,name')
            ->orderByDesc('number')->limit(50)->get()
            ->map(fn (SitePageVersion $v) => [
                'id' => $v->id,
                'number' => $v->number,
                'note' => $v->note,
                'publishedAt' => $v->published_at->toIso8601String(),
                'publishedBy' => $v->publisher?->name,
                'sections' => count($v->snapshot['sections']),
            ])->all());
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
