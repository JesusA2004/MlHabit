<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SocialLinkRequest;
use App\Models\SocialLink;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SocialLinkController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', SocialLink::class);

        $links = SocialLink::query()->orderBy('order')->get();

        return Inertia::render('admin/redes-sociales/Index', [
            'links' => $links,
            'can' => ['create' => Gate::allows('create', SocialLink::class)],
        ]);
    }

    public function store(SocialLinkRequest $request, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('create', SocialLink::class);

        $link = SocialLink::query()->create($request->validated());
        $this->forgetPublicCache();

        $activity->log('red_social.creada', $link, ['platform' => $link->platform]);

        return back()->with('success', 'Red social agregada.');
    }

    public function update(SocialLinkRequest $request, SocialLink $socialLink, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('update', $socialLink);

        $socialLink->update($request->validated());
        $this->forgetPublicCache();

        $activity->log('red_social.actualizada', $socialLink, ['platform' => $socialLink->platform]);

        return back()->with('success', 'Red social actualizada.');
    }

    public function destroy(SocialLink $socialLink, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('delete', $socialLink);

        $platform = $socialLink->platform;
        $socialLink->delete();
        $this->forgetPublicCache();

        $activity->log('red_social.eliminada', $socialLink, ['platform' => $platform]);

        return back()->with('success', 'Red social eliminada.');
    }

    private function forgetPublicCache(): void
    {
        Cache::forget('public.social_links.visible');
    }
}
