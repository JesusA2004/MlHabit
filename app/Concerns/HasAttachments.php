<?php

namespace App\Concerns;

use App\Models\Media;
use App\Models\MediaAttachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Archivos vinculados a una entidad (galerías, evidencias, documentos).
 * Los archivos privados solo se descargan vía PrivateMediaController,
 * que delega la autorización en la Policy de la entidad (`view`).
 */
trait HasAttachments
{
    /** @return MorphMany<MediaAttachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(MediaAttachment::class, 'attachable')->orderBy('sort');
    }

    /** @return MorphToMany<Media, $this> */
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'attachable', 'media_attachments')
            ->withPivot(['role', 'caption', 'sort'])
            ->orderByPivot('sort');
    }

    /** @return MorphToMany<Media, $this> */
    public function mediaWithRole(string $role): MorphToMany
    {
        return $this->media()->wherePivot('role', $role);
    }

    /**
     * Reemplaza los archivos de un rol conservando el orden recibido.
     *
     * @param  list<int>  $mediaIds
     */
    public function syncAttachments(string $role, array $mediaIds, ?int $userId = null): void
    {
        $this->attachments()->where('role', $role)->whereNotIn('media_id', $mediaIds)->delete();

        foreach ($mediaIds as $index => $mediaId) {
            $this->attachments()->updateOrCreate(
                ['media_id' => $mediaId, 'role' => $role],
                ['sort' => $index, 'attached_by' => $userId ?? auth()->id()],
            );
        }
    }

    public function attachMedia(int $mediaId, string $role, ?string $caption = null): MediaAttachment
    {
        $sort = (int) $this->attachments()->where('role', $role)->max('sort') + 1;

        return $this->attachments()->firstOrCreate(
            ['media_id' => $mediaId, 'role' => $role],
            ['caption' => $caption, 'sort' => $sort, 'attached_by' => auth()->id()],
        );
    }
}
