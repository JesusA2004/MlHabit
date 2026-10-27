<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Vínculo archivo ↔ entidad. `role` describe el uso: gallery, cover,
 * photo, sketch, render, document, evidence, receipt, contract…
 *
 * @property int $id
 * @property int $media_id
 * @property string $attachable_type
 * @property int $attachable_id
 * @property string $role
 * @property string|null $caption
 * @property int $sort
 * @property int|null $attached_by
 */
class MediaAttachment extends Model
{
    protected $fillable = ['media_id', 'attachable_type', 'attachable_id', 'role', 'caption', 'sort', 'attached_by'];

    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /** @return MorphTo<Model, $this> */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
