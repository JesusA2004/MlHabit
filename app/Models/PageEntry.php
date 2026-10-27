<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $page_id
 * @property string $title
 * @property Carbon|null $entry_date
 * @property array<string, mixed>|null $body
 * @property int|null $cover_media_id
 * @property bool $is_visible
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PageEntry extends Model
{
    protected $fillable = ['page_id', 'title', 'entry_date', 'body', 'cover_media_id', 'is_visible', 'order'];

    protected function casts(): array
    {
        return [
            'body' => 'array',
            'entry_date' => 'date',
            'is_visible' => 'boolean',
            'order' => 'integer',
        ];
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /** @return BelongsTo<Media, $this> */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }
}
