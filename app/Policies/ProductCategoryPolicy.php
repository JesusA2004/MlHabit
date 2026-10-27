<?php

namespace App\Policies;

use App\Models\ProductCategory;
use App\Models\User;

/**
 * Edición: `catalog.manage`. Publicar (hacer visible en el sitio) además requiere
 * `content.publish`.
 */
class ProductCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('catalog.manage');
    }

    public function view(User $user, ProductCategory $productCategory): bool
    {
        return $productCategory->is_published || $user->hasPermission('catalog.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('catalog.manage');
    }

    public function update(User $user, ProductCategory $productCategory): bool
    {
        return $user->hasPermission('catalog.manage');
    }

    public function delete(User $user, ProductCategory $productCategory): bool
    {
        return $user->hasPermission('catalog.manage');
    }

    public function publish(User $user): bool
    {
        return $user->hasPermission('catalog.manage') && $user->hasPermission('content.publish');
    }
}
