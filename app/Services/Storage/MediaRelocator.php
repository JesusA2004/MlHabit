<?php

namespace App\Services\Storage;

use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Mueve un archivo de la biblioteca (original + TODAS sus variantes) a otro
 * disco, de forma segura y repetible:
 *
 * 1. Copia cada archivo por streaming (sin cargarlo en memoria), respetando
 *    el límite de KB/s del NAS.
 * 2. Verifica cada copia: tamaño siempre y, en el original, la huella
 *    xxh128 releyendo el destino (config media.storage.verify_checksum).
 * 3. Solo entonces actualiza `disk` en la base de datos (una transacción).
 * 4. Solo después borra el origen, y únicamente si no se pidió conservarlo.
 *
 * Idempotente: si el destino ya tiene una copia idéntica no la vuelve a
 * transferir; si todo ya está en el destino, no hace nada. Un candado por
 * archivo evita chocar con la generación de variantes o el procesamiento
 * de video.
 */
class MediaRelocator
{
    public function __construct(private readonly MediaStorage $storage) {}

    public static function lockKey(int $mediaId): string
    {
        return "media-storage:{$mediaId}";
    }

    /**
     * @param  (callable(int): void)|null  $onProgress  bytes transferidos en cada bloque
     *
     * @throws MediaRelocationException
     */
    public function relocate(Media $media, string $target, bool $keepSource = true, int $limitKbps = 0, ?callable $onProgress = null): RelocationResult
    {
        $lock = Cache::lock(self::lockKey($media->id), 3600);
        if (! $lock->get()) {
            throw new MediaRelocationException('El archivo se está procesando en este momento (variantes o video).', retryable: true);
        }

        try {
            $media->refresh();
            $media->load('variants');

            $files = $this->pendingFiles($media, $target);
            if ($files === []) {
                $this->clearPending($media, $target);

                return new RelocationResult(moved: false, files: 0, bytes: 0, transferred: 0);
            }

            $transferred = 0;
            $bytes = 0;
            $computedChecksum = null;

            foreach ($files as $file) {
                [$sent, $size, $checksum] = $this->copy($file['model'], $file['disk'], $file['path'], $target, $limitKbps, $onProgress);
                $transferred += $sent;
                $bytes += $size;
                if ($file['model'] instanceof Media) {
                    $computedChecksum = $checksum;
                }
            }

            DB::transaction(function () use ($files, $target, $media, $computedChecksum) {
                foreach ($files as $file) {
                    $file['model']->forceFill(['disk' => $target])->save();
                }

                $updates = [];
                if ($media->pending_sync_to !== null) {
                    $updates = ['pending_sync_to' => null, 'sync_error' => null, 'sync_attempts' => 0];
                }
                if ($media->checksum === null && $computedChecksum !== null) {
                    $updates['checksum'] = $computedChecksum;
                }
                if ($updates !== []) {
                    $media->forceFill($updates)->save();
                }
            });

            if (! $keepSource) {
                foreach ($files as $file) {
                    $this->deleteQuietly($file['disk'], $file['path']);
                }
            }

            return new RelocationResult(moved: true, files: count($files), bytes: $bytes, transferred: $transferred);
        } finally {
            $lock->release();
        }
    }

    /**
     * Archivos de este Media que todavía no están en `$target`.
     *
     * @return list<array{model: Media|MediaVariant, disk: string, path: string}>
     */
    public function pendingFiles(Media $media, string $target): array
    {
        $files = [];
        if ($media->disk !== $target) {
            $files[] = ['model' => $media, 'disk' => $media->disk, 'path' => $media->path];
        }

        foreach ($media->variants as $variant) {
            if ($variant->disk !== $target) {
                $files[] = ['model' => $variant, 'disk' => $variant->disk, 'path' => $variant->path];
            }
        }

        return $files;
    }

