<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;

class ContractPolicy
{
    public function view(User $user, Contract $contract): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isStaff()) {
            return $user->hasPermission('projects.view') || $user->hasPermission('payments.view');
        }

        return $contract->project->belongsToUser($user);
    }

    /** Pagar el anticipo desde el portal. */
    public function pay(User $user, Contract $contract): bool
    {
        return ! $user->isStaff() && $user->is_active && $contract->project->belongsToUser($user);
    }
}
