<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\SocialLinkRequest;
use App\Models\SocialLink;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Redes sociales del sitio (pestaña "Redes sociales" de Sitio web).
 */
class SocialLinkController extends Controller
{
    public function store(SocialLinkRequest $request, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('create', SocialLink::class);

        $data = $request->validated();
        $data['order'] ??= (int) SocialLink::query()->max('order') + 1;
        $link = SocialLink::query()->create($data);

        $activity->log('sitio.red_social_creada', $link, ['platform' => $link->platform]);

        return back()->with('success', 'Red social agregada.');
    }

    public function update(SocialLinkRequest $request, SocialLink $socialLink, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('update', $socialLink);

        $socialLink->fill($request->validated());
        $activity->logModelChanges('sitio.red_social_actualizada', $socialLink);
        $socialLink->save();

        return back()->with('success', 'Red social actualizada.');
    }

    public function reorder(Request $request, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('create', SocialLink::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:social_links,id'],
        ]);

        DB::transaction(function () use ($data) {
            foreach (array_values($data['ids']) as $index => $id) {
                SocialLink::query()->whereKey($id)->first()?->update(['order' => $index]);
            }
        });

        $activity->log('sitio.redes_reordenadas', null, ['ids' => $data['ids']]);

        return back()->with('success', 'Orden actualizado.');
    }

    public function destroy(SocialLink $socialLink, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('delete', $socialLink);

        $platform = $socialLink->platform;
        $socialLink->delete();

        $activity->log('sitio.red_social_eliminada', $socialLink, ['platform' => $platform]);

        return back()->with('success', 'Red social eliminada.');
    }
}
