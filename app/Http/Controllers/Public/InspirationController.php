<?php

namespace App\Http\Controllers\Public;

use App\Enums\VisualOrigin;
use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Models\ShowcaseProject;
use App\Models\Testimonial;
use App\Services\Web\Paginated;
use App\Services\Web\Presenter;
use App\Services\Web\PublicPage;
use App\Services\Web\Seo;
use App\Support\ResponsiveImage;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Inspiración: proyectos reales y renders conceptuales, siempre
 * etiquetados por origen.
 */
class InspirationController extends Controller
{
    public function __construct(
        private readonly Presenter $presenter,
        private readonly PublicPage $page,
        private readonly Seo $seo,
    ) {}

    public function index(Request $request): Response
    {
        $origin = VisualOrigin::tryFrom((string) $request->query('origen'));
        $categorySlug = is_string($request->query('categoria')) ? $request->query('categoria') : null;

        $projects = ShowcaseProject::query()->published()
            ->with(['cover.variants', 'category'])
            ->when($origin !== null, fn ($q) => $q->where('origin', $origin->value))
            ->when($categorySlug !== null, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $categorySlug)->where('is_published', true)))
            ->ordered()
            ->paginate(12)
            ->withQueryString();

        $categories = ProductCategory::query()->published()->ordered()
            ->whereHas('showcaseProjects', fn ($q) => $q->where('status', 'published'))
            ->get(['id', 'name', 'slug']);

        return $this->page->render('public/inspiracion/Index', [
            'projects' => Paginated::of($projects, fn (ShowcaseProject $p) => $this->presenter->showcaseCard($p)),
            'filters' => ['origen' => $origin?->value, 'categoria' => $categorySlug],
            'categories' => $categories->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug])->values(),
            'originOptions' => VisualOrigin::options(),
        ], $this->seo->page(
            'Inspiración: proyectos y propuestas',
            'Ideas para cocinas, clósets y muebles a la medida. Cada imagen indica si es un proyecto entregado o un render conceptual.',
            jsonLd: [$this->seo->breadcrumbs([['Inicio', '/'], ['Inspiración', '/inspiracion']])],
            canonical: url('/inspiracion'),
            noindex: $origin !== null || $categorySlug !== null || $projects->currentPage() > 1,
        ));
    }

    public function show(ShowcaseProject $showcase): Response
    {
        abort_unless($showcase->status->value === 'published', 404);

        $showcase->load([
            'cover.variants',
            'category',
            'media' => fn ($q) => $q->wherePivot('role', 'gallery')->with('variants'),
        ]);

        $more = ShowcaseProject::query()->published()->whereKeyNot($showcase->id)
            ->with(['cover.variants', 'category'])
            ->orderByRaw('product_category_id <=> ? desc', [$showcase->product_category_id])
            ->ordered()->limit(3)->get();

        $testimonial = Testimonial::query()->published()->where('showcase_project_id', $showcase->id)
            ->with(['media.variants', 'showcaseProject'])->first();

        $categoryVisible = $showcase->category !== null && $showcase->category->is_published;

        return $this->page->render('public/inspiracion/Show', [
            'project' => [
                ...$this->presenter->showcaseDetail($showcase),
                'category' => $categoryVisible ? ['name' => $showcase->category->name, 'slug' => $showcase->category->slug, 'href' => '/catalogo/'.$showcase->category->slug] : null,
            ],
            'more' => $more->map(fn (ShowcaseProject $p) => $this->presenter->showcaseCard($p))->values(),
            'testimonial' => $testimonial !== null ? $this->presenter->testimonial($testimonial) : null,
            'ctaHref' => '/solicitar'.($categoryVisible ? '?categoria='.$showcase->category->slug : ''),
        ], $this->seo->page(
            $showcase->seo_title ?: $showcase->title,
            $showcase->seo_description ?: ($showcase->summary ?: $showcase->story),
            ResponsiveImage::largeUrl($showcase->cover),
            'article',
            [$this->seo->breadcrumbs([['Inicio', '/'], ['Inspiración', '/inspiracion'], [$showcase->title, '/inspiracion/'.$showcase->slug]])],
            url('/inspiracion/'.$showcase->slug),
        ));
    }
}
