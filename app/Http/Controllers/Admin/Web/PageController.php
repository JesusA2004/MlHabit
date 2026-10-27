<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\PageRequest;
use App\Models\Page;
use App\Services\ActivityLogger;
use App\Services\Web\Slugger;
use App\Support\Html;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Páginas legales e informativas. El cuerpo se sanea en el modelo. Cada
 * publicación incrementa `version`; editar una página ya publicada cuenta
 * como nueva publicación (requiere `content.publish`).
 */
class PageController extends Controller
{
    public function create(): Response
    {
        Gate::authorize('create', Page::class);

        return Inertia::render('admin/sitio/paginas/Form', [
            'page' => null,
            'can' => ['publish' => Gate::allows('publish', Page::class)],
        ]);
    }

    public function store(PageRequest $request, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('create', Page::class);

        $data = $request->validated();
        $page = Page::query()->create([
            ...$data,
            'slug' => Slugger::unique(Page::class, $data['slug'] ?? null, $data['title']),
            'version' => 1,
            'is_published' => false,
            'updated_by' => $request->user()?->id,
        ]);

        $activity->log('sitio.pagina_creada', $page, ['slug' => $page->slug]);

        return to_route('admin.sitio.paginas.edit', $page)->with('success', 'Página creada como borrador.');
    }

    public function edit(Page $page): Response
    {
        Gate::authorize('update', $page);

        return Inertia::render('admin/sitio/paginas/Form', [
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'summary' => $page->summary,
                'body' => $page->body,
                'version' => $page->version,
                'isPublished' => $page->is_published,
                'showInFooter' => $page->show_in_footer,
                'publishedAt' => $page->published_at?->toIso8601String(),
                'updatedAt' => $page->updated_at?->toIso8601String(),
                'publicUrl' => $page->is_published ? $page->publicUrl() : null,
            ],
            'can' => ['publish' => Gate::allows('publish', Page::class)],
        ]);
    }

    public function update(PageRequest $request, Page $page, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('update', $page);
        if ($page->is_published) {
            Gate::authorize('publish', Page::class);
        }

        $data = $request->validated();
        $data['body'] = Html::clean((string) $data['body']);
        $page->fill([
            ...$data,
            'slug' => Slugger::unique(Page::class, $data['slug'] ?? $page->slug, $data['title'], $page->id),
            'updated_by' => $request->user()?->id,
        ]);

        $contentChanged = $page->isDirty(['title', 'summary', 'body']);
        if ($page->is_published && $contentChanged) {
            $page->version++;
            $page->published_at = now();
        }

        $activity->logModelChanges('sitio.pagina_actualizada', $page, ['title', 'slug', 'summary', 'show_in_footer', 'version']);
        $page->save();

        return back()->with('success', $page->is_published && $contentChanged
            ? "Cambios publicados (versión {$page->version})."
            : 'Cambios guardados.');
    }

    public function publish(Request $request, Page $page, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('publish', Page::class);

        if (trim(strip_tags($page->body)) === '') {
            return back()->with('error', 'La página no tiene contenido. Escribe el texto antes de publicarla.');
        }

        $page->forceFill([
            'is_published' => true,
            'published_at' => now(),
            'version' => $page->published_at === null ? $page->version : $page->version + 1,
            'updated_by' => $request->user()?->id,
        ])->save();

        $activity->log('sitio.pagina_publicada', $page, ['version' => $page->version]);

        return back()->with('success', "Página publicada (versión {$page->version}).");
    }

    public function unpublish(Request $request, Page $page, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('publish', Page::class);

        $page->forceFill(['is_published' => false, 'updated_by' => $request->user()?->id])->save();
        $activity->log('sitio.pagina_despublicada', $page, ['version' => $page->version]);

        return back()->with('success', 'La página ya no es visible en el sitio.');
    }

    public function destroy(Page $page, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('delete', $page);
        if ($page->is_published) {
            return back()->with('error', 'Despublica la página antes de eliminarla.');
        }

        $page->delete();
        $activity->log('sitio.pagina_eliminada', $page, ['slug' => $page->slug]);

        return to_route('admin.sitio.index', ['pestana' => 'paginas'])->with('success', 'Página eliminada.');
    }
}
