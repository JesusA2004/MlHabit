<?php

namespace App\Console\Commands\Storage;

use App\Enums\StorageMigrationItemStatus;
use App\Services\Storage\MediaStorage;
use App\Services\Storage\StorageMigrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Number;
use Throwable;

/**
 * php artisan media:migrate --to=synology|local [--delete-source] [--dry-run] [--queue]
 *
 * Traslada la biblioteca pública entre el servidor y el NAS. Por defecto
 * corre aquí mismo con barra de progreso; con --queue se manda a la cola
 * (igual que el botón de /admin/almacenamiento). Sin --delete-source se
 * conserva la copia del origen.
 */
class MigrateMediaStorage extends Command
{
    protected $signature = 'media:migrate
        {--to= : Destino: synology o local}
        {--delete-source : Borra la copia del origen tras verificar cada archivo}
        {--dry-run : Solo muestra cuántos archivos y cuánto espacio se moverían}
        {--queue : Envía el traslado a la cola en lugar de ejecutarlo aquí}';

    protected $description = 'Mueve la multimedia del servidor al Synology (o de regreso), verificando cada archivo';

    public function handle(StorageMigrationService $migrations): int
    {
        $to = strtolower((string) $this->option('to'));
        [$source, $target] = match ($to) {
            'synology', 'nas' => [MediaStorage::LOCAL, MediaStorage::REMOTE],
            'local', 'servidor' => [MediaStorage::REMOTE, MediaStorage::LOCAL],
            default => [null, null],
        };

        if ($source === null || $target === null) {
            $this->error('Indica el destino: --to=synology o --to=local');

            return self::INVALID;
        }

        $plan = $migrations->plan($source);
        $this->info("Origen: {$source} → destino: {$target}");
        $this->line("Archivos por mover: {$plan['files']} (".Number::fileSize($plan['bytes'], 1).', incluye variantes)');

        if ($this->option('dry-run')) {
            $this->comment('Simulación: no se movió nada.');

            return self::SUCCESS;
        }

        if ($plan['files'] === 0) {
            $this->info('No hay nada que mover.');

            return self::SUCCESS;
        }

        $keepSource = ! $this->option('delete-source');

        try {
            if ($this->option('queue')) {
                $migration = $migrations->start($source, $target, $keepSource);
                $this->info("Traslado #{$migration->id} enviado a la cola. Revisa el avance en /admin/almacenamiento.");

                return self::SUCCESS;
            }

            $migration = $migrations->create($source, $target, $keepSource);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $bar = $this->output->createProgressBar($migration->total_files);
        $bar->start();

        $migration->items()->where('status', StorageMigrationItemStatus::Pending->value)->orderBy('id')
            ->chunkById(100, function ($items) use ($migrations, $bar) {
                foreach ($items as $item) {
                    $migrations->processItem($item);
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        $counts = $migrations->counts($migration);
        $this->table(['Trasladados', 'Ya estaban', 'Fallidos'], [[$counts['done'], $counts['skipped'], $counts['failed']]]);

        foreach ($migration->items()->where('status', StorageMigrationItemStatus::Failed->value)->with('media:id,original_filename')->limit(50)->get() as $failed) {
            $this->warn('· '.($failed->media->original_filename).': '.$failed->error);
        }

        if ($keepSource && $counts['done'] > 0) {
            $this->comment('Se conservó la copia en el origen. Puedes liberarla desde /admin/almacenamiento cuando verifiques el destino.');
        }

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
