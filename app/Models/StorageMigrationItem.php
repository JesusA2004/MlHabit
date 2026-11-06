<?php

namespace App\Models;

use App\Enums\StorageMigrationItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un archivo de la biblioteca (original + todas sus variantes) dentro de
 * un traslado.
 *
 * @property int $id
 * @property int $storage_migration_id
 * @property int $media_id
 * @property StorageMigrationItemStatus $status
 * @property int $files
 * @property int $bytes
 * @property int $transferred_bytes
 * @property int $attempts
 * @property string|null $error
 * @property bool $source_kept
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StorageMigrationItem extends Model
{
    protected $fillable = [
        'storage_migration_id', 'media_id', 'status', 'files', 'bytes', 'transferred_bytes',
        'attempts', 'error', 'source_kept', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => StorageMigrationItemStatus::class,
            'files' => 'integer',
            'bytes' => 'integer',
            'transferred_bytes' => 'integer',
            'attempts' => 'integer',
            'source_kept' => 'boolean',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<StorageMigration, $this> */
    public function migration(): BelongsTo
    {
        return $this->belongsTo(StorageMigration::class, 'storage_migration_id');
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class)->withTrashed();
    }
}
