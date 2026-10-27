<?php

namespace App\Services\Web;

use App\Enums\MediaVariantType;
use App\Enums\VisualOrigin;
use App\Models\Media;
use App\Support\ResponsiveImage;

/**
 * Representación de archivos para el panel (biblioteca y selectores).
 * Requiere `variants` y `collection` precargados cuando aplique.
 */
class MediaPresenter
{
    /**
     * Referencia compacta para campos de formulario (portadas, galerías).
     *
     * @return array<string, mixed>|null
     */
    public static function ref(?Media $media): ?array
    {
        if ($media === null) {
            return null;
        }

        $origin = $media->origin !== null ? VisualOrigin::tryFrom($media->origin) : null;

        return [
            'id' => $media->id,
            'url' => $media->url(),
            'thumbnailUrl' => ResponsiveImage::thumbnailUrl($media),
            'filename' => $media->original_filename,
            'type' => $media->type->value,
            'altText' => $media->alt_text,
            'origin' => $origin?->value,
            'originLabel' => $origin?->label(),
            'width' => $media->width,
            'height' => $media->height,
        ];
    }

    /**
     * Datos completos para la biblioteca multimedia.
     *
     * @return array<string, mixed>
     */
    public static function full(Media $media): array
    {
        $media->loadMissing(['variants', 'collection']);
        $origin = $media->origin !== null ? VisualOrigin::tryFrom($media->origin) : null;

        return [
            ...(array) self::ref($media),
            'uuid' => $media->uuid,
            'visibility' => $media->visibility->value,
            'status' => $media->status->value,
            'mimeType' => $media->mime_type,
            'size' => $media->size,
            'durationSeconds' => $media->duration_seconds,
            'posterUrl' => $media->variantUrl(MediaVariantType::Poster->value),
            'title' => $media->title,
            'description' => $media->description,
            'originLabel' => $origin?->label(),
            'collection' => $media->collection !== null ? ['id' => $media->collection->id, 'name' => $media->collection->name, 'path' => $media->collection->path] : null,
            'variants' => $media->variants->map(fn ($v) => ['type' => $v->type, 'width' => $v->width, 'height' => $v->height, 'size' => $v->size])->values()->all(),
            'createdAt' => $media->created_at?->toIso8601String(),
            'updatedAt' => $media->updated_at?->toIso8601String(),
        ];
    }
}
