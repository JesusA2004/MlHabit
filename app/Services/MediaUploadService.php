<?php

namespace App\Services;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Jobs\GenerateImageVariants;
use App\Jobs\ProcessVideoMedia;
use App\Models\Media;
use App\Models\MediaCollection;
use App\Models\StorageProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sube archivos al proveedor de almacenamiento activo y crea su registro en
 * la biblioteca multimedia. `upload()` sirve subidas pequeñas y síncronas
 * (portadas, imágenes de galería); `ingest()` es el núcleo compartido que
 * también usa el ensamblador de subidas por partes (ChunkedUploadService)
 * para archivos grandes.
 */
class MediaUploadService
{
    /**
     * MIME reales (detectados por contenido, no por la extensión ni por el
     * encabezado que declaró el cliente) que nunca deben entrar a la
     * biblioteca, sin importar qué extensión o mimeType declarado traiga el
     * archivo. Cubre el caso central: un script PHP (o HTML, o un
     * ejecutable) renombrado como "foto.jpg" para intentar quedar servido
     * desde el disco de medios.
     *
     * @var list<string>
     */
    private const DENIED_MIME_TYPES = [
        'text/x-php',
        'application/x-php',
        'application/x-httpd-php',
        'text/html',
        'application/xhtml+xml',
        'text/x-shellscript',
        'application/x-sh',
        'application/x-executable',
        'application/x-elf',
        'application/x-msdownload',
        'application/x-msdos-program',
    ];

    public function upload(UploadedFile $file, ?string $collectionPath = null): Media
    {
        $realPath = $file->getRealPath();
        if ($realPath === false) {
            throw new \RuntimeException('No se pudo leer el archivo subido.');
        }

        return $this->ingest(
            $realPath,
            $file->getClientOriginalName(),
            $file->getMimeType() ?? 'application/octet-stream',
            $file->getSize(),
            $collectionPath,
        );
    }

    /**
     * @param  string  $mimeType  Tipo declarado (header del cliente o el que
     *                            registró la sesión de subida por
     *                            fragmentos). Se usa para decidir qué
     *                            pipeline corre (imagen/video) — nunca para
     *                            decidir si el archivo es peligroso, eso lo
     *                            decide únicamente el contenido real.
     */
    public function ingest(
        string $absoluteTempPath,
        string $originalFilename,
        string $mimeType,
        int $size,
        ?string $collectionPath = null,
    ): Media {
        $detectedMime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($absoluteTempPath);

        if (in_array($detectedMime, self::DENIED_MIME_TYPES, true)) {
            throw new \RuntimeException("Tipo de archivo no permitido ({$detectedMime}).");
        }

        $disk = $this->activeDisk();
        $collection = $this->resolveCollection($collectionPath ?? 'mr-insight/general');
        $type = MediaType::fromMime($mimeType);

        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION) ?: 'bin';
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $collection->path.'/'.$filename;

        $stream = fopen($absoluteTempPath, 'r');
        if ($stream === false) {
            throw new \RuntimeException('No se pudo leer el archivo temporal.');
        }

        Storage::disk($disk)->put($path, $stream);
        fclose($stream);

        [$width, $height] = $type === MediaType::Image ? $this->dimensions($absoluteTempPath) : [null, null];

        $media = Media::query()->create([
            'media_collection_id' => $collection->id,
            'uploaded_by' => auth()->id(),
            'disk' => $disk,
            'path' => $path,
            'filename' => $filename,
            'original_filename' => $originalFilename,
            'type' => $type->value,
            'mime_type' => $mimeType,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'status' => $type === MediaType::Video ? MediaStatus::Processing->value : MediaStatus::Ready->value,
        ]);

        if ($type === MediaType::Video) {
            ProcessVideoMedia::dispatch($media->id);
        } elseif ($type === MediaType::Image) {
            GenerateImageVariants::dispatch($media->id);
        }

        return $media;
    }

    public function activeDisk(): string
    {
        $provider = StorageProvider::query()->where('is_active', true)->first();

        return $provider?->configValue('disk', 'media_local') ?? 'media_local';
    }

    public function resolveCollection(string $path): MediaCollection
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $parentId = null;
        $currentPath = '';
        $collection = null;

        foreach ($segments as $segment) {
            $currentPath = $currentPath === '' ? $segment : "{$currentPath}/{$segment}";

            $collection = MediaCollection::query()->firstOrCreate(
                ['path' => $currentPath],
                ['parent_id' => $parentId, 'name' => Str::headline($segment), 'slug' => Str::slug($segment)]
            );

            $parentId = $collection->id;
        }

        /** @var MediaCollection $collection */
        return $collection;
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
