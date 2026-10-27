<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageEntryRequest;
use App\Models\Page;
use App\Models\PageEntry;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PageEntryController extends Controller
{
    public function store(PageEntryRequest $request, ActivityLogger $activity): RedirectResponse
    {
        $page = Page::query()->where('key', 'mi-proceso')->firstOrFail();
        Gate::authorize('update', $page);

        $entry = $page->entries()->create($request->validated());
        $activity->log('pagina_entrada.creada', $entry, ['title' => $entry->title]);

        return back()->with('success', 'Entrada agregada.');
    }

    public function update(PageEntryRequest $request, PageEntry $pageEntry, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('update', $pageEntry->page);

        $pageEntry->update($request->validated());
        $activity->log('pagina_entrada.actualizada', $pageEntry, ['title' => $pageEntry->title]);

        return back()->with('success', 'Entrada actualizada.');
    }

    public function destroy(PageEntry $pageEntry, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('update', $pageEntry->page);

        $title = $pageEntry->title;
        $pageEntry->delete();
        $activity->log('pagina_entrada.eliminada', null, ['title' => $title]);

        return back()->with('success', 'Entrada eliminada.');
    }
}
