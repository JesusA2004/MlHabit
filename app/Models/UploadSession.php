<?php

namespace App\Models;

use App\Enums\UploadSessionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int|null $media_collection_id
 * @property int|null $media_id
 * @property string|null $storage_provider_key
 * @property string $filename
 * @property string|null $mime_type
 * @property int $total_size
 * @property int $received_size
 * @property int $chunk_size
 * @property int $next_chunk_index
 * @property string|null $checksum_sha256
 * @property UploadSessionStatus $status
 * @property string|null $temp_path
 * @property string|null $error_message
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UploadSession extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'media_collection_id', 'media_id', 'storage_provider_key',
        'filename', 'mime_type', 'total_size', 'received_size', 'chunk_size',
        'next_chunk_index', 'checksum_sha256',
        'status', 'temp_path', 'error_message', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => UploadSessionStatus::class,
            'total_size' => 'integer',
            'received_size' => 'integer',
            'chunk_size' => 'integer',
            'next_chunk_index' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<MediaCollection, $this> */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(MediaCollection::class, 'media_collection_id');
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function progressPercent(): int
    {
        if ($this->total_size <= 0) {
            return 0;
        }

        return (int) min(100, round(($this->received_size / $this->total_size) * 100));
    }
}
