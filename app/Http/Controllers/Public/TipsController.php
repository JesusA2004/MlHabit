<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\PostPresenter;
use App\Services\PublicSiteDataService;
use App\Studio\StudioPagePresenter;
use App\Studio\StudioService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TipsController extends Controller
{
    public function index(Request $request, PublicSiteDataService $siteData, PostPresenter $presenter): Response
    {
        $categorySlug = $request->query('categoria');

        $query = Post::query()
            ->published()
            ->with(['category', 'cover'])
            ->orderByDesc('published_at');

        if ($categorySlug && $categorySlug !== 'todos') {
            $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
        }

        $posts = $query->paginate(9)->withQueryString();

        return Inertia::render('public/Tips', [
            ...$siteData->base(),
            'activeCategory' => $categorySlug ?? 'todos',
            'posts' => [
                'data' => $posts->getCollection()->map(fn (Post $post) => $presenter->summary($post)),
                'currentPage' => $posts->currentPage(),
                'lastPage' => $posts->lastPage(),
                'nextPageUrl' => $posts->nextPageUrl(),
                'prevPageUrl' => $posts->previousPageUrl(),
                'total' => $posts->total(),
            ],
        ]);
    }

    public function show(string $slug, PublicSiteDataService $siteData, StudioService $studio, StudioPagePresenter $presenter): Response
    {
        $post = Post::query()
            ->published()
            ->where('slug', $slug)
            ->with(['category', 'cover', 'author', 'galleryMedia'])
            ->firstOrFail();

        $page = $studio->ensureSystemPage('tip');

        return Inertia::render('public/PostShow', [
            ...$siteData->base(),
            'studio' => $presenter->payload($page, $studio->publishedDocument($page) ?? $studio->draftDocument($page), $post),
        ]);
    }
}
