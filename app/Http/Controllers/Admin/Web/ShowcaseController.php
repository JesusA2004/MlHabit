<?php

namespace App\Http\Controllers\Admin\Web;

use App\Enums\PublicationStatus;
use App\Enums\VisualOrigin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ShowcaseProjectRequest;
use App\Models\ProductCategory;
use App\Models\ShowcaseProject;
use App\Services\ActivityLogger;
use App\Services\Web\Gallery;
use App\Services\Web\MediaPresenter;
use App\Services\Web\Paginated;
use App\Services\Web\PublicSite;
use App\Services\Web\Slugger;
use App\Support\Html;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración → Inspiración: proyectos de muestra (renders conceptuales
 * o proyectos reales entregados) con galería.
 */
class ShowcaseController extends Controller
{
    public function __construct(private readonly Gallery $gallery) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ShowcaseProject::class);

        $search = is_string($request->query('q')) ? trim($request->query('q')) : '';
        $status = PublicationStatus::tryFrom((string) $request->query('estado'));
        $origin = VisualOrigin::tryFrom((string) $request->query('origen'));

        $projects = ShowcaseProject::query()
            ->with(['cover.variants', 'category:id,name'])
            ->when($search !== '', fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->when($status !== null, fn ($q) => $q->where('status', $status->value))
            ->when($origin !== null, fn ($q) => $q->where('origin', $origin->value))
            ->orderByDesc('is_featured')->orderBy('sort')->orderByDesc('updated_at')
            ->paginate(24)->withQueryString();

        return Inertia::render('admin/inspiracion/Index', [
            'filters' => ['q' => $search, 'estado' => $status?->value, 'origen' => $origin?->value],
            'projects' => Paginated::of($projects, fn (ShowcaseProject $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'origin' => $p->origin->value,
                'originLabel' => $p->origin->label(),
                'status' => $p->status->value,
                'statusLabel' => $p->status->label(),
                'statusTone' => $p->status->tone(),
                'isFeatured' => $p->is_featured,
                'category' => $p->category?->name,
                'cover' => MediaPresenter::ref($p->cover),
                'publicUrl' => $p->status === PublicationStatus::Published ? '/inspiracion/'.$p->slug : null,
                'updatedAt' => $p->updated_at?->toIso8601String(),
            ]),
            'statusOptions' => PublicationStatus::options(),
            'originOptions' => VisualOrigin::options(),
            'can' => ['publish' => Gate::allows('publish', ShowcaseProject::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', ShowcaseProject::class);

        return Inertia::render('admin/inspiracion/Form', [...$this->formOptions(), 'project' => null]);
    }

    public function store(ShowcaseProjectRequest $request, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('create', ShowcaseProject::class);

        $data = $request->validated();
        $status = PublicationStatus::from($data['status']);
        if ($status === PublicationStatus::Published) {
            Gate::authorize('publish', ShowcaseProject::class);
        }

        $project = DB::transaction(function () use ($data, $status, $request) {
            $project = ShowcaseProject::query()->create([
                ...$this->attributes($data),
                'slug' => Slugger::unique(ShowcaseProject::class, $data['slug'] ?? null, $data['title'], null, 180),
                'status' => $status,
                'published_at' => $status === PublicationStatus::Published ? now() : null,
                'sort' => (int) ShowcaseProject::query()->max('sort') + 10,
            ]);
            /** @var list<array{id: int, origin?: string|null, caption?: string|null}> $gallery */
            $gallery = $data['gallery'] ?? [];
            $this->gallery->sync($project, $gallery, $request->user()?->id);

            return $project;
        });
        PublicSite::flush();

        $activity->log('inspiracion.proyecto_creado', $project, ['titulo' => $project->title, 'origen' => $project->origin->value]);

        return to_route('admin.inspiracion.edit', $project)->with('success', 'Proyecto guardado.');
    }

    public function edit(ShowcaseProject $showcase): Response
    {
        Gate::authorize('update', $showcase);
        $showcase->load('cover.variants');

        return Inertia::render('admin/inspiracion/Form', [
            ...$this->formOptions(),
            'project' => [
                'id' => $showcase->id,
                'productCategoryId' => $showcase->product_category_id,
                'title' => $showcase->title,
                'slug' => $showcase->slug,
                'summary' => $showcase->summary,
                'story' => $showcase->story,
                'locationLabel' => $showcase->location_label,
                'origin' => $showcase->origin->value,
                'cover' => MediaPresenter::ref($showcase->cover),
                'gallery' => $this->gallery->forEditor($showcase),
                'status' => $showcase->status->value,
                'isFeatured' => $showcase->is_featured,
                'seoTitle' => $showcase->seo_title,
                'seoDescription' => $showcase->seo_description,
                'publicUrl' => $showcase->status === PublicationStatus::Published ? $showcase->publicUrl() : null,
                'updatedAt' => $showcase->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(ShowcaseProjectRequest $request, ShowcaseProject $showcase, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('update', $showcase);

        $data = $request->validated();
        $status = PublicationStatus::from($data['status']);
        $this->authorizeStatusChange($showcase, $status);

        DB::transaction(function () use ($showcase, $data, $status, $request, $activity) {
            $showcase->fill([
                ...$this->attributes($data),
                'slug' => Slugger::unique(ShowcaseProject::class, $data['slug'] ?? $showcase->slug, $data['title'], $showcase->id, 180),
                'status' => $status,
            ]);
            if ($status === PublicationStatus::Published && $showcase->published_at === null) {
                $showcase->published_at = now();
            }
            $activity->logModelChanges('inspiracion.proyecto_actualizado', $showcase, ['title', 'slug', 'origin', 'status', 'is_featured', 'location_label']);
            $showcase->save();
            /** @var list<array{id: int, origin?: string|null, caption?: string|null}> $gallery */
            $gallery = $data['gallery'] ?? [];
            $this->gallery->sync($showcase, $gallery, $request->user()?->id);
        });
        PublicSite::flush();

        return back()->with('success', 'Proyecto actualizado.');
    }

    public function updateStatus(Request $request, ShowcaseProject $showcase, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('update', $showcase);

        $data = $request->validate(['status' => ['required', 'in:draft,published,archived']]);
        $status = PublicationStatus::from($data['status']);
        $this->authorizeStatusChange($showcase, $status);

        $before = $showcase->status->value;
        $showcase->forceFill([
            'status' => $status,
            'published_at' => $status === PublicationStatus::Published ? ($showcase->published_at ?? now()) : $showcase->published_at,
        ])->save();

        $activity->log('inspiracion.proyecto_estado', $showcase, ['before' => ['status' => $before], 'after' => ['status' => $status->value]]);

        return back()->with('success', $status === PublicationStatus::Published ? 'Proyecto publicado.' : 'Proyecto retirado del sitio.');
    }

    public function destroy(ShowcaseProject $showcase, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('delete', $showcase);
        if ($showcase->status === PublicationStatus::Published) {
            Gate::authorize('publish', ShowcaseProject::class);
        }

        $showcase->attachments()->delete();
        $showcase->delete();
        $activity->log('inspiracion.proyecto_eliminado', $showcase, ['titulo' => $showcase->title]);

        return to_route('admin.inspiracion.index')->with('success', 'Proyecto eliminado.');
    }

    private function authorizeStatusChange(ShowcaseProject $project, PublicationStatus $next): void
    {
        $touchesPublished = $next !== $project->status
            && ($next === PublicationStatus::Published || $project->status === PublicationStatus::Published);

        if ($touchesPublished || $project->status === PublicationStatus::Published) {
            Gate::authorize('publish', ShowcaseProject::class);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'product_category_id' => $data['product_category_id'] ?? null,
            'title' => $data['title'],
            'summary' => $data['summary'] ?? null,
            'story' => isset($data['story']) ? Html::clean((string) $data['story']) : null,
            'location_label' => $data['location_label'] ?? null,
            'origin' => VisualOrigin::from($data['origin']),
            'cover_media_id' => $data['cover_media_id'] ?? null,
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => ProductCategory::query()->ordered()->get(['id', 'name'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->values(),
            'statusOptions' => PublicationStatus::options(),
            'originOptions' => VisualOrigin::options(),
            'can' => ['publish' => Gate::allows('publish', ShowcaseProject::class)],
        ];
    }
}
