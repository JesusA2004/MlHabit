<?php

namespace App\Http\Controllers\Admin\Web;

use App\Enums\PublicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\FinishRequest;
use App\Http\Requests\Web\ProductCategoryRequest;
use App\Models\Finish;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ActivityLogger;
use App\Services\Web\MediaPresenter;
use App\Services\Web\Paginated;
use App\Services\Web\PublicSite;
use App\Services\Web\Slugger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración → Catálogo: productos (listado), categorías y acabados.
 */
class CatalogController extends Controller
{
    public const TABS = ['productos', 'categorias', 'acabados'];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Product::class);

        $tab = in_array($request->query('pestana'), self::TABS, true) ? (string) $request->query('pestana') : 'productos';
        $search = is_string($request->query('q')) ? trim($request->query('q')) : '';
        $status = PublicationStatus::tryFrom((string) $request->query('estado'));
        $categoryId = is_numeric($request->query('categoria')) ? (int) $request->query('categoria') : null;

        $products = Product::query()
            ->with(['category:id,name,slug,is_published', 'cover.variants'])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")))
            ->when($status !== null, fn ($q) => $q->where('status', $status->value))
            ->when($categoryId !== null, fn ($q) => $q->where('product_category_id', $categoryId))
            ->orderBy('product_category_id')->orderBy('sort')->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        $categories = ProductCategory::query()->with('cover.variants')->withCount('products')->ordered()->get();
        $finishes = Finish::query()->with('media.variants')->withCount('products')->orderBy('sort')->orderBy('name')->get();

        return Inertia::render('admin/catalogo/Index', [
            'tab' => $tab,
            'filters' => ['q' => $search, 'estado' => $status?->value, 'categoria' => $categoryId],
            'products' => Paginated::of($products, fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'status' => $p->status->value,
                'statusLabel' => $p->status->label(),
                'statusTone' => $p->status->tone(),
                'isFeatured' => $p->is_featured,
                'category' => ['id' => $p->category->id, 'name' => $p->category->name, 'isPublished' => $p->category->is_published],
                'cover' => MediaPresenter::ref($p->cover),
                'publicUrl' => $p->status === PublicationStatus::Published && $p->category->is_published ? '/catalogo/'.$p->category->slug.'/'.$p->slug : null,
                'updatedAt' => $p->updated_at?->toIso8601String(),
            ]),
            'categories' => $categories->map(fn (ProductCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'tagline' => $c->tagline,
                'description' => $c->description,
                'cover' => MediaPresenter::ref($c->cover),
                'isPublished' => $c->is_published,
                'isFeatured' => $c->is_featured,
                'seoTitle' => $c->seo_title,
                'seoDescription' => $c->seo_description,
                'productsCount' => (int) $c->getAttribute('products_count'),
                'sort' => $c->sort,
            ])->values(),
            'finishes' => $finishes->map(fn (Finish $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'slug' => $f->slug,
                'material' => $f->material,
                'colorHex' => $f->color_hex,
                'description' => $f->description,
                'media' => MediaPresenter::ref($f->media),
                'isActive' => $f->is_active,
                'productsCount' => (int) $f->getAttribute('products_count'),
            ])->values(),
            'statusOptions' => PublicationStatus::options(),
            'can' => ['publish' => Gate::allows('publish', Product::class)],
        ]);
    }

    public function storeCategory(ProductCategoryRequest $request, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('create', ProductCategory::class);

        $data = $request->validated();
        if ($data['is_published'] ?? false) {
            Gate::authorize('publish', ProductCategory::class);
        }

        $category = ProductCategory::query()->create([
            ...$data,
            'slug' => Slugger::unique(ProductCategory::class, $data['slug'] ?? null, $data['name']),
            'sort' => (int) ProductCategory::query()->max('sort') + 10,
        ]);

        $activity->log('catalogo.categoria_creada', $category, ['nombre' => $category->name]);

        return back()->with('success', 'Categoría creada.');
    }

    public function updateCategory(ProductCategoryRequest $request, ProductCategory $category, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('update', $category);

        $data = $request->validated();
        if (array_key_exists('is_published', $data) && (bool) $data['is_published'] !== $category->is_published) {
            Gate::authorize('publish', ProductCategory::class);
        }

        $category->fill([...$data, 'slug' => Slugger::unique(ProductCategory::class, $data['slug'] ?? $category->slug, $data['name'], $category->id)]);
        $activity->logModelChanges('catalogo.categoria_actualizada', $category);
        $category->save();

        return back()->with('success', 'Categoría actualizada.');
    }

    public function destroyCategory(ProductCategory $category, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('delete', $category);

        if ($category->products()->exists()) {
            return back()->with('error', 'Esta categoría tiene productos. Muévelos o elimínalos antes de borrarla.');
        }

        $category->delete();
        $activity->log('catalogo.categoria_eliminada', $category, ['nombre' => $category->name]);

        return back()->with('success', 'Categoría eliminada.');
    }

    public function reorderCategories(Request $request, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('create', ProductCategory::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:product_categories,id'],
        ]);

        DB::transaction(function () use ($data) {
            foreach (array_values($data['ids']) as $index => $id) {
                ProductCategory::query()->whereKey($id)->update(['sort' => ($index + 1) * 10]);
            }
        });
        PublicSite::flush();

        $activity->log('catalogo.categorias_reordenadas', null, ['ids' => $data['ids']]);

        return back()->with('success', 'Orden actualizado.');
    }

    public function storeFinish(FinishRequest $request, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('create', Finish::class);

        $data = $request->validated();
        $finish = Finish::query()->create([
            ...$data,
            'slug' => Slugger::unique(Finish::class, $data['slug'] ?? null, $data['name']),
            'sort' => (int) Finish::query()->max('sort') + 10,
        ]);

        $activity->log('catalogo.acabado_creado', $finish, ['nombre' => $finish->name]);

        return back()->with('success', 'Acabado creado.');
    }

    public function updateFinish(FinishRequest $request, Finish $finish, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('update', $finish);

        $data = $request->validated();
        $finish->fill([...$data, 'slug' => Slugger::unique(Finish::class, $data['slug'] ?? $finish->slug, $data['name'], $finish->id)]);
        $activity->logModelChanges('catalogo.acabado_actualizado', $finish);
        $finish->save();

        return back()->with('success', 'Acabado actualizado.');
    }

    public function destroyFinish(Finish $finish, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('delete', $finish);

        $finish->delete();
        $activity->log('catalogo.acabado_eliminado', $finish, ['nombre' => $finish->name]);

        return back()->with('success', 'Acabado eliminado.');
    }
}
