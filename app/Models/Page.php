<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    protected $fillable = [
        'key', 'title', 'intro', 'body', 'cover_media_id', 'seo_title', 'seo_description', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'body' => 'array',
        ];
    }

    /** @return BelongsTo<Media, $this> */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return HasMany<PageEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(PageEntry::class)->orderBy('order');
    }
}
