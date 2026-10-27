<?php

namespace App\Policies;

use App\Models\DesignVersion;
use App\Models\User;

class DesignVersionPolicy
{
    public function view(User $user, DesignVersion $design): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isStaff()) {
            return $user->hasPermission('designs.view') || $user->hasPermission('projects.view');
        }

        return $design->status->isVisibleToClient() && $design->project->belongsToUser($user);
    }

    public function update(User $user, DesignVersion $design): bool
    {
        return $user->isStaff() && $user->hasPermission('designs.manage');
    }

    /** Decisión del cliente en el portal. */
    public function decide(User $user, DesignVersion $design): bool
    {
        return ! $user->isStaff() && $user->is_active && $design->project->belongsToUser($user);
    }
}
