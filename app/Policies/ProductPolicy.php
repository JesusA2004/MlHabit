<?php

namespace App\Policies;

use App\Enums\PublicationStatus;
use App\Models\Product;
use App\Models\User;

/**
 * Edición: `catalog.manage`. Publicar (hacer visible en el sitio) además requiere
 * `content.publish`.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('catalog.manage');
    }

    public function view(User $user, Product $product): bool
    {
        return $product->status === PublicationStatus::Published || $user->hasPermission('catalog.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('catalog.manage');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasPermission('catalog.manage');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasPermission('catalog.manage');
    }

    public function publish(User $user): bool
    {
        return $user->hasPermission('catalog.manage') && $user->hasPermission('content.publish');
    }
}
