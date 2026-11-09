<?php

namespace App\Services;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Enums\MediaVisibility;
use App\Exceptions\RejectedUploadException;
use App\Jobs\GenerateImageVariants;
use App\Jobs\ProcessVideoMedia;
use App\Models\Media;
use App\Models\MediaCollection;
use App\Models\StorageMigrationItem;
use App\Models\StorageProvider;
use App\Services\Storage\FallbackSpaceException;
use App\Services\Storage\MediaStorage;
use App\Support\MediaPaths;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sube archivos al almacenamiento y crea su registro en la biblioteca.
 *
 * Seguridad: solo entra lo que está en la lista blanca de config/media.php
 * (extensión + MIME real detectado por contenido). Un script renombrado a
 * "foto.jpg" se rechaza porque su contenido no es image/jpeg. El MIME que
 * se guarda es el detectado, nunca el declarado por el navegador.
 *
 * Visibilidad: `public` va al disco de medios público (/media) y
 * `private` al disco `media_private`, que solo se sirve vía
 * PrivateMediaController (`archivos.show`) tras autorizar.
 *
 * Respaldo: si el proveedor activo es el NAS y no responde, el archivo se
 * guarda en el servidor (`media_local`) y queda marcado como pendiente de
 * sincronizar (`pending_sync_to`); ver MediaStorage y media:sync-pending.
 */
class MediaUploadService
{
    public function __construct(private readonly MediaStorage $storage) {}

    public function upload(UploadedFile $file, ?string $collectionPath = null, MediaVisibility $visibility = MediaVisibility::Public): Media
    {
        $realPath = $file->getRealPath();
        if ($realPath === false) {
            throw new RejectedUploadException('No se pudo leer el archivo subido.');
        }

        return $this->ingest(
            $realPath,
            $file->getClientOriginalName(),
            $file->getClientMimeType(),
            (int) $file->getSize(),
            $collectionPath,
            $visibility,
        );
    }

    /**
     * @param  string  $mimeType  Tipo declarado por el cliente; solo informativo.
     */
    public function ingest(
        string $absoluteTempPath,
        string $originalFilename,
        string $mimeType,
        int $size,
        ?string $collectionPath = null,
        MediaVisibility $visibility = MediaVisibility::Public,
    ): Media {
        $detectedMime = $this->assertAllowed($absoluteTempPath, $originalFilename, $size);

        $disk = $visibility->disk($this->activeDisk());
        $collection = $this->resolveCollection($collectionPath ?? ($visibility === MediaVisibility::Private ? 'privado/general' : 'sitio/general'));
        $type = MediaType::fromMime($detectedMime);

        ['path' => $path, 'filename' => $filename] = MediaPaths::forUpload($collection->path, $type, $originalFilename, $this->extension($originalFilename));

        $storedDisk = $this->putFile($disk, $path, $absoluteTempPath);
        $fellBack = $storedDisk !== $disk;

        [$width, $height] = $type === MediaType::Image ? $this->dimensions($absoluteTempPath) : [null, null];

        $media = Media::query()->create([
            'visibility' => $visibility,
            'media_collection_id' => $collection->id,
            'uploaded_by' => auth()->id(),
            'disk' => $storedDisk,
            'pending_sync_to' => $fellBack ? $disk : null,
            'sync_error' => $fellBack ? $this->storage->downReason($disk) : null,
            'checksum' => $this->checksum($absoluteTempPath),
            'path' => $path,
            'filename' => $filename,
            'original_filename' => mb_substr($originalFilename, 0, 255),
            'type' => $type->value,
            'mime_type' => $detectedMime,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'status' => $type === MediaType::Video ? MediaStatus::Processing->value : MediaStatus::Ready->value,
        ]);

        $this->dispatchProcessing($media);

        return $media;
    }

    /**
     * Reemplaza el archivo conservando id, uuid, metadatos y vínculos. El
     * archivo anterior y sus variantes se eliminan; se regeneran variantes.
     */
    public function replace(Media $media, UploadedFile $file): Media
    {
        $realPath = $file->getRealPath();
        if ($realPath === false) {
            throw new RejectedUploadException('No se pudo leer el archivo subido.');
        }

        $detectedMime = $this->assertAllowed($realPath, $file->getClientOriginalName(), (int) $file->getSize());
        $type = MediaType::fromMime($detectedMime);

        if ($type !== $media->type) {
            throw new RejectedUploadException('El archivo de reemplazo debe ser del mismo tipo que el original.');
        }

        $collectionPath = $media->loadMissing('collection')->collection?->path ?? dirname($media->path);
        ['path' => $newPath, 'filename' => $filename] = MediaPaths::forUpload($collectionPath, $type, $file->getClientOriginalName(), $this->extension($file->getClientOriginalName()));

        $preferredDisk = $media->pending_sync_to ?? $media->disk;
        $storedDisk = $this->putFile($preferredDisk, $newPath, $realPath);
        $this->deleteFiles($media);

        [$width, $height] = $type === MediaType::Image ? $this->dimensions($realPath) : [null, null];

        $media->forceFill([
            'disk' => $storedDisk,
            'pending_sync_to' => $storedDisk !== $preferredDisk ? $preferredDisk : null,
            'checksum' => $this->checksum($realPath),
            'path' => $newPath,
            'filename' => $filename,
            'original_filename' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $detectedMime,
            'size' => (int) $file->getSize(),
            'width' => $width,
            'height' => $height,
            'duration_seconds' => null,
            'metadata' => null,
            'status' => $type === MediaType::Video ? MediaStatus::Processing->value : MediaStatus::Ready->value,
        ])->save();

        $this->dispatchProcessing($media);

        return $media->refresh();
    }

