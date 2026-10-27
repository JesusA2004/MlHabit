<?php

namespace App\Models;

use App\Enums\PostStatus;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $category_id
 * @property int $author_id
 * @property int|null $cover_media_id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property array<string, mixed>|null $body
 * @property PostStatus $status
 * @property bool $is_featured
 * @property int $editorial_order
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $published_at
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property array<int, string>|null $tags
 * @property array<int, int>|null $related_post_ids
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id', 'author_id', 'cover_media_id', 'title', 'slug', 'excerpt', 'body',
        'status', 'is_featured', 'editorial_order', 'scheduled_at', 'published_at',
        'seo_title', 'seo_description', 'tags', 'related_post_ids',
    ];

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'body' => 'array',
            'tags' => 'array',
            'related_post_ids' => 'array',
            'is_featured' => 'boolean',
            'editorial_order' => 'integer',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<Media, $this> */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    /** @return BelongsToMany<Media, $this> */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'post_media')
            ->withPivot(['role', 'order'])
            ->orderByPivot('order');
    }

    /** @return BelongsToMany<Media, $this> */
    public function galleryMedia(): BelongsToMany
    {
        return $this->media()->wherePivot('role', 'gallery');
    }

    /** @return HasMany<PostRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(PostRevision::class)->latest('created_at');
    }

    /** @param Builder<Post> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PostStatus::Published)
            ->where('published_at', '<=', Carbon::now());
    }

    /** @param Builder<Post> $query */
    public function scopeFeatured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    /** @return Collection<int, Post> */
    public function relatedPosts(): Collection
    {
        if (empty($this->related_post_ids)) {
            return new Collection;
        }

        return Post::query()
            ->published()
            ->whereIn('id', $this->related_post_ids)
            ->get();
    }
}
