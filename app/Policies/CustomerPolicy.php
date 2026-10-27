<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::CustomersView);
    }

    public function view(User $user, Customer $customer): bool
    {
        if ($user->hasPermission(Permission::CustomersView)) {
            return true;
        }

        // El propio cliente (portal) puede ver su ficha.
        return $customer->user_id !== null && $customer->user_id === $user->id;
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->hasPermission(Permission::CustomersManage);
    }

    public function invite(User $user, Customer $customer): bool
    {
        return $user->hasPermission(Permission::CustomersManage);
    }
}
