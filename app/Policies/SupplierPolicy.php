<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Supplier;
use App\Models\User;

/** Proveedores (incluye la descarga de sus documentos privados). */
class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::SuppliersView);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission(Permission::SuppliersView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::SuppliersManage);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission(Permission::SuppliersManage);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission(Permission::SuppliersManage);
    }
}
