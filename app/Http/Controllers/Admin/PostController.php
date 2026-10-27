<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PostRequest;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\PostPresenter;
use App\Services\PostService;
use App\Services\PublicSiteDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Post::class);

        $posts = Post::query()
            ->with(['category', 'author', 'cover'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('categoria'), fn ($q, $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->when($request->query('buscar'), fn ($q, $term) => $q->where('title', 'like', '%'.(is_string($term) ? $term : '').'%'))
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/publicaciones/Index', [
            'posts' => [
                'data' => $posts->getCollection()->map(fn (Post $post) => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'slug' => $post->slug,
                    'status' => $post->status->value,
                    'statusLabel' => $post->status->label(),
                    'isFeatured' => $post->is_featured,
                    'category' => $post->category?->name,
                    'author' => $post->author?->name,
                    'coverUrl' => $post->cover?->url(),
                    'publishedAt' => $post->published_at?->toIso8601String(),
                    'scheduledAt' => $post->scheduled_at?->toIso8601String(),
                    'updatedAt' => $post->updated_at?->toIso8601String(),
                ]),
                'currentPage' => $posts->currentPage(),
                'lastPage' => $posts->lastPage(),
                'nextPageUrl' => $posts->nextPageUrl(),
                'prevPageUrl' => $posts->previousPageUrl(),
                'total' => $posts->total(),
            ],
            'filters' => $request->only(['status', 'categoria', 'buscar']),
            'categories' => Category::query()->orderBy('order')->get(['id', 'name', 'slug']),
            'can' => ['create' => Gate::allows('create', Post::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Post::class);

        return Inertia::render('admin/publicaciones/Form', [
            'post' => null,
            'categories' => Category::query()->where('status', 'active')->orderBy('order')->get(['id', 'name', 'slug']),
        ]);
    }

    public function store(PostRequest $request, PostService $posts, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('create', Post::class);

        $user = $request->user();
        abort_if(! $user instanceof User, 401);

        $post = $posts->create($request->validated(), $user->id);

        $activity->log('publicacion.creada', $post, ['title' => $post->title]);

        return to_route('admin.publicaciones.edit', $post)->with('success', 'Borrador guardado.');
    }

    public function edit(Post $post): Response
    {
        Gate::authorize('update', $post);

        $post->load(['category', 'cover', 'galleryMedia', 'media']);

        return Inertia::render('admin/publicaciones/Form', [
            'post' => $this->postForEdit($post),
            'categories' => Category::query()->where('status', 'active')->orderBy('order')->get(['id', 'name', 'slug']),
        ]);
    }

    public function update(PostRequest $request, Post $post, PostService $posts, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('update', $post);

        $posts->update($post, $request->validated());

        $activity->log('publicacion.actualizada', $post, ['title' => $post->title]);

        return back()->with('success', 'Cambios guardados.');
    }

    public function autosave(Request $request, Post $post): RedirectResponse
    {
        Gate::authorize('update', $post);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'array'],
        ]);

        $post->revisions()->create([
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'excerpt' => $validated['excerpt'] ?? null,
            'body' => $validated['body'] ?? [],
            'type' => 'autosave',
        ]);

        return back();
    }

    public function destroy(Post $post, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('delete', $post);

        $title = $post->title;
        $post->delete();

        $activity->log('publicacion.eliminada', $post, ['title' => $title]);

        return to_route('admin.publicaciones.index')->with('success', "«{$title}» eliminada.");
    }

    public function preview(Post $post, PostPresenter $presenter, PublicSiteDataService $siteData): Response
    {
        Gate::authorize('view', $post);

        $post->load(['category', 'cover', 'author', 'galleryMedia']);

        return Inertia::render('public/PostShow', [
            ...$siteData->base(),
            'post' => $presenter->detail($post),
            'related' => $presenter->related($post),
            'previewing' => true,
        ]);
    }

    public function publish(Post $post, ActivityLogger $activity, PostService $posts): RedirectResponse
    {
        Gate::authorize('publish', $post);

        $posts->publish($post);

        $activity->log('publicacion.publicada', $post, ['title' => $post->title]);

        return back()->with('success', 'Publicación publicada.');
    }

    public function archive(Post $post, ActivityLogger $activity, PostService $posts): RedirectResponse
    {
        Gate::authorize('update', $post);

        $posts->archive($post);

        $activity->log('publicacion.archivada', $post, ['title' => $post->title]);

        return back()->with('success', 'Publicación archivada.');
    }

    public function toggleFeatured(Post $post, ActivityLogger $activity, PostService $posts): RedirectResponse
    {
        Gate::authorize('feature', $post);

        $featured = $posts->toggleFeatured($post);

        $activity->log($featured ? 'publicacion.destacada' : 'publicacion.no_destacada', $post);

        return back()->with('success', $featured ? 'Publicación destacada.' : 'Publicación ya no está destacada.');
    }

    public function schedule(Request $request, Post $post, ActivityLogger $activity, PostService $posts): RedirectResponse
    {
        Gate::authorize('publish', $post);

        $validated = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $posts->schedule($post, Carbon::parse($validated['scheduled_at']));

        $activity->log('publicacion.programada', $post, ['scheduled_at' => $validated['scheduled_at']]);

        return back()->with('success', 'Publicación programada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function postForEdit(Post $post): array
    {
        return [
            'id' => $post->id,
            'category_id' => $post->category_id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'body' => $post->body,
            'status' => $post->status->value,
            'statusLabel' => $post->status->label(),
            'is_featured' => $post->is_featured,
            'editorial_order' => $post->editorial_order,
            'scheduled_at' => $post->scheduled_at?->toIso8601String(),
            'published_at' => $post->published_at?->toIso8601String(),
            'seo_title' => $post->seo_title,
            'seo_description' => $post->seo_description,
            'tags' => $post->tags,
            'cover' => $post->cover ? ['id' => $post->cover->id, 'url' => $post->cover->url()] : null,
            'gallery' => $post->galleryMedia->map(fn ($m) => ['id' => $m->id, 'url' => $m->url()])->values(),
        ];
    }
}
