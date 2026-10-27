<?php

namespace App\Models;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Enums\MediaVisibility;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property MediaVisibility $visibility
 * @property string|null $description
 * @property string|null $origin render|real_photo
 * @property int|null $media_collection_id
 * @property int|null $uploaded_by
 * @property string $disk
 * @property string $path
 * @property string $filename
 * @property string $original_filename
 * @property MediaType $type
 * @property string $mime_type
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property int|null $duration_seconds
 * @property string|null $title
 * @property string|null $alt_text
 * @property MediaStatus $status
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'visibility', 'description', 'origin',
        'media_collection_id', 'uploaded_by', 'disk', 'path', 'filename', 'original_filename',
        'type', 'mime_type', 'size', 'width', 'height', 'duration_seconds',
        'title', 'alt_text', 'status', 'metadata', 'last_used_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Media $media) {
            $media->uuid ??= (string) Str::uuid();
            $media->visibility ??= MediaVisibility::Public;
        });
    }

    public function isPrivate(): bool
    {
        return $this->visibility === MediaVisibility::Private;
    }

    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'visibility' => MediaVisibility::class,
            'status' => MediaStatus::class,
            'metadata' => 'array',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration_seconds' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<MediaCollection, $this> */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(MediaCollection::class, 'media_collection_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return HasMany<MediaVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class);
    }

    public function url(): ?string
    {
        if ($this->status !== MediaStatus::Ready) {
            return null;
        }

        if ($this->isPrivate()) {
            return route('archivos.show', ['media' => $this->uuid]);
        }

        return Storage::disk($this->disk)->url($this->path);
    }

    public function variantUrl(string $type): ?string
    {
        $variant = $this->variants->firstWhere('type', $type);
        if ($variant === null) {
            return null;
        }

        if ($this->isPrivate()) {
            return route('archivos.show', ['media' => $this->uuid, 'variante' => $type]);
        }

        return Storage::disk($variant->disk)->url($variant->path);
    }

    /** @return HasMany<MediaAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(MediaAttachment::class);
    }
}
