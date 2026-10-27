<?php

namespace App\Http\Controllers\Admin\Web;

use App\Cms\Layout\DynamicBlocks;
use App\Cms\Layout\LayoutValidator;
use App\Cms\Layout\Options;
use App\Cms\Layout\Templates;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\SitePageDraft;
use App\Models\SitePageVersion;
use App\Models\SiteSection;
use App\Models\User;
use App\Services\Web\HomePage;
use App\Services\Web\MediaPresenter;
use App\Services\Web\PublicPage;
use App\Services\Web\Seo;
use App\Services\Web\SiteBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Editor visual de la portada (tipo lienzo): borrador con autoguardado,
 * vista previa firmada, publicación con historial y restauración.
 *
 * Editar el borrador requiere `content.manage`; publicar además
 * `content.publish` (SiteSectionPolicy::publish).
 */
class SiteBuilderController extends Controller
{
    private const PAGE = 'home';

    public function __construct(
        private readonly SiteBuilder $builder,
        private readonly HomePage $home,
    ) {}

    public function edit(): Response
    {
        Gate::authorize('content.manage');

        $draft = $this->builder->draft(self::PAGE);

        return Inertia::render('admin/sitio/Editor', [
            'page' => [
                'slug' => self::PAGE,
                'name' => SiteBuilder::PAGES[self::PAGE],
                'publicUrl' => url('/'),
            ],
            'draft' => $this->draftMeta($draft),
            'sections' => $this->home->resolve($draft->snapshot['sections'], editing: true),
            'library' => $this->library($draft),
            'templates' => Templates::all(),
            'dynamic' => DynamicBlocks::descriptors(),
            'icons' => Options::ICONS,
            'versions' => $this->versions(),
            'previewUrl' => URL::temporarySignedRoute('admin.sitio.editor.vista-previa', now()->addHours(4)),
            'visitPolicy' => $this->home->visitPolicy(),
            'can' => [
                'publish' => Gate::allows('publish', SiteSection::class),
                'upload' => Gate::allows('create', Media::class),
                'library' => Gate::allows('viewAny', Media::class),
            ],
        ]);
    }

    public function saveDraft(Request $request): JsonResponse
    {
        Gate::authorize('content.manage');

        $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'sections' => ['present', 'array'],
        ]);

        $draft = $this->builder->saveDraft(self::PAGE, ['sections' => $request->input('sections')], (int) $request->integer('revision'), $this->user($request));

        return response()->json([
            ...$this->draftMeta($draft),
            'resolved' => $this->resolvedExtras($draft),
        ]);
    }

    public function publish(Request $request): JsonResponse
    {
        Gate::authorize('publish', SiteSection::class);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:300']]);
        $version = $this->builder->publish(self::PAGE, $this->user($request), $data['note'] ?? null);
        $draft = $this->builder->draft(self::PAGE);

        return response()->json([
            'message' => "Publicaste la versión {$version->number}. Los cambios ya se ven en el sitio.",
            'draft' => $this->draftMeta($draft),
            'versions' => $this->versions(),
        ]);
    }

    public function restore(Request $request, SitePageVersion $version): JsonResponse
    {
        Gate::authorize('content.manage');
        abort_unless($version->page === self::PAGE, 404);

        $draft = $this->builder->restore($version, $this->user($request));

        return $this->fullDraft($draft, "Restauraste la versión {$version->number} en el borrador. Publica para que se vea en el sitio.");
    }

    public function discard(Request $request): JsonResponse
    {
        Gate::authorize('content.manage');

        $draft = $this->builder->discard(self::PAGE, $this->user($request));

        return $this->fullDraft($draft, 'Descartaste los cambios sin publicar.');
    }

    /** Vista previa del borrador tal como se verá publicado (enlace firmado). */
    public function preview(PublicPage $page, Seo $seo): \Symfony\Component\HttpFoundation\Response
    {
        Gate::authorize('content.manage');

        $draft = $this->builder->draft(self::PAGE);
        $sections = $this->home->resolve($draft->snapshot['sections']);
        $first = $sections[0] ?? null;
        $overlay = is_array($first) && ($first['layout']['settings']['overlayHeader'] ?? false) === true;

        $response = $page->render('public/Home', [
            'sections' => $sections,
            'heroImage' => is_array($first) ? $first['image'] : null,
            'preview' => true,
        ], $seo->page(noindex: true, canonical: url('/')), overlay: $overlay)
            ->toResponse(request());
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
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
    private function versions(): array
    {
        return array_values(SitePageVersion::query()->where('page', self::PAGE)->with('publisher:id,name')
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
