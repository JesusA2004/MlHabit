<?php

namespace App\Http\Controllers\Admin\Web;

use App\Cms\Layout\PageTemplates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\SitePageRequest;
use App\Models\SitePage;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Web\SiteBuilder;
use App\Services\Web\SitePages;
use App\Services\Web\Slugger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Páginas propias del sitio (Sitio web → Páginas): crear desde una
 * plantilla, editar sus datos y SEO, duplicar, publicar/despublicar y
 * eliminar. El contenido se edita en el editor visual
 * (SiteBuilderController::editPage).
 */
class SitePageController extends Controller
{
    public function __construct(
        private readonly SiteBuilder $builder,
        private readonly ActivityLogger $activity,
    ) {}

    public function store(SitePageRequest $request): RedirectResponse
    {
        Gate::authorize('create', SitePage::class);

        $data = $request->validated();
        $user = $request->user();

        $page = DB::transaction(function () use ($data, $user) {
            $page = SitePage::query()->create([
                'title' => $data['title'],
                'slug' => self::slug($data['slug'] ?? null, $data['title']),
                'seo_title' => $data['seo_title'] ?? null,
                'seo_description' => $data['seo_description'] ?? null,
                'og_media_id' => $data['og_media_id'] ?? null,
                'template' => $data['template'],
                'is_published' => false,
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ]);
            $this->builder->seed($page->pageKey(), PageTemplates::sections($data['template'], $page->title), $user);

            return $page;
        });

        $this->activity->log('sitio.pagina_web_creada', $page, ['slug' => $page->slug, 'template' => $page->template]);

        return to_route('admin.sitio.paginas-web.editor.edit', $page)->with('success', 'Página creada como borrador. Edita su contenido y publícala cuando esté lista.');
    }

    public function update(SitePageRequest $request, SitePage $sitePage): RedirectResponse
    {
        Gate::authorize('update', $sitePage);

        $data = $request->validated();
        $sitePage->fill([
            'title' => $data['title'],
            'slug' => self::slug($data['slug'] ?? $sitePage->slug, $data['title'], $sitePage->id),
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
            'og_media_id' => $data['og_media_id'] ?? null,
            'updated_by' => $request->user()?->id,
        ]);

        // Cambiar la dirección de una página visible equivale a publicar.
        if ($sitePage->is_published && $sitePage->isDirty('slug')) {
            Gate::authorize('publish', SitePage::class);
        }

        $this->activity->logModelChanges('sitio.pagina_web_actualizada', $sitePage, ['title', 'slug', 'seo_title', 'seo_description', 'og_media_id']);
        $sitePage->save();

        return back()->with('success', 'Cambios guardados.');
    }

    public function duplicate(Request $request, SitePage $sitePage): RedirectResponse
    {
        Gate::authorize('create', SitePage::class);

        $user = $request->user();
        $copy = DB::transaction(function () use ($sitePage, $user) {
            $title = mb_substr($sitePage->title.' (copia)', 0, 160);
            $copy = SitePage::query()->create([
                'title' => $title,
                'slug' => self::slug($sitePage->slug.'-copia', $title),
                'seo_title' => $sitePage->seo_title,
                'seo_description' => $sitePage->seo_description,
                'og_media_id' => $sitePage->og_media_id,
                'template' => $sitePage->template,
                'is_published' => false,
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ]);
            $this->builder->seed($copy->pageKey(), $this->builder->draft($sitePage->pageKey())->snapshot['sections'], $user);

            return $copy;
        });

        $this->activity->log('sitio.pagina_web_duplicada', $copy, ['from' => $sitePage->slug, 'slug' => $copy->slug]);

        return to_route('admin.sitio.index', ['pestana' => 'paginas-web'])->with('success', "Duplicaste «{$sitePage->title}». La copia quedó como borrador.");
    }

    public function publish(Request $request, SitePage $sitePage): RedirectResponse
    {
        Gate::authorize('publish', SitePage::class);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        // Sin versión publicada se publica el borrador actual.
        if (! $this->builder->hasVersions($sitePage->pageKey())) {
            $this->builder->publish($sitePage->pageKey(), $user, 'Primera publicación');
        }

        $sitePage->forceFill(['is_published' => true, 'published_at' => now(), 'updated_by' => $user->id])->save();
        $this->activity->log('sitio.pagina_web_publicada', $sitePage, ['slug' => $sitePage->slug]);

        return back()->with('success', "Página publicada en {$sitePage->path()}.");
    }

    public function unpublish(Request $request, SitePage $sitePage): RedirectResponse
    {
        Gate::authorize('publish', SitePage::class);

        $sitePage->forceFill(['is_published' => false, 'updated_by' => $request->user()?->id])->save();
        $this->activity->log('sitio.pagina_web_despublicada', $sitePage, ['slug' => $sitePage->slug]);

        return back()->with('success', 'La página ya no es visible en el sitio. Los enlaces del menú hacia ella se ocultan.');
    }

    public function destroy(SitePage $sitePage): RedirectResponse
    {
        Gate::authorize('delete', $sitePage);
        if ($sitePage->is_published) {
            return back()->with('error', 'Despublica la página antes de eliminarla.');
        }

        DB::transaction(function () use ($sitePage) {
            $this->builder->forget($sitePage->pageKey());
            $sitePage->delete();
        });
        $this->activity->log('sitio.pagina_web_eliminada', $sitePage, ['slug' => $sitePage->slug]);

        return to_route('admin.sitio.index', ['pestana' => 'paginas-web'])->with('success', 'Página eliminada.');
    }

    /** Slug único que tampoco choque con una ruta del sitio. */
    private static function slug(?string $preferred, string $title, ?int $ignoreId = null): string
    {
        $slug = Slugger::unique(SitePage::class, $preferred, $title, $ignoreId, 120);
        $n = 2;
        while (SitePages::isReserved($slug)) {
            $slug = Slugger::unique(SitePage::class, $slug.'-'.$n++, $title, $ignoreId, 120);
        }

        return $slug;
    }
}
