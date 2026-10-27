<?php

namespace App\Models;

use App\Concerns\FlushesPublicSite;
use Carbon\CarbonImmutable;
use Database\Factories\FinishFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Acabado o material (muestra de color o textura).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $material
 * @property string|null $color_hex
 * @property string|null $description
 * @property int|null $media_id
 * @property bool $is_active
 * @property int $sort
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Media|null $media
 */
class Finish extends Model
{
    /** @use HasFactory<FinishFactory> */
    use FlushesPublicSite, HasFactory;

    protected $fillable = ['name', 'slug', 'material', 'color_hex', 'description', 'media_id', 'is_active', 'sort'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /** @param Builder<Finish> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
