<?php

namespace App\Models;

use App\Enums\StorageMigrationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Traslado de la biblioteca multimedia de un disco a otro (servidor ⇄ NAS).
 *
 * @property int $id
 * @property string $uuid
 * @property string $source_disk
 * @property string $target_disk
 * @property bool $keep_source
 * @property StorageMigrationStatus $status
 * @property string|null $batch_id
 * @property int|null $started_by
 * @property int $total_files
 * @property int $total_bytes
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $sources_purged_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StorageMigration extends Model
{
    protected $fillable = [
        'uuid', 'source_disk', 'target_disk', 'keep_source', 'status', 'batch_id', 'started_by',
        'total_files', 'total_bytes', 'started_at', 'finished_at', 'cancelled_at', 'sources_purged_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (StorageMigration $migration) {
            $migration->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => StorageMigrationStatus::class,
            'keep_source' => 'boolean',
            'total_files' => 'integer',
            'total_bytes' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'sources_purged_at' => 'datetime',
        ];
    }

    /** @return HasMany<StorageMigrationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(StorageMigrationItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }
}
