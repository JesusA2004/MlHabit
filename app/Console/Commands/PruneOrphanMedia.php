<?php

namespace App\Console\Commands;

use App\Enums\MediaVisibility;
use App\Models\Media;
use App\Services\MediaUploadService;
use App\Services\MediaUsageService;
use Illuminate\Console\Command;

/**
 * Purga archivos PRIVADOS que nunca se vincularon a una entidad (p. ej. un
 * formulario abandonado tras subir fotos). Primero se marcan como
 * eliminados (soft delete) y luego se borran sus archivos del disco.
 */
class PruneOrphanMedia extends Command
{
    protected $signature = 'media:prune-orphans {--hours= : Antigüedad mínima en horas (por defecto config media.orphan_hours)} {--dry-run : Solo muestra cuántos se purgarían}';

    protected $description = 'Elimina archivos privados sin vincular con más de 24 h de antigüedad';

    public function handle(MediaUploadService $uploader, MediaUsageService $usage): int
    {
        $hours = (int) ($this->option('hours') ?: config('media.orphan_hours', 24));

        $query = Media::query()
            ->where('visibility', MediaVisibility::Private->value)
            ->where('created_at', '<', now()->subHours($hours))
            ->whereDoesntHave('attachments');

        if ($this->option('dry-run')) {
            $this->info('Archivos privados huérfanos: '.$query->count());

            return self::SUCCESS;
        }

        $count = 0;
        $query->with('variants')->chunkById(100, function ($items) use ($uploader, $usage, &$count) {
            foreach ($items as $media) {
                /** @var Media $media */
                if ($usage->isUsed($media)) {
                    continue;
                }
                $media->delete();
                $uploader->deleteFiles($media);
                $count++;
            }
        });

        $this->info("Archivos privados huérfanos eliminados: {$count}");

        return self::SUCCESS;
    }
}
