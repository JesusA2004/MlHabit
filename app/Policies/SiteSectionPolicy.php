<?php

namespace App\Policies;

use App\Models\SiteSection;
use App\Models\User;

/**
 * Edición: `content.manage`. Publicar (hacer visible en el sitio) además requiere
 * `content.publish`.
 */
class SiteSectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('content.manage');
    }

    public function view(User $user, SiteSection $siteSection): bool
    {
        return $user->hasPermission('content.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('content.manage');
    }

    public function update(User $user, SiteSection $siteSection): bool
    {
        return $user->hasPermission('content.manage');
    }

    public function delete(User $user, SiteSection $siteSection): bool
    {
        return $user->hasPermission('content.manage');
    }

    public function publish(User $user): bool
    {
        return $user->hasPermission('content.manage') && $user->hasPermission('content.publish');
    }
}
