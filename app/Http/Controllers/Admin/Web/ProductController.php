<?php

namespace App\Http\Controllers\Admin\Web;

use App\Enums\PublicationStatus;
use App\Enums\VisualOrigin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ProductRequest;
use App\Models\Finish;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tag;
use App\Services\ActivityLogger;
use App\Services\Web\Gallery;
use App\Services\Web\MediaPresenter;
use App\Services\Web\Presenter;
use App\Services\Web\PublicSite;
use App\Services\Web\Slugger;
use App\Support\Html;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Productos del catálogo. Publicar o retirar de publicación requiere
 * además `content.publish`.
 */
class ProductController extends Controller
{
    public function __construct(private readonly Gallery $gallery, private readonly Presenter $presenter) {}

    public function create(Request $request): Response
    {
        Gate::authorize('create', Product::class);

        return Inertia::render('admin/catalogo/Producto', [
            ...$this->formOptions(),
            'product' => null,
            'defaultCategoryId' => is_numeric($request->query('categoria')) ? (int) $request->query('categoria') : null,
        ]);
    }

    public function store(ProductRequest $request, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('create', Product::class);

        $data = $request->validated();
        $status = PublicationStatus::from($data['status']);
        if ($status === PublicationStatus::Published) {
            Gate::authorize('publish', Product::class);
        }

        $product = DB::transaction(function () use ($data, $status, $request) {
            $product = Product::query()->create([
                ...$this->attributes($data),
                'slug' => Slugger::unique(Product::class, $data['slug'] ?? null, $data['name'], null, 180),
                'status' => $status,
                'published_at' => $status === PublicationStatus::Published ? now() : null,
                'sort' => (int) Product::query()->where('product_category_id', $data['product_category_id'])->max('sort') + 10,
                'updated_by' => $request->user()?->id,
            ]);
            $this->syncRelations($product, $data, $request->user()?->id);

            return $product;
        });

        $activity->log('catalogo.producto_creado', $product, ['nombre' => $product->name, 'estado' => $product->status->value]);

        return to_route('admin.catalogo.productos.edit', $product)->with('success', 'Producto guardado.');
    }

    public function edit(Product $product): Response
    {
        Gate::authorize('update', $product);

        $product->load(['category', 'cover.variants', 'finishes:id', 'tags:id,name']);

        return Inertia::render('admin/catalogo/Producto', [
            ...$this->formOptions(),
            'product' => [
                'id' => $product->id,
                'productCategoryId' => $product->product_category_id,
                'name' => $product->name,
                'slug' => $product->slug,
                'summary' => $product->summary,
                'description' => $product->description,
                'benefits' => $this->presenter->editorialList($product->benefits),
                'features' => $this->presenter->editorialList($product->features),
                'cover' => MediaPresenter::ref($product->cover),
                'gallery' => $this->gallery->forEditor($product),
                'finishIds' => $product->finishes->pluck('id')->values(),
                'tags' => $product->tags->pluck('name')->values(),
                'priceFrom' => $product->price_from,
                'priceTo' => $product->price_to,
                'showPrice' => $product->show_price,
                'status' => $product->status->value,
                'isFeatured' => $product->is_featured,
                'seoTitle' => $product->seo_title,
                'seoDescription' => $product->seo_description,
                'publishedAt' => $product->published_at?->toIso8601String(),
                'updatedAt' => $product->updated_at?->toIso8601String(),
                'publicUrl' => $product->isPubliclyVisible() ? $product->publicUrl() : null,
                'categoryPublished' => $product->category->is_published,
            ],
            'defaultCategoryId' => null,
        ]);
    }

    public function update(ProductRequest $request, Product $product, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('update', $product);

        $data = $request->validated();
        $status = PublicationStatus::from($data['status']);
        $this->authorizeStatusChange($product, $status);

        DB::transaction(function () use ($product, $data, $status, $request, $activity) {
            $product->fill([
                ...$this->attributes($data),
                'slug' => Slugger::unique(Product::class, $data['slug'] ?? $product->slug, $data['name'], $product->id, 180),
                'status' => $status,
                'updated_by' => $request->user()?->id,
            ]);
            if ($status === PublicationStatus::Published && $product->published_at === null) {
                $product->published_at = now();
            }
            $activity->logModelChanges('catalogo.producto_actualizado', $product, ['name', 'slug', 'status', 'product_category_id', 'price_from', 'price_to', 'show_price', 'is_featured']);
            $product->save();
            $this->syncRelations($product, $data, $request->user()?->id);
        });

        return back()->with('success', 'Producto actualizado.');
    }

