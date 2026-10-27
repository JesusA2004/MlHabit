<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\FabricationOrder;
use App\Models\User;

class FabricationOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::FabricationView);
    }

    public function view(User $user, FabricationOrder $order): bool
    {
        return $user->hasPermission(Permission::FabricationView) || $user->hasPermission(Permission::PayoutsView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::FabricationManage);
    }

    public function update(User $user, FabricationOrder $order): bool
    {
        return $user->hasPermission(Permission::FabricationManage);
    }
}
