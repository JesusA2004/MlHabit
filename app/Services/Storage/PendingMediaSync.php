<?php

namespace App\Services\Storage;

use App\Models\Media;
use App\Services\ActivityLogger;
use App\Services\MediaUploadService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Empuja al NAS los archivos que se guardaron en el servidor mientras el
 * NAS no respondía (`media.pending_sync_to`). Lo ejecuta el programador
 * cada 10 minutos (`media:sync-pending`) y también al probar con éxito la
 * conexión desde /admin/almacenamiento.
 */
class PendingMediaSync
{
    public function __construct(
        private readonly MediaStorage $storage,
        private readonly MediaRelocator $relocator,
        private readonly MediaUploadService $uploader,
        private readonly ActivityLogger $activity,
    ) {}

    public function pendingCount(): int
    {
        return Media::withTrashed()->whereNotNull('pending_sync_to')->count();
    }

    /**
     * @return array{status: 'idle'|'unreachable'|'synced'|'partial'|'cleared', pushed: int, failed: int, remaining: int, message: string}
     */
    public function run(int $limit = 200): array
    {
        $targets = Media::withTrashed()->whereNotNull('pending_sync_to')->distinct()->pluck('pending_sync_to')->all();
        if ($targets === []) {
            return ['status' => 'idle', 'pushed' => 0, 'failed' => 0, 'remaining' => 0, 'message' => 'No hay archivos pendientes de sincronizar.'];
        }

        $pushed = 0;
        $failed = 0;
        $unreachable = false;
        $cleared = 0;

        foreach ($targets as $target) {
            $target = (string) $target;

            // Si el NAS dejó de ser el proveedor activo, los archivos se
            // quedan en el servidor (donde ya funcionan) y se quita la marca.
            if ($this->uploader->activeDisk() !== $target) {
                $cleared += Media::withTrashed()->where('pending_sync_to', $target)
                    ->update(['pending_sync_to' => null, 'sync_error' => null, 'sync_attempts' => 0]);

                continue;
            }

            $problem = $this->storage->probe($target);
            if ($problem !== null) {
                $this->storage->markDown($target, $problem);
                Log::info('media:sync-pending: el NAS sigue sin responder.', ['disk' => $target, 'error' => $problem]);
                $unreachable = true;

                continue;
            }

            $this->storage->markUp($target);
            $limitKbps = $this->storage->transferLimitKbps();

            // Primero los que menos han fallado: un archivo que falla siempre
            // no bloquea a los nuevos.
            Media::withTrashed()->where('pending_sync_to', $target)
                ->orderBy('sync_attempts')->orderBy('id')->limit($limit)->get()
                ->each(function (Media $media) use ($target, $limitKbps, &$pushed, &$failed) {
                    try {
                        $this->relocator->relocate($media, $target, keepSource: false, limitKbps: $limitKbps);
                        $pushed++;
                    } catch (Throwable $e) {
                        $failed++;
                        $media->forceFill([
                            'sync_attempts' => $media->sync_attempts + 1,
                            'sync_error' => $this->storage->safeError($e),
                        ])->save();
                    }
                });
        }

        $remaining = $this->pendingCount();

        if ($pushed > 0 || $failed > 0) {
            $this->activity->log('almacenamiento.sincronizado', null, [
                'subidos' => $pushed,
                'fallidos' => $failed,
                'pendientes' => $remaining,
            ], $failed > 0 ? 'warning' : 'success');
        }

        if ($unreachable && $pushed === 0) {
            return ['status' => 'unreachable', 'pushed' => 0, 'failed' => $failed, 'remaining' => $remaining, 'message' => 'El NAS sigue sin responder; se reintentará más tarde.'];
        }

        if ($cleared > 0 && $pushed === 0 && $failed === 0) {
            return ['status' => 'cleared', 'pushed' => 0, 'failed' => 0, 'remaining' => $remaining, 'message' => "El NAS ya no es el proveedor activo: {$cleared} archivos se quedan en el servidor."];
        }

        return [
            'status' => $remaining === 0 && $failed === 0 ? 'synced' : 'partial',
            'pushed' => $pushed,
            'failed' => $failed,
            'remaining' => $remaining,
            'message' => "Se subieron {$pushed} archivos al NAS".($failed > 0 ? "; {$failed} fallaron" : '').($remaining > 0 ? "; quedan {$remaining} pendientes." : '.'),
        ];
    }
}
