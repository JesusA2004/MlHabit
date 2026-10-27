<?php

namespace App\Models;

use App\Concerns\FlushesPublicSite;
use App\Concerns\HasAttachments;
use App\Enums\PublicationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ProductCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Categoría del catálogo (cocinas integrales, clósets, recámaras…).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $tagline
 * @property string|null $description
 * @property int|null $cover_media_id
 * @property int $sort
 * @property bool $is_published
 * @property bool $is_featured
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Media|null $cover
 */
class ProductCategory extends Model
{
    /** @use HasFactory<ProductCategoryFactory> */
    use FlushesPublicSite, HasAttachments, HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'tagline', 'description', 'cover_media_id', 'sort',
        'is_published', 'is_featured', 'seo_title', 'seo_description',
    ];

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    /** @return BelongsTo<Media, $this> */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<ShowcaseProject, $this> */
    public function showcaseProjects(): HasMany
    {
        return $this->hasMany(ShowcaseProject::class);
    }

    /** @return HasMany<Faq, $this> */
    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class);
    }

    /** @param Builder<ProductCategory> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /** @param Builder<ProductCategory> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('name');
    }

    public function publicUrl(): string
    {
        return route('public.catalogo.categoria', ['category' => $this->slug]);
    }

    /** Número de productos publicados (para tarjetas públicas). */
    public function publishedProductsCount(): int
    {
        return $this->products()->where('status', PublicationStatus::Published->value)->count();
    }
}
