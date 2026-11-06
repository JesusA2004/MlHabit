<?php

namespace App\Policies;

use App\Models\SitePage;
use App\Models\User;

/**
 * Páginas propias del sitio. Editar: `content.manage`. Publicar o
 * despublicar (hacerla visible en el sitio) además requiere `content.publish`.
 */
class SitePagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('content.manage');
    }

    public function view(User $user, SitePage $sitePage): bool
    {
        return $sitePage->is_published || $user->hasPermission('content.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('content.manage');
    }

    public function update(User $user, SitePage $sitePage): bool
    {
        return $user->hasPermission('content.manage');
    }

    public function delete(User $user, SitePage $sitePage): bool
    {
        return $user->hasPermission('content.manage');
    }

    public function publish(User $user): bool
    {
        return $user->hasPermission('content.manage') && $user->hasPermission('content.publish');
    }
}
