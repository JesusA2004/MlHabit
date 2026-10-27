<?php

namespace App\Policies;

use App\Models\Finish;
use App\Models\User;

/**
 * Edición: `catalog.manage`. Publicar (hacer visible en el sitio) además requiere
 * `content.publish`.
 */
class FinishPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('catalog.manage');
    }

    public function view(User $user, Finish $finish): bool
    {
        return $finish->is_active || $user->hasPermission('catalog.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('catalog.manage');
    }

    public function update(User $user, Finish $finish): bool
    {
        return $user->hasPermission('catalog.manage');
    }

    public function delete(User $user, Finish $finish): bool
    {
        return $user->hasPermission('catalog.manage');
    }

    public function publish(User $user): bool
    {
        return $user->hasPermission('catalog.manage') && $user->hasPermission('content.publish');
    }
}
