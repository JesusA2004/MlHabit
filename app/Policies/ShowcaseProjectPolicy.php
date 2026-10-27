<?php

namespace App\Policies;

use App\Enums\PublicationStatus;
use App\Models\ShowcaseProject;
use App\Models\User;

/**
 * Edición: `catalog.manage`. Publicar (hacer visible en el sitio) además requiere
 * `content.publish`.
 */
class ShowcaseProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('catalog.manage');
    }

    public function view(User $user, ShowcaseProject $showcaseProject): bool
    {
        return $showcaseProject->status === PublicationStatus::Published || $user->hasPermission('catalog.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('catalog.manage');
    }

    public function update(User $user, ShowcaseProject $showcaseProject): bool
    {
        return $user->hasPermission('catalog.manage');
    }

    public function delete(User $user, ShowcaseProject $showcaseProject): bool
    {
        return $user->hasPermission('catalog.manage');
    }

    public function publish(User $user): bool
    {
        return $user->hasPermission('catalog.manage') && $user->hasPermission('content.publish');
    }
}
