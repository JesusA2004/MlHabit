<?php

namespace App\Services\Storage;

use App\Enums\MediaVisibility;
use App\Enums\StorageMigrationItemStatus as ItemStatus;
use App\Enums\StorageMigrationStatus as Status;
use App\Jobs\Storage\MigrateMediaItem;
use App\Models\Media;
use App\Models\MediaVariant;
use App\Models\StorageMigration;
use App\Models\StorageMigrationItem;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Traslado de la biblioteca pública entre el servidor (`media_local`) y el
 * NAS (`media_synology`), en ambos sentidos.
 *
 * - Un renglón por archivo en `storage_migration_items`: se puede cancelar,
 *   reanudar y reintentar solo los fallidos; volver a lanzar un traslado
 *   no duplica nada (solo toma lo que sigue en el origen).
 * - Se procesa en cola con un Bus batch (un job por archivo) o en línea
 *   desde `php artisan media:migrate`.
 * - Los archivos privados (documentos de clientes, disco `media_private`)
 *   nunca salen del servidor.
 */
class StorageMigrationService
{
    public const DISKS = [MediaStorage::LOCAL, MediaStorage::REMOTE];

    public function __construct(
        private readonly MediaRelocator $relocator,
        private readonly MediaStorage $storage,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Archivos públicos con el original o alguna variante en `$source`.
     *
     * @return Builder<Media>
     */
    public function candidates(string $source): Builder
    {
        return Media::withTrashed()
            ->where('visibility', MediaVisibility::Public->value)
            ->where(fn (Builder $q) => $q->where('disk', $source)
                ->orWhereHas('variants', fn (Builder $v) => $v->where('disk', $source)));
    }

    /**
     * @return array{files: int, bytes: int}
     */
    public function plan(string $source): array
    {
        $ids = $this->candidates($source)->pluck('id');

        $bytes = (int) Media::withTrashed()->whereIn('id', $ids)->where('disk', $source)->sum('size')
            + (int) MediaVariant::query()->whereIn('media_id', $ids)->where('disk', $source)->sum('size');

        return ['files' => $ids->count(), 'bytes' => $bytes];
    }

    public function active(): ?StorageMigration
    {
        return StorageMigration::query()
            ->whereIn('status', [Status::Queued->value, Status::Running->value])
            ->latest('id')
            ->first();
    }

    /**
     * Crea el traslado con todos sus renglones. No lo despacha.
     */
    public function create(string $source, string $target, bool $keepSource, ?User $actor = null): StorageMigration
    {
        $this->assertDisks($source, $target);

        if ($this->active() !== null) {
            throw new RuntimeException('Ya hay un traslado en curso. Espera a que termine o cancélalo.');
        }

        return DB::transaction(function () use ($source, $target, $keepSource, $actor) {
            $migration = StorageMigration::query()->create([
                'source_disk' => $source,
                'target_disk' => $target,
                'keep_source' => $keepSource,
                'status' => Status::Queued,
                'started_by' => $actor?->id,
            ]);

            $totalFiles = 0;
            $totalBytes = 0;

            $this->candidates($source)->with('variants')->select(['id', 'disk', 'size'])
                ->chunkById(500, function ($chunk) use ($migration, $source, &$totalFiles, &$totalBytes) {
                    $now = now();
                    $rows = [];
                    foreach ($chunk as $media) {
                        /** @var Media $media */
                        $files = ($media->disk === $source ? 1 : 0);
                        $bytes = ($media->disk === $source ? $media->size : 0);
                        foreach ($media->variants as $variant) {
                            if ($variant->disk === $source) {
                                $files++;
                                $bytes += (int) $variant->size;
                            }
                        }
                        $rows[] = [
                            'storage_migration_id' => $migration->id,
                            'media_id' => $media->id,
                            'status' => ItemStatus::Pending->value,
                            'files' => $files,
                            'bytes' => $bytes,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        $totalFiles++;
                        $totalBytes += $bytes;
                    }
                    StorageMigrationItem::query()->insert($rows);
                });

            $migration->forceFill(['total_files' => $totalFiles, 'total_bytes' => $totalBytes])->save();

            $this->activity->log('almacenamiento.traslado_iniciado', $migration, [
                'origen' => $source,
                'destino' => $target,
                'archivos' => $totalFiles,
                'bytes' => $totalBytes,
                'conservar_origen' => $keepSource,
            ]);

            return $migration;
        });
    }

    /** Crea y despacha el traslado a la cola. */
    public function start(string $source, string $target, bool $keepSource, ?User $actor = null): StorageMigration
    {
        $migration = $this->create($source, $target, $keepSource, $actor);
        $this->dispatch($migration);

        return $migration->refresh();
    }

    /** Despacha a la cola los renglones pendientes (sirve para reanudar). */
    public function dispatch(StorageMigration $migration): void
    {
        $pending = $migration->items()->where('status', ItemStatus::Pending->value)->pluck('id');

        if ($pending->isEmpty()) {
            $this->finalizeIfDone($migration);

            return;
        }

        $migrationId = $migration->id;
        $batch = Bus::batch([])
            ->name("Traslado multimedia #{$migration->id}")
            ->allowFailures()
            ->onQueue(config('media.storage.queue'))
            ->finally(function () use ($migrationId) {
                $migration = StorageMigration::query()->find($migrationId);
                if ($migration !== null) {
                    app(StorageMigrationService::class)->finalizeIfDone($migration);
                }
            })
            ->dispatch();

        $migration->forceFill(['batch_id' => $batch->id])->save();

        foreach ($pending->chunk(200) as $chunk) {
            $batch->add($chunk->map(fn (int $id) => new MigrateMediaItem($id))->values()->all());
        }
    }

    /**
     * Procesa un renglón: traslada el archivo con reintentos y registra el
     * resultado. Seguro de llamar varias veces.
     *
     * @param  (callable(int): void)|null  $onProgress
     */
    public function processItem(StorageMigrationItem $item, ?callable $onProgress = null): void
    {
        $item->refresh();
        $migration = StorageMigration::query()->findOrFail($item->storage_migration_id);

        if ($item->status !== ItemStatus::Pending || ! $migration->status->isActive()) {
            return;
        }

        if ($migration->status === Status::Queued) {
            StorageMigration::query()->whereKey($migration->id)->where('status', Status::Queued->value)
                ->update(['status' => Status::Running->value, 'started_at' => now()]);
        }

        SynologyDisk::refreshIfChanged();

        $media = Media::withTrashed()->find($item->media_id);
        if ($media === null) {
            $this->finish($item, ItemStatus::Skipped, 'El archivo ya no existe en la biblioteca.');
            $this->finalizeIfDone($migration);

            return;
        }

        $limitKbps = $this->involvesRemote($migration) ? $this->storage->transferLimitKbps() : 0;
        $maxAttempts = max(1, (int) config('media.storage.attempts', 3));
        $waits = (array) config('media.storage.retry_wait_seconds', [2, 10]);

        $lastError = null;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $item->forceFill(['attempts' => $item->attempts + 1])->save();

            try {
                $result = $this->relocator->relocate($media, $migration->target_disk, $migration->keep_source, $limitKbps, $onProgress);

                if (! $result->moved) {
                    $this->finish($item, ItemStatus::Skipped, 'Ya estaba en el destino.');
                } else {
                    $item->forceFill(['transferred_bytes' => $result->transferred, 'bytes' => max($item->bytes, $result->bytes)]);
                    $this->finish($item, ItemStatus::Done, null, $migration->keep_source);
                }

                $this->finalizeIfDone($migration);

                return;
            } catch (MediaRelocationException $e) {
                $lastError = $e->getMessage();
                if (! $e->retryable) {
                    break;
                }
            } catch (Throwable $e) {
                $lastError = $this->storage->safeError($e);
            }

            if ($attempt < $maxAttempts) {
                $wait = (int) ($waits[$attempt - 1] ?? end($waits) ?: 0);
                if ($wait > 0) {
                    sleep($wait);
                }
            }
        }

        Log::warning('Traslado multimedia: archivo fallido.', ['migration' => $migration->id, 'media_id' => $item->media_id, 'error' => $lastError]);
        $this->finish($item, ItemStatus::Failed, $lastError);
        $this->finalizeIfDone($migration);
    }

    public function markItemFailed(StorageMigrationItem $item, string $error): void
    {
        $item->refresh();
        if ($item->status === ItemStatus::Pending) {
            $this->finish($item, ItemStatus::Failed, $error);
        }
        $this->finalizeIfDone(StorageMigration::query()->findOrFail($item->storage_migration_id));
    }

    public function finalizeIfDone(StorageMigration $migration): void
    {
        $migration->refresh();
        if (! $migration->status->isActive()) {
            return;
        }

        if ($migration->items()->where('status', ItemStatus::Pending->value)->exists()) {
            return;
        }

        $failed = $migration->items()->where('status', ItemStatus::Failed->value)->count();
        $status = $failed > 0 ? Status::CompletedWithErrors : Status::Completed;

        $updated = StorageMigration::query()->whereKey($migration->id)
            ->whereIn('status', [Status::Queued->value, Status::Running->value])
            ->update([
                'status' => $status->value,
                'finished_at' => now(),
                'started_at' => $migration->started_at ?? now(),
            ]);

        if ($updated === 0) {
            return;
        }

        $counts = $this->counts($migration);
        $this->activity->log('almacenamiento.traslado_finalizado', $migration, [
            'origen' => $migration->source_disk,
            'destino' => $migration->target_disk,
            'trasladados' => $counts['done'],
            'omitidos' => $counts['skipped'],
            'fallidos' => $counts['failed'],
        ], $failed > 0 ? 'warning' : 'success');
    }

    public function cancel(StorageMigration $migration): void
    {
        if (! $migration->status->isActive()) {
            return;
        }

        if ($migration->batch_id !== null) {
            try {
                Bus::findBatch($migration->batch_id)?->cancel();
            } catch (Throwable $e) {
                Log::warning('No se pudo cancelar el lote del traslado.', ['error' => $e->getMessage()]);
            }
        }

        $migration->forceFill([
            'status' => Status::Cancelled,
            'cancelled_at' => now(),
            'finished_at' => now(),
        ])->save();

        $this->activity->log('almacenamiento.traslado_cancelado', $migration, $this->counts($migration));
    }

    /** Vuelve a poner en cola los fallidos (y lo que quedó pendiente). */
    public function retry(StorageMigration $migration): void
    {
        $other = $this->active();
        if ($other !== null && $other->id !== $migration->id) {
            throw new RuntimeException('Ya hay otro traslado en curso.');
        }

        $migration->items()->where('status', ItemStatus::Failed->value)
            ->update(['status' => ItemStatus::Pending->value, 'attempts' => 0, 'error' => null, 'finished_at' => null]);

        $migration->forceFill([
            'status' => Status::Running,
            'finished_at' => null,
            'cancelled_at' => null,
            'started_at' => $migration->started_at ?? now(),
        ])->save();

        $this->activity->log('almacenamiento.traslado_reintentado', $migration, $this->counts($migration));

        $this->dispatch($migration);
    }

    /**
     * Borra las copias que se conservaron en el origen, solo de archivos que
     * siguen en el destino y cuya copia allá se vuelve a comprobar.
     *
     * @return array{deleted: int, kept: int}
     */
    public function purgeSources(StorageMigration $migration): array
    {
        $deleted = 0;
        $kept = 0;

        $migration->items()->where('status', ItemStatus::Done->value)->where('source_kept', true)
            ->with('media.variants')
            ->chunkById(100, function ($items) use ($migration, &$deleted, &$kept) {
                foreach ($items as $item) {
                    /** @var StorageMigrationItem $item */
                    $media = $item->media;
                    if ($media === null || ! $this->safelyOnTarget($media, $migration->target_disk)) {
                        $kept++;

                        continue;
                    }

                    $paths = array_merge([$media->path], $media->variants->pluck('path')->all());
                    foreach ($paths as $path) {
                        try {
                            Storage::disk($migration->source_disk)->delete($path);
                        } catch (Throwable $e) {
                            Log::warning('No se pudo borrar la copia conservada.', ['path' => $path, 'error' => $e->getMessage()]);
                        }
                    }

                    $item->forceFill(['source_kept' => false])->save();
                    $deleted++;
                }
            });

        $migration->forceFill(['sources_purged_at' => now()])->save();

        $this->activity->log('almacenamiento.copias_eliminadas', $migration, [
            'disco' => $migration->source_disk,
            'eliminadas' => $deleted,
            'conservadas' => $kept,
        ]);

        return ['deleted' => $deleted, 'kept' => $kept];
    }

    /**
     * @return array{total: int, done: int, skipped: int, failed: int, pending: int}
     */
    public function counts(StorageMigration $migration): array
    {
        /** @var array<string, int> $rows */
        $rows = $migration->items()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status')->map(fn ($v) => (int) $v)->all();

        return [
            'total' => array_sum($rows),
            'done' => $rows[ItemStatus::Done->value] ?? 0,
            'skipped' => $rows[ItemStatus::Skipped->value] ?? 0,
            'failed' => $rows[ItemStatus::Failed->value] ?? 0,
            'pending' => $rows[ItemStatus::Pending->value] ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function present(StorageMigration $migration): array
    {
        $counts = $this->counts($migration);
        $processedBytes = (int) $migration->items()->where('status', '!=', ItemStatus::Pending->value)->sum('bytes');
        $transferred = (int) $migration->items()->sum('transferred_bytes');
        $processed = $counts['done'] + $counts['skipped'] + $counts['failed'];

        $eta = null;
        if ($migration->status === Status::Running && $migration->started_at !== null && $processedBytes > 0) {
            $elapsed = max(1, now()->getTimestamp() - $migration->started_at->getTimestamp());
            $rate = $processedBytes / $elapsed;
            $remaining = max(0, $migration->total_bytes - $processedBytes);
            $eta = $rate > 0 ? (int) ceil($remaining / $rate) : null;
        }

        $batch = null;
        if ($migration->batch_id !== null && $migration->status->isActive()) {
            try {
                $batch = Bus::findBatch($migration->batch_id);
            } catch (Throwable) {
                $batch = null;
            }
        }

        return [
            'id' => $migration->id,
            'uuid' => $migration->uuid,
            'sourceDisk' => $migration->source_disk,
            'targetDisk' => $migration->target_disk,
            'direction' => $migration->target_disk === MediaStorage::REMOTE ? 'to_synology' : 'to_local',
            'keepSource' => $migration->keep_source,
            'status' => $migration->status->value,
            'statusLabel' => $migration->status->label(),
            'isActive' => $migration->status->isActive(),
            'totalFiles' => $migration->total_files,
            'totalBytes' => $migration->total_bytes,
            'processedFiles' => $processed,
            'processedBytes' => $processedBytes,
            'transferredBytes' => $transferred,
            'counts' => $counts,
            'etaSeconds' => $eta,
            'waitingForWorker' => $migration->status === Status::Queued && $migration->created_at !== null && $migration->created_at->lt(now()->subMinute()),
            'queuedJobs' => $batch instanceof Batch ? $batch->pendingJobs : null,
            'keptSources' => $migration->items()->where('source_kept', true)->count(),
            'sourcesPurgedAt' => $migration->sources_purged_at?->toIso8601String(),
            'startedAt' => $migration->started_at?->toIso8601String(),
            'finishedAt' => $migration->finished_at?->toIso8601String(),
            'createdAt' => $migration->created_at?->toIso8601String(),
            'failures' => $migration->items()->where('status', ItemStatus::Failed->value)
                ->with('media:id,original_filename,path')
                ->orderBy('id')->limit(100)->get()
                ->map(fn (StorageMigrationItem $item) => [
                    'id' => $item->id,
                    'mediaId' => $item->media_id,
                    'filename' => $item->media->original_filename,
                    'error' => $item->error,
                    'attempts' => $item->attempts,
                ])->values()->all(),
        ];
    }

    private function finish(StorageMigrationItem $item, ItemStatus $status, ?string $error, bool $sourceKept = false): void
    {
        $item->forceFill([
            'status' => $status,
            'error' => $error,
            'source_kept' => $sourceKept,
            'finished_at' => now(),
        ])->save();
    }

    private function involvesRemote(StorageMigration $migration): bool
    {
        return $this->storage->isRemote($migration->source_disk) || $this->storage->isRemote($migration->target_disk);
    }

    private function safelyOnTarget(Media $media, string $target): bool
    {
        if ($media->disk !== $target) {
            return false;
        }

        try {
            $disk = Storage::disk($target);
            if (! $disk->exists($media->path) || (int) $disk->size($media->path) !== $media->size) {
                return false;
            }

            foreach ($media->variants as $variant) {
                if ($variant->disk !== $target || ! $disk->exists($variant->path)) {
                    return false;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    private function assertDisks(string $source, string $target): void
    {
        if ($source === $target || ! in_array($source, self::DISKS, true) || ! in_array($target, self::DISKS, true)) {
            throw new RuntimeException('Dirección de traslado no válida.');
        }
    }
}
