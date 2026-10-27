<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;
use App\Services\Web\PrivateMediaAccess;

/**
 * Biblioteca multimedia: administrarla requiere `media.manage`. Ver un
 * archivo privado se decide en PrivateMediaAccess (permiso sensible,
 * Policy de la entidad vinculada o quien lo subió mientras es huérfano).
 */
class MediaPolicy
{
    public function __construct(private readonly PrivateMediaAccess $access) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('media.manage');
    }

    public function view(User $user, Media $media): bool
    {
        return $this->access->allows($user, $media);
    }

    /** Ver archivos privados en la biblioteca (listado). */
    public function viewPrivate(User $user): bool
    {
        return $user->hasPermission('media.manage') && $user->hasPermission('sensitive.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('media.manage');
    }

    public function update(User $user, Media $media): bool
    {
        if ($media->isPrivate()) {
            return $this->viewPrivate($user);
        }

        return $user->hasPermission('media.manage');
    }

    public function delete(User $user, Media $media): bool
    {
        return $this->update($user, $media);
    }
}
