<?php

namespace App\Models;

use App\Concerns\FlushesPublicSite;
use App\Concerns\HasAttachments;
use App\Enums\PublicationStatus;
use App\Enums\VisualOrigin;
use Carbon\CarbonImmutable;
use Database\Factories\ShowcaseProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Proyecto de inspiración. `origin` distingue un render conceptual de un
 * proyecto real entregado, y el sitio lo etiqueta siempre.
 *
 * @property int $id
 * @property int|null $product_category_id
 * @property string $title
 * @property string $slug
 * @property string|null $summary
 * @property string|null $story
 * @property string|null $location_label
 * @property VisualOrigin $origin
 * @property int|null $cover_media_id
 * @property PublicationStatus $status
 * @property bool $is_featured
 * @property int $sort
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read ProductCategory|null $category
 * @property-read Media|null $cover
 */
class ShowcaseProject extends Model
{
    /** @use HasFactory<ShowcaseProjectFactory> */
    use FlushesPublicSite, HasAttachments, HasFactory, SoftDeletes;

    protected $fillable = [
        'product_category_id', 'title', 'slug', 'summary', 'story', 'location_label', 'origin',
        'cover_media_id', 'status', 'is_featured', 'sort', 'seo_title', 'seo_description', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'origin' => VisualOrigin::class,
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

    /** @return HasMany<Testimonial, $this> */
    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    /** @param Builder<ShowcaseProject> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PublicationStatus::Published->value);
    }

    /** @param Builder<ShowcaseProject> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc('is_featured')->orderBy('sort')->orderByDesc('published_at');
    }

    public function publicUrl(): string
    {
        return route('public.inspiracion.show', ['showcase' => $this->slug]);
    }
}
