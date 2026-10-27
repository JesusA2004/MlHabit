<?php

namespace App\Services;

use App\Enums\UploadSessionStatus;
use App\Exceptions\IncompleteUploadException;
use App\Exceptions\InvalidChunkException;
use App\Models\Media;
use App\Models\UploadSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Subida reanudable por fragmentos para archivos grandes (sobre todo
 * video). Los fragmentos se acumulan en un archivo temporal privado
 * (disco "local") y, al completarse, se entregan a MediaUploadService
 * para ingresar a la biblioteca con el mismo flujo que una subida simple.
 *
 * No depende de que una única petición HTTP viva todo el tiempo que tarda
 * la subida: cada fragmento es su propia petición corta. La integridad se
 * garantiza con tres mecanismos: (1) un bloqueo de fila en la sesión que
 * serializa escrituras concurrentes, (2) un índice de fragmento esperado
 * que rechaza desorden y hace idempotentes los reintentos, y (3) una
 * verificación de tamaño (y checksum opcional) antes de dar la subida
 * por completa.
 */
class ChunkedUploadService
{
    private const TEMP_DIR = 'uploads-tmp';

    public function start(
        string $filename,
        string $mimeType,
        int $totalSize,
        ?int $userId,
        ?string $collectionPath,
        ?string $storageProviderKey,
        MediaUploadService $uploader,
        ?string $checksumSha256 = null,
    ): UploadSession {
        $uuid = (string) Str::uuid();
        $collection = $uploader->resolveCollection($collectionPath ?? 'mr-insight/general');

        return UploadSession::query()->create([
            'uuid' => $uuid,
            'user_id' => $userId,
            'media_collection_id' => $collection->id,
            'storage_provider_key' => $storageProviderKey,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'total_size' => $totalSize,
            'received_size' => 0,
            'chunk_size' => config('media.chunk_size_mb') * 1024 * 1024,
            'next_chunk_index' => 0,
            'checksum_sha256' => $checksumSha256,
            'status' => UploadSessionStatus::Uploading->value,
            'temp_path' => self::TEMP_DIR.'/'.$uuid.'.part',
            'expires_at' => now()->addHours(24),
        ]);
    }

    /**
     * Agrega un fragmento de forma segura ante reintentos y concurrencia.
     *
     * El bloqueo de fila (`lockForUpdate`) serializa cualquier petición
     * concurrente para la misma sesión: si dos fragmentos llegan a la vez,
     * la segunda transacción espera a que la primera confirme antes de leer
     * `next_chunk_index`, así que nunca se escriben dos fragmentos a la vez
     * sobre el mismo archivo temporal.
     */
    public function appendChunk(UploadSession $session, UploadedFile $chunk, int $chunkIndex): UploadSession
    {
        return DB::transaction(function () use ($session, $chunk, $chunkIndex) {
            $locked = UploadSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [UploadSessionStatus::Pending, UploadSessionStatus::Uploading], true)) {
                throw new InvalidChunkException('La sesión de subida ya no acepta fragmentos.');
            }

            if ($locked->expires_at !== null && $locked->expires_at->isPast()) {
                throw new InvalidChunkException('La sesión de subida expiró.');
            }

            // Reintento de un fragmento ya aplicado: responde el estado
            // actual sin volver a escribir (idempotente).
            if ($chunkIndex < $locked->next_chunk_index) {
                return $locked;
            }

            if ($chunkIndex > $locked->next_chunk_index) {
                throw new InvalidChunkException('El fragmento llegó fuera de orden.');
            }

            $disk = Storage::disk('local');
            $absolutePath = $disk->path($locked->temp_path);

            if (! is_dir(dirname($absolutePath))) {
                mkdir(dirname($absolutePath), 0755, true);
            }

            $out = fopen($absolutePath, 'ab');
            if ($out === false) {
                throw new \RuntimeException('No se pudo escribir el fragmento de subida.');
            }

            if (! flock($out, LOCK_EX)) {
                fclose($out);
                throw new \RuntimeException('No se pudo bloquear el archivo temporal de subida.');
            }

            $in = fopen($chunk->getRealPath(), 'rb');
            if ($in === false) {
                flock($out, LOCK_UN);
                fclose($out);
                throw new \RuntimeException('No se pudo leer el fragmento recibido.');
            }

            $written = stream_copy_to_stream($in, $out);
            fclose($in);
            flock($out, LOCK_UN);
            fclose($out);

            $locked->update([
                'received_size' => $locked->received_size + ($written ?: 0),
                'next_chunk_index' => $locked->next_chunk_index + 1,
            ]);

            return $locked;
        });
    }

    public function complete(UploadSession $session, MediaUploadService $uploader): Media
    {
        $disk = Storage::disk('local');
        $absolutePath = $disk->path($session->temp_path);

        if (! file_exists($absolutePath)) {
            throw new \RuntimeException('No se encontró el archivo temporal de la subida.');
        }

        $actualSize = filesize($absolutePath) ?: 0;

        if ($actualSize !== $session->total_size) {
            throw new IncompleteUploadException(sprintf(
                'La subida está incompleta: se recibieron %d de %d bytes esperados.',
                $actualSize,
                $session->total_size,
            ));
        }

        if ($session->checksum_sha256 !== null) {
            $actualChecksum = hash_file('sha256', $absolutePath);
            if ($actualChecksum === false || ! hash_equals($session->checksum_sha256, $actualChecksum)) {
                @unlink($absolutePath);
                $session->update([
                    'status' => UploadSessionStatus::Failed->value,
                    'error_message' => 'El checksum del archivo ensamblado no coincide con el enviado por el cliente.',
                ]);

                throw new IncompleteUploadException('No se pudo verificar la integridad del archivo (checksum inválido).');
            }
        }

        $media = $uploader->ingest(
            $absolutePath,
            $session->filename,
            $session->mime_type ?? 'application/octet-stream',
            $actualSize,
            $session->collection?->path,
        );

        @unlink($absolutePath);

        $session->update([
            'status' => UploadSessionStatus::Completed->value,
            'media_id' => $media->id,
        ]);

        return $media;
    }

    /**
     * Aborta y borra de inmediato el archivo temporal: una cancelación real
     * no deja bytes huérfanos en disco esperando al barrido por expiración.
     */
    public function abort(UploadSession $session): void
    {
        $disk = Storage::disk('local');
        if ($session->temp_path && $disk->exists($session->temp_path)) {
            $disk->delete($session->temp_path);
        }

        $session->update(['status' => UploadSessionStatus::Aborted->value]);
    }

    /**
     * Limpia sesiones de subida abandonadas (sin actividad y ya vencidas).
     */
    public function pruneExpired(): int
    {
        $disk = Storage::disk('local');
        $expired = UploadSession::query()
            ->whereIn('status', [UploadSessionStatus::Pending->value, UploadSessionStatus::Uploading->value])
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $session) {
            if ($session->temp_path && $disk->exists($session->temp_path)) {
                $disk->delete($session->temp_path);
            }
            $session->update(['status' => UploadSessionStatus::Aborted->value]);
        }

        return $expired->count();
    }
}
