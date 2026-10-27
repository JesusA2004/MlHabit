<?php

namespace App\Policies;

use App\Models\InstallationOrderItem;
use App\Models\User;

/** Evidencia por partida: quien puede ver la orden puede ver su evidencia. */
class InstallationOrderItemPolicy
{
    public function view(User $user, InstallationOrderItem $item): bool
    {
        return (new InstallationOrderPolicy)->view($user, $item->order);
    }
}
