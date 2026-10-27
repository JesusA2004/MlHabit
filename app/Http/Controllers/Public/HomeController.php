<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\PostPresenter;
use App\Services\PublicSiteDataService;
use App\Studio\StudioPagePresenter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inicio: la composición la define la versión publicada en Studio
 * (/admin/studio). `tips` se conserva como resumen de las últimas
 * publicaciones para integraciones y compatibilidad.
 */
class HomeController extends Controller
{
    public function __invoke(PublicSiteDataService $siteData, PostPresenter $presenter, StudioPagePresenter $studio): Response
    {
        $latestPosts = Post::query()
            ->published()
            ->with(['category', 'cover'])
            ->orderByDesc('published_at')
            ->limit(6)
            ->get()
            ->map(fn (Post $post) => $presenter->summary($post));

        return Inertia::render('public/Home', [
            ...$siteData->base(),
            'studio' => $studio->published('home'),
            'tips' => ['posts' => $latestPosts],
        ]);
    }
}