    /**
     * Borra del disco el original y todas sus variantes (y sus registros),
     * incluidas las copias que se hayan conservado en el disco de origen
     * de un traslado servidor ⇄ NAS.
     */
    public function deleteFiles(Media $media): void
    {
        $media->loadMissing('variants');
        $paths = [$media->path];
        foreach ($media->variants as $variant) {
            $paths[] = $variant->path;
            $this->deleteQuietly($variant->disk, $variant->path);
            $variant->delete();
        }

        $this->deleteQuietly($media->disk, $media->path);

        $kept = StorageMigrationItem::query()
            ->where('media_id', $media->id)
            ->where('source_kept', true)
            ->with('migration:id,source_disk')
            ->get();
        foreach ($kept as $item) {
            if ($item->migration->source_disk !== $media->disk) {
                foreach ($paths as $path) {
                    $this->deleteQuietly($item->migration->source_disk, $path);
                }
            }
            $item->forceFill(['source_kept' => false])->save();
        }
    }

    private function deleteQuietly(string $disk, string $path): void
    {
        try {
            Storage::disk($disk)->delete($path);
        } catch (\Throwable $e) {
            // El NAS puede no responder: el registro se borra igual y el archivo
            // queda huérfano en el NAS (no es accesible desde la plataforma).
            report($e);
        }
    }

    private function checksum(string $absolutePath): ?string
    {
        $hash = @hash_file('xxh128', $absolutePath);

        return $hash === false ? null : $hash;
    }

    /**
     * Valida extensión, MIME real y tamaño. Devuelve el MIME detectado.
     *
     * @throws RejectedUploadException
     */
    public function assertAllowed(string $absolutePath, string $originalFilename, int $size): string
    {
        $extension = $this->extension($originalFilename);
        /** @var array<string, list<string>> $allowed */
        $allowed = config('media.allowed', []);

        if (! array_key_exists($extension, $allowed)) {
            throw new RejectedUploadException('Tipo de archivo no permitido. Usa JPG, PNG, WebP, AVIF, GIF, PDF, MP4, WebM o MOV.');
        }

        $detected = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($absolutePath);

        if (! in_array($detected, $allowed[$extension], true)) {
            throw new RejectedUploadException("El contenido del archivo no corresponde a un .{$extension} válido.");
        }

        $limitMb = match (MediaType::fromMime($detected)) {
            MediaType::Image => (int) config('media.max_image_mb', 40),
            MediaType::Document => (int) config('media.max_document_mb', 25),
            default => (int) config('media.max_upload_mb', 2048),
        };

        if ($size > $limitMb * 1024 * 1024) {
            throw new RejectedUploadException("El archivo supera el tamaño máximo permitido ({$limitMb} MB).");
        }

        return $detected;
    }

    /** @return list<string> */
    public static function allowedExtensions(): array
    {
        return array_keys((array) config('media.allowed', []));
    }

    public static function isAllowedFilename(string $filename): bool
    {
        return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), self::allowedExtensions(), true);
    }

    public function activeDisk(): string
    {
        $provider = StorageProvider::query()->where('is_active', true)->first();

        return $provider?->configValue('disk', 'media_local') ?? 'media_local';
    }

    public function resolveCollection(string $path): MediaCollection
    {
        $segments = array_values(array_filter(
            array_map(fn ($s) => Str::slug($s), explode('/', trim($path, '/'))),
            fn ($s) => $s !== '',
        ));
        if ($segments === []) {
            $segments = ['sitio', 'general'];
        }

        $parentId = null;
        $currentPath = '';
        $collection = null;

        foreach ($segments as $segment) {
            $currentPath = $currentPath === '' ? $segment : "{$currentPath}/{$segment}";

            $collection = MediaCollection::query()->firstOrCreate(
                ['path' => $currentPath],
                ['parent_id' => $parentId, 'name' => Str::headline($segment), 'slug' => $segment]
            );

            $parentId = $collection->id;
        }

        /** @var MediaCollection $collection */
        return $collection;
    }

    private function dispatchProcessing(Media $media): void
    {
        if ($media->type === MediaType::Video) {
            ProcessVideoMedia::dispatch($media->id);
        } elseif ($media->type === MediaType::Image) {
            GenerateImageVariants::dispatch($media->id);
        }
    }

    /** Devuelve el disco donde quedó (el servidor si el NAS no respondió). */
    private function putFile(string $disk, string $path, string $absoluteSource): string
    {
        $stream = fopen($absoluteSource, 'r');
        if ($stream === false) {
            throw new RejectedUploadException('No se pudo leer el archivo temporal.');
        }

        try {
            return $this->storage->put($disk, $path, $stream);
        } catch (FallbackSpaceException $e) {
            throw new RejectedUploadException($e->getMessage());
        } catch (\RuntimeException $e) {
            throw new RejectedUploadException('No se pudo guardar el archivo. Inténtalo de nuevo en unos minutos.');
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function extension(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'bin';
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function dimensions(string $absolutePath): array
    {
        $size = @getimagesize($absolutePath);

        return $size ? [$size[0], $size[1]] : [null, null];
    }
}
