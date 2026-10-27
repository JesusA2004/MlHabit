<?php

namespace App\Models;

use App\Concerns\FlushesPublicSite;
use App\Enums\FaqTopic;
use Carbon\CarbonImmutable;
use Database\Factories\FaqFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property FaqTopic $topic
 * @property int|null $product_category_id
 * @property string $question
 * @property string $answer
 * @property int $sort
 * @property bool $is_published
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ProductCategory|null $category
 */
class Faq extends Model
{
    /** @use HasFactory<FaqFactory> */
    use FlushesPublicSite, HasFactory;

    protected $fillable = ['topic', 'product_category_id', 'question', 'answer', 'sort', 'is_published'];

    protected function casts(): array
    {
        return [
            'topic' => FaqTopic::class,
            'sort' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /** @return BelongsTo<ProductCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /** @param Builder<Faq> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /** @param Builder<Faq> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('id');
    }
}
