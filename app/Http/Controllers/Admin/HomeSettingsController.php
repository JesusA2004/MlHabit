<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HomeSettingsRequest;
use App\Models\Category;
use App\Models\HomepageSection;
use App\Models\Media;
use App\Models\Post;
use App\Models\SiteSetting;
use App\Services\ActivityLogger;
use App\Services\PostPresenter;
use App\Studio\StudioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HomeSettingsController extends Controller
{
    public function edit(PostPresenter $presenter, StudioService $studio): Response
    {
        abort_unless(request()->user()?->canManagePosts(), 403);

        $hero = SiteSetting::get('hero', []);
        $heroPhoto = ! empty($hero['photo_media_id']) ? Media::query()->find((int) $hero['photo_media_id']) : null;

        $historia = SiteSetting::get('historia_preview', []);
        $historiaPhoto = ! empty($historia['photo_media_id']) ? Media::query()->find((int) $historia['photo_media_id']) : null;

        $categories = Category::query()->where('status', 'active')->orderBy('order')->get();
        $preview = $categories->map(function (Category $category) use ($presenter) {
            $publishedInCategory = Post::query()
                ->where('category_id', $category->id)
                ->published()
                ->with('cover')
                ->orderByDesc('published_at')
                ->limit(20)
                ->get();

            $featured = $publishedInCategory->firstWhere('is_featured', true) ?? $publishedInCategory->first();

            return [
                'category_id' => $category->id,
                'category' => $category->name,
                'post' => $featured ? $presenter->summary($featured) : null,
                'candidates' => $publishedInCategory->map(fn (Post $p) => [
                    ...$presenter->summary($p),
                    'isFeatured' => $p->is_featured,
                ])->values(),
            ];
        });

        return Inertia::render('admin/inicio/Form', [
            'hero' => [
                'eyebrow' => $hero['eyebrow'] ?? '',
                'title_lines' => $hero['title_lines'] ?? [],
                'primary_cta_label' => $hero['primary_cta_label'] ?? '',
                'secondary_cta_label' => $hero['secondary_cta_label'] ?? '',
                'tertiary_link_label' => $hero['tertiary_link_label'] ?? '',
                'photo' => $heroPhoto ? ['id' => $heroPhoto->id, 'url' => $heroPhoto->url()] : null,
            ],
            'empiezaAqui' => SiteSetting::get('empieza_aqui', ['title' => '']),
            'tipsSection' => SiteSetting::get('tips_section', ['title' => '', 'posts_per_page' => 9]),
            'historiaPreview' => [
                'title' => $historia['title'] ?? '',
                'photo' => $historiaPhoto ? ['id' => $historiaPhoto->id, 'url' => $historiaPhoto->url()] : null,
            ],
            'procesoPreview' => SiteSetting::get('proceso_preview', ['title' => '']),
            'footer' => SiteSetting::get('footer', ['tagline' => '']),
            'sections' => HomepageSection::query()->orderBy('order')->get(['section_key', 'is_visible', 'order']),
            'empiezaAquiPreview' => $preview,
            // La composición y los textos del inicio se editan en Studio.
            'studioPageId' => $studio->ensureSystemPage('home')->id,
        ]);
    }

    public function update(HomeSettingsRequest $request, ActivityLogger $activity): RedirectResponse
    {
        $data = $request->validated();

        SiteSetting::set('hero', $data['hero']);
        SiteSetting::set('empieza_aqui', $data['empieza_aqui']);
        SiteSetting::set('tips_section', $data['tips_section']);
        SiteSetting::set('historia_preview', $data['historia_preview']);
        SiteSetting::set('proceso_preview', $data['proceso_preview']);
        SiteSetting::set('footer', $data['footer']);

        foreach ($data['sections'] as $section) {
            HomepageSection::query()->where('section_key', $section['section_key'])->update([
                'is_visible' => $section['is_visible'] ?? true,
                'order' => $section['order'] ?? 0,
            ]);
        }

        $activity->log('inicio.actualizado');

        return back()->with('success', 'Inicio actualizado.');
    }

    /**
     * Destaca una publicación para su categoría directamente desde /admin/inicio,
     * sin tener que entrar a Publicaciones. Solo acepta publicaciones ya
     * publicadas (la columna `published()` ya excluye borradores y archivadas)
     * y garantiza que quede como único destacado de esa categoría.
     */
    public function setFeatured(Request $request, ActivityLogger $activity): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'post_id' => ['required', 'integer', 'exists:posts,id'],
        ]);

        $post = Post::query()
            ->where('id', $data['post_id'])
            ->where('category_id', $data['category_id'])
            ->published()
            ->firstOrFail();

        Gate::authorize('feature', $post);

        Post::query()->where('category_id', $data['category_id'])->update(['is_featured' => false]);
        $post->update(['is_featured' => true]);

        $activity->log('inicio.destacado_cambiado', $post, ['title' => $post->title]);

        return back()->with('success', 'Publicación destacada actualizada.');
    }
}
