<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaCollection extends Model
{
    protected $fillable = ['parent_id', 'name', 'slug', 'path', 'order'];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    /** @return BelongsTo<MediaCollection, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MediaCollection::class, 'parent_id');
    }

    /** @return HasMany<MediaCollection, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(MediaCollection::class, 'parent_id')->orderBy('order');
    }

    /** @return HasMany<Media, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }
}
