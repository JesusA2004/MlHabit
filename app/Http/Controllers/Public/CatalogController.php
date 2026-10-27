<?php

namespace App\Http\Controllers\Public;

use App\Enums\FaqTopic;
use App\Enums\SiteSectionType;
use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ShowcaseProject;
use App\Models\SiteSection;
use App\Services\Web\OgImage;
use App\Services\Web\Presenter;
use App\Services\Web\PublicPage;
use App\Services\Web\Seo;
use Inertia\Response;

/**
 * Catálogo público: categorías, landing de categoría y ficha de producto.
 * Solo contenido publicado; lo demás responde 404.
 */
class CatalogController extends Controller
{
    public function __construct(
        private readonly Presenter $presenter,
        private readonly PublicPage $page,
        private readonly Seo $seo,
        private readonly OgImage $og,
    ) {}

    public function index(): Response
    {
        $categories = ProductCategory::query()->published()->ordered()
            ->with('cover.variants')
            ->withCount(['products' => fn ($q) => $q->where('status', 'published')])
            ->get();

        $title = 'Catálogo de muebles a la medida';
        $description = 'Cocinas integrales, clósets, recámaras, centros de entretenimiento y más muebles a la medida. Diseñamos, fabricamos e instalamos.';

        return $this->page->render('public/catalogo/Index', [
            'categories' => $categories->map(fn (ProductCategory $c, int $i) => $this->presenter->categoryCard($c, $i === 0 ? Presenter::SIZES_WIDE : Presenter::SIZES_CARD))->values(),
        ], $this->seo->page($title, $description, jsonLd: [
            $this->seo->breadcrumbs([['Inicio', '/'], ['Catálogo', '/catalogo']]),
        ], canonical: url('/catalogo'), og: $this->og->page('catalogo', $title, 'Cocinas, clósets, recámaras y más')));
    }

    public function category(ProductCategory $category): Response
    {
        abort_unless($category->is_published, 404);
        $category->load('cover.variants');

        $products = $category->products()->where('status', 'published')
            ->with(['cover.variants', 'category'])->orderBy('sort')->orderBy('name')->get();

        $projects = ShowcaseProject::query()->published()->where('product_category_id', $category->id)
            ->with(['cover.variants', 'category'])->ordered()->limit(6)->get();

        $faqs = Faq::query()->published()->where('product_category_id', $category->id)->ordered()->limit(10)->get();

        $title = $category->seo_title ?: $category->name;
        $description = $category->seo_description ?: ($category->tagline ?: $category->description);

        return $this->page->render('public/catalogo/Category', [
            'category' => [
                ...$this->presenter->categoryCard($category, Presenter::SIZES_FULL),
                'description' => $category->description,
            ],
            'products' => $products->map(fn (Product $p) => $this->presenter->productCard($p))->values(),
            'projects' => $projects->map(fn (ShowcaseProject $p) => $this->presenter->showcaseCard($p))->values(),
            'faqs' => $faqs->map(fn (Faq $f) => $this->presenter->faq($f))->values(),
        ], $this->seo->page(
            $title,
            $description,
            jsonLd: array_values(array_filter([
                $this->seo->breadcrumbs([['Inicio', '/'], ['Catálogo', '/catalogo'], [$category->name, '/catalogo/'.$category->slug]]),
                $this->seo->faqPage($faqs),
            ])),
            canonical: url('/catalogo/'.$category->slug),
            og: $this->og->category($category),
        ));
    }

    public function product(ProductCategory $category, Product $product): Response
    {
        abort_unless($category->is_published && $product->product_category_id === $category->id, 404);
        abort_unless($product->status->value === 'published', 404);

        $product->load([
            'category',
            'cover.variants',
            'finishes.media.variants',
            'tags',
            'media' => fn ($q) => $q->wherePivot('role', 'gallery')->with('variants'),
        ]);

        $related = Product::query()->published()->where('product_category_id', $category->id)->whereKeyNot($product->id)
            ->with(['cover.variants', 'category'])->orderBy('sort')->limit(3)->get();

        $projects = ShowcaseProject::query()->published()->where('product_category_id', $category->id)
            ->with(['cover.variants', 'category'])->ordered()->limit(3)->get();

        $faqs = Faq::query()->published()
            ->where(fn ($q) => $q->where('product_category_id', $category->id)
                ->orWhere(fn ($w) => $w->whereNull('product_category_id')->whereIn('topic', [FaqTopic::Product->value, FaqTopic::Process->value])))
            ->ordered()->limit(8)->get();

        $process = SiteSection::query()->where('page', 'home')->where('type', SiteSectionType::Process->value)->first();
        $steps = $process !== null ? $this->presenter->editorialList($process->content['steps'] ?? []) : [];

        $title = $product->seo_title ?: $product->name.' · '.$category->name;
        $description = $product->seo_description ?: ($product->summary ?: $product->description);

        return $this->page->render('public/catalogo/Product', [
            'product' => $this->presenter->productDetail($product),
            'related' => $related->map(fn (Product $p) => $this->presenter->productCard($p))->values(),
            'projects' => $projects->map(fn (ShowcaseProject $p) => $this->presenter->showcaseCard($p))->values(),
            'faqs' => $faqs->map(fn (Faq $f) => $this->presenter->faq($f))->values(),
            'process' => $steps,
            'ctaHref' => '/solicitar?categoria='.$category->slug,
        ], $this->seo->page(
            $title,
            $description,
            type: 'product',
            jsonLd: array_values(array_filter([
                $this->seo->product($product),
                $this->seo->breadcrumbs([['Inicio', '/'], ['Catálogo', '/catalogo'], [$category->name, '/catalogo/'.$category->slug], [$product->name, '/catalogo/'.$category->slug.'/'.$product->slug]]),
                $this->seo->faqPage($faqs),
            ])),
            canonical: url('/catalogo/'.$category->slug.'/'.$product->slug),
            og: $this->og->product($product),
        ));
    }
}
