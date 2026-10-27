<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\RateCard;
use App\Models\User;

class RateCardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::RateCardsView);
    }

    public function view(User $user, RateCard $card): bool
    {
        return $user->hasPermission(Permission::RateCardsView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::RateCardsManage);
    }

    /** La inmutabilidad de versiones vigentes/retiradas la valida RateCardService. */
    public function update(User $user, RateCard $card): bool
    {
        return $user->hasPermission(Permission::RateCardsManage);
    }
}
