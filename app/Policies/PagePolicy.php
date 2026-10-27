<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

/**
 * Edición: `content.manage`. Publicar (hacer visible en el sitio) además requiere
 * `content.publish`.
 */
class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('content.manage');
    }

    public function view(User $user, Page $page): bool
    {
        return $page->is_published || $user->hasPermission('content.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('content.manage');
    }

    public function update(User $user, Page $page): bool
    {
        return $user->hasPermission('content.manage');
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->hasPermission('content.manage');
    }

    public function publish(User $user): bool
    {
        return $user->hasPermission('content.manage') && $user->hasPermission('content.publish');
    }
}
