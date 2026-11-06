<?php

namespace App\Jobs\Storage;

use App\Models\StorageMigrationItem;
use App\Services\Storage\StorageMigrationService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Traslada un archivo (original + variantes) dentro de un traslado de la
 * biblioteca. Los reintentos por archivo se hacen dentro del servicio para
 * dejar el resultado registrado; un tiempo agotado se marca como fallido.
 */
class MigrateMediaItem implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 1;

    /** Un video de 2 GB a 2 MB/s tarda ~17 min; se deja holgura. */
    public int $timeout = 3600;

    public function __construct(public int $itemId) {}

    public function handle(StorageMigrationService $migrations): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $item = StorageMigrationItem::query()->find($this->itemId);
        if ($item === null) {
            return;
        }

        $migrations->processItem($item);
    }

    public function failed(?Throwable $exception): void
    {
        $item = StorageMigrationItem::query()->find($this->itemId);
        if ($item !== null) {
            app(StorageMigrationService::class)->markItemFailed($item, 'El proceso se interrumpió: '.mb_substr((string) $exception?->getMessage(), 0, 400));
        }
    }
}
