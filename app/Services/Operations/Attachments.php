<?php

namespace App\Services\Operations;

use App\Enums\MediaType;
use App\Enums\MediaVisibility;
use App\Models\Media;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

/**
 * Vincula archivos privados (subidos por fragmentos con visibility=private)
 * a entidades de operaciones. Reglas: el archivo debe ser privado y
 * haberlo subido quien lo vincula (nadie adjunta archivos ajenos), y la
 * evidencia de campo solo admite foto/video de hasta 60 MB.
 */
class Attachments
{
    public const EVIDENCE_MAX_BYTES = 60 * 1024 * 1024;

    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  Model  $entity  Modelo con el trait HasAttachments
     * @param  list<int>  $mediaIds
     * @return int Archivos vinculados
     */
    public function attach(Model $entity, array $mediaIds, string $role, User $actor, bool $evidenceOnly = false, string $field = 'media_ids'): int
    {
        $mediaIds = array_values(array_unique(array_map('intval', $mediaIds)));
        if ($mediaIds === []) {
            return 0;
        }

        $media = Media::query()->whereIn('id', $mediaIds)->get();

        foreach ($mediaIds as $id) {
            /** @var Media|null $file */
            $file = $media->firstWhere('id', $id);

            if ($file === null || $file->uploaded_by !== $actor->id) {
                throw ValidationException::withMessages([$field => 'Uno de los archivos no existe o no lo subiste tú.']);
            }

            if ($file->visibility !== MediaVisibility::Private) {
                throw ValidationException::withMessages([$field => 'Los documentos y evidencias deben subirse como archivos privados.']);
            }

            if ($evidenceOnly) {
                if (! in_array($file->type, [MediaType::Image, MediaType::Video], true)) {
                    throw ValidationException::withMessages([$field => 'La evidencia solo admite fotos o videos.']);
                }

                if ($file->size > self::EVIDENCE_MAX_BYTES) {
                    throw ValidationException::withMessages([$field => 'Cada evidencia debe pesar 60 MB o menos.']);
                }
            }
        }

        foreach ($mediaIds as $id) {
            /** @phpstan-ignore method.notFound */
            $entity->attachMedia($id, $role);
        }

        $this->logger->log('operaciones.archivos_vinculados', $entity, ['role' => $role, 'media_ids' => $mediaIds]);

        return count($mediaIds);
    }

    /**
     * @param  Model  $entity  Modelo con el trait HasAttachments
     */
    public function detach(Model $entity, int $mediaId, string $role): void
    {
        /** @phpstan-ignore method.notFound */
        $deleted = $entity->attachments()->where('media_id', $mediaId)->where('role', $role)->delete();

        if ($deleted) {
            $this->logger->log('operaciones.archivo_desvinculado', $entity, ['role' => $role, 'media_id' => $mediaId]);
        }
    }

    /**
     * Representación segura de un archivo para Inertia.
     *
     * @return array{id: int, uuid: string, name: string, type: string, mime: string, size: int, url: string|null, thumbnail: string|null, createdAt: string|null}
     */
    public static function present(Media $media): array
    {
        $routable = ! $media->isPrivate() || Route::has('archivos.show');

        return [
            'id' => $media->id,
            'uuid' => $media->uuid,
            'name' => $media->original_filename,
            'type' => $media->type->value,
            'mime' => $media->mime_type,
            'size' => $media->size,
            'url' => $routable ? $media->url() : null,
            'thumbnail' => $routable && $media->relationLoaded('variants') ? $media->variantUrl('thumbnail') : null,
            'createdAt' => $media->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  iterable<Media>  $media
     * @return list<array<string, mixed>>
     */
    public static function presentMany(iterable $media): array
    {
        $out = [];
        foreach ($media as $file) {
            $out[] = self::present($file);
        }

        return $out;
    }
}
