<?php

namespace App\Models;

use App\Concerns\FlushesPublicSite;
use App\Concerns\HasAttachments;
use App\Enums\PublicationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Línea de producto del catálogo. La galería se vincula con
 * media_attachments (rol `gallery`); cada imagen declara su origen
 * (render o foto real) en el propio Media.
 *
 * @property int $id
 * @property int $product_category_id
 * @property string $name
 * @property string $slug
 * @property string|null $summary
 * @property string|null $description
 * @property list<array{title: string, body: string|null}>|null $benefits
 * @property list<array{title: string, body: string|null}>|null $features
 * @property int|null $cover_media_id
 * @property string|null $price_from
 * @property string|null $price_to
 * @property bool $show_price
 * @property PublicationStatus $status
 * @property bool $is_featured
 * @property int $sort
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property CarbonImmutable|null $published_at
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read ProductCategory $category
 * @property-read Media|null $cover
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use FlushesPublicSite, HasAttachments, HasFactory, SoftDeletes;

    protected $fillable = [
        'product_category_id', 'name', 'slug', 'summary', 'description', 'benefits', 'features',
        'cover_media_id', 'price_from', 'price_to', 'show_price', 'status', 'is_featured', 'sort',
        'seo_title', 'seo_description', 'published_at', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'benefits' => 'array',
            'features' => 'array',
            'price_from' => 'decimal:2',
            'price_to' => 'decimal:2',
            'show_price' => 'boolean',
            'status' => PublicationStatus::class,
            'is_featured' => 'boolean',
            'sort' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ProductCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /** @return BelongsTo<Media, $this> */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    /** @return BelongsToMany<Finish, $this> */
    public function finishes(): BelongsToMany
    {
        return $this->belongsToMany(Finish::class)->orderBy('finishes.sort');
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('tags.name');
    }

    /** Publicado y con categoría publicada: visible en el sitio. */
    public function isPubliclyVisible(): bool
    {
        return $this->status === PublicationStatus::Published && $this->category->is_published;
    }

    /** @param Builder<Product> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PublicationStatus::Published->value)
            ->whereHas('category', fn (Builder $q) => $q->where('is_published', true));
    }

    /** @param Builder<Product> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('name');
    }

    /**
     * Rango "desde" solo si el producto lo permite explícitamente.
     *
     * @return array{from: string, to: string|null, currency: string}|null
     */
    public function displayPrice(): ?array
    {
        if (! $this->show_price || $this->price_from === null) {
            return null;
        }

        return ['from' => $this->price_from, 'to' => $this->price_to, 'currency' => 'MXN'];
    }

    public function publicUrl(): string
    {
        return route('public.catalogo.producto', ['category' => $this->category->slug, 'product' => $this->slug]);
    }
}
