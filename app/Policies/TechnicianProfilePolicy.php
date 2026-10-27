<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\TechnicianProfile;
use App\Models\User;

/** Fichas de técnicos (y sus documentos privados: el propio técnico también los ve). */
class TechnicianProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::SuppliersView);
    }

    public function view(User $user, TechnicianProfile $profile): bool
    {
        return $user->hasPermission(Permission::SuppliersView)
            || ($user->is_active && $profile->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::SuppliersManage);
    }

    public function update(User $user, TechnicianProfile $profile): bool
    {
        return $user->hasPermission(Permission::SuppliersManage);
    }
}