    /**
     * @return array{0: int, 1: int, 2: string|null} [bytes transferidos, tamaño, huella del original]
     *
     * @throws MediaRelocationException
     */
    private function copy(Media|MediaVariant $model, string $sourceDisk, string $path, string $target, int $limitKbps, ?callable $onProgress): array
    {
        $source = Storage::disk($sourceDisk);
        $destination = Storage::disk($target);
        $isOriginal = $model instanceof Media;
        $expectedChecksum = $isOriginal ? $model->checksum : null;
        $verifyChecksum = $isOriginal && (bool) config('media.storage.verify_checksum', true);

        $sourceSize = $this->size($source, $path);

        // ¿Ya hay una copia idéntica en el destino? (traslado anterior que se
        // interrumpió, o copia conservada al regresar del NAS).
        $existingSize = $this->size($destination, $path);
        if ($existingSize !== null && ($sourceSize === null || $existingSize === $sourceSize)) {
            $expected = $sourceSize ?? $model->size;
            $sizeOk = $expected === null || $existingSize === (int) $expected;
            $checksumOk = ! $verifyChecksum || $expectedChecksum === null
                || $this->checksum($destination, $path) === $expectedChecksum;

            if ($sizeOk && $checksumOk) {
                return [0, $existingSize, $expectedChecksum];
            }
        }

        if ($sourceSize === null) {
            throw new MediaRelocationException("No se encontró el archivo en el origen ({$sourceDisk}): {$path}", retryable: false);
        }

        try {
            $in = $source->readStream($path);
        } catch (Throwable $e) {
            throw new MediaRelocationException('No se pudo leer el archivo de origen: '.$this->storage->safeError($e), retryable: true, previous: $e);
        }
        if (! is_resource($in)) {
            throw new MediaRelocationException("No se pudo leer el archivo de origen ({$sourceDisk}).", retryable: true);
        }

        $hash = hash_init('xxh128');
        $stream = ThrottledStream::wrap($in, $limitKbps, $hash, $onProgress);

        try {
            $written = $destination->writeStream($path, $stream);
        } catch (Throwable $e) {
            $this->deleteQuietly($target, $path);
            throw new MediaRelocationException('No se pudo escribir en el destino: '.$this->storage->safeError($e), retryable: true, previous: $e);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($written === false) {
            $this->deleteQuietly($target, $path);
            throw new MediaRelocationException("El destino ({$target}) rechazó la escritura.", retryable: true);
        }

        $sourceChecksum = hash_final($hash);

        // Verificación: tamaño siempre; huella del original releyendo el destino.
        $copiedSize = $this->size($destination, $path);
        if ($copiedSize !== $sourceSize) {
            $this->deleteQuietly($target, $path);
            throw new MediaRelocationException("La copia quedó incompleta ({$copiedSize} de {$sourceSize} bytes).", retryable: true);
        }

        if ($expectedChecksum !== null && $sourceChecksum !== $expectedChecksum) {
            $this->deleteQuietly($target, $path);
            throw new MediaRelocationException('El archivo de origen no coincide con su huella registrada (posible archivo dañado).', retryable: false);
        }

        if ($verifyChecksum && $this->checksum($destination, $path) !== $sourceChecksum) {
            $this->deleteQuietly($target, $path);
            throw new MediaRelocationException('La copia en el destino no coincide con el original (huella distinta).', retryable: true);
        }

        return [$sourceSize, $sourceSize, $isOriginal ? $sourceChecksum : null];
    }

    private function size(Filesystem $disk, string $path): ?int
    {
        try {
            if (! $disk->exists($path)) {
                return null;
            }

            return (int) $disk->size($path);
        } catch (Throwable $e) {
            throw new MediaRelocationException('No se pudo consultar el almacenamiento: '.$this->storage->safeError($e), retryable: true, previous: $e);
        }
    }

    private function checksum(Filesystem $disk, string $path): ?string
    {
        try {
            $stream = $disk->readStream($path);
        } catch (Throwable) {
            return null;
        }
        if (! is_resource($stream)) {
            return null;
        }

        $hash = hash_init('xxh128');
        hash_update_stream($hash, $stream);
        fclose($stream);

        return hash_final($hash);
    }

    private function clearPending(Media $media, string $target): void
    {
        if ($media->pending_sync_to === $target) {
            $media->forceFill(['pending_sync_to' => null, 'sync_error' => null, 'sync_attempts' => 0])->save();
        }
    }

    private function deleteQuietly(string $disk, string $path): void
    {
        try {
            Storage::disk($disk)->delete($path);
        } catch (Throwable $e) {
            Log::warning('No se pudo borrar un archivo durante el traslado.', ['disk' => $disk, 'path' => $path, 'error' => $e->getMessage()]);
        }
    }
}
