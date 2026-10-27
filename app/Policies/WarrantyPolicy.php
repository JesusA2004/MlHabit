<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Warranty;

/** Garantías: personal con permiso o el cliente dueño del proyecto. */
class WarrantyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::WarrantiesView);
    }

    public function view(User $user, Warranty $warranty): bool
    {
        if ($user->isStaff()) {
            return $user->hasPermission(Permission::WarrantiesView);
        }

        return $user->is_active && $user->role === UserRole::Customer && $warranty->project->belongsToUser($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::WarrantiesManage);
    }

    public function update(User $user, Warranty $warranty): bool
    {
        return $user->hasPermission(Permission::WarrantiesManage);
    }
}
