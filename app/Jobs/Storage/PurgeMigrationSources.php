<?php

namespace App\Jobs\Storage;

use App\Models\StorageMigration;
use App\Services\Storage\StorageMigrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Libera espacio: borra las copias conservadas en el origen de un traslado
 * ya terminado, comprobando antes que cada archivo sigue en el destino.
 */
class PurgeMigrationSources implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(public int $migrationId) {}

    public function handle(StorageMigrationService $migrations): void
    {
        $migration = StorageMigration::query()->find($this->migrationId);
        if ($migration === null || $migration->status->isActive()) {
            return;
        }

        $migrations->purgeSources($migration);
    }
}