    public function updateStatus(Request $request, Product $product, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('update', $product);

        $data = $request->validate(['status' => ['required', 'in:draft,published,archived']]);
        $status = PublicationStatus::from($data['status']);
        $this->authorizeStatusChange($product, $status);

        $before = $product->status->value;
        $product->forceFill([
            'status' => $status,
            'published_at' => $status === PublicationStatus::Published ? ($product->published_at ?? now()) : $product->published_at,
            'updated_by' => $request->user()?->id,
        ])->save();

        $activity->log('catalogo.producto_estado', $product, ['before' => ['status' => $before], 'after' => ['status' => $status->value]]);

        return back()->with('success', match ($status) {
            PublicationStatus::Published => 'Producto publicado.',
            PublicationStatus::Draft => 'El producto volvió a borrador y ya no se ve en el sitio.',
            PublicationStatus::Archived => 'Producto archivado.',
        });
    }

    public function reorder(Request $request, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('create', Product::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:products,id'],
        ]);

        DB::transaction(function () use ($data) {
            foreach (array_values($data['ids']) as $index => $id) {
                Product::query()->whereKey($id)->update(['sort' => ($index + 1) * 10]);
            }
        });
        PublicSite::flush();
        $activity->log('catalogo.productos_reordenados', null, ['ids' => $data['ids']]);

        return back()->with('success', 'Orden actualizado.');
    }

    public function destroy(Product $product, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('delete', $product);
        if ($product->status === PublicationStatus::Published) {
            Gate::authorize('publish', Product::class);
        }

        $product->attachments()->delete();
        $product->delete();
        $activity->log('catalogo.producto_eliminado', $product, ['nombre' => $product->name]);

        return to_route('admin.catalogo.index')->with('success', 'Producto eliminado.');
    }

    private function authorizeStatusChange(Product $product, PublicationStatus $next): void
    {
        $touchesPublished = $next !== $product->status
            && ($next === PublicationStatus::Published || $product->status === PublicationStatus::Published);

        if ($touchesPublished || ($product->status === PublicationStatus::Published && ! Gate::allows('publish', Product::class))) {
            Gate::authorize('publish', Product::class);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'product_category_id' => (int) $data['product_category_id'],
            'name' => $data['name'],
            'summary' => $data['summary'] ?? null,
            'description' => isset($data['description']) ? Html::clean((string) $data['description']) : null,
            'benefits' => $this->presenter->editorialList($data['benefits'] ?? []),
            'features' => $this->presenter->editorialList($data['features'] ?? []),
            'cover_media_id' => $data['cover_media_id'] ?? null,
            'price_from' => isset($data['price_from']) && $data['price_from'] !== '' ? (string) $data['price_from'] : null,
            'price_to' => isset($data['price_to']) && $data['price_to'] !== '' ? (string) $data['price_to'] : null,
            'show_price' => (bool) ($data['show_price'] ?? false),
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRelations(Product $product, array $data, ?int $userId): void
    {
        /** @var list<array{id: int, origin?: string|null, caption?: string|null}> $gallery */
        $gallery = $data['gallery'] ?? [];
        $this->gallery->sync($product, $gallery, $userId);
        $product->finishes()->sync(array_map('intval', $data['finish_ids'] ?? []));
        /** @var list<string> $tags */
        $tags = $data['tags'] ?? [];
        $product->tags()->sync(Tag::idsFor($tags));
        PublicSite::flush();
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => ProductCategory::query()->ordered()->get(['id', 'name', 'is_published'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name, 'isPublished' => $c->is_published])->values(),
            'finishes' => Finish::query()->with('media.variants')->orderBy('sort')->orderBy('name')->get()
                ->map(fn (Finish $f) => ['id' => $f->id, 'name' => $f->name, 'material' => $f->material, 'colorHex' => $f->color_hex, 'isActive' => $f->is_active, 'media' => MediaPresenter::ref($f->media)])->values(),
            'tagSuggestions' => Tag::query()->orderBy('name')->limit(200)->pluck('name'),
            'statusOptions' => PublicationStatus::options(),
            'originOptions' => VisualOrigin::options(),
            'can' => ['publish' => Gate::allows('publish', Product::class)],
        ];
    }
}
