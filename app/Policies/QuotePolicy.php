<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && $user->hasPermission('quotes.view');
    }

    public function view(User $user, Quote $quote): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isStaff()) {
            return $user->hasPermission('quotes.view') || $user->hasPermission('projects.view');
        }

        return $quote->status->isVisibleToClient() && $quote->project->belongsToUser($user);
    }

    public function update(User $user, Quote $quote): bool
    {
        return $user->isStaff() && $user->hasPermission('quotes.manage');
    }

    /** Aceptar/rechazar desde el portal (solo el cliente dueño). */
    public function respond(User $user, Quote $quote): bool
    {
        return ! $user->isStaff() && $user->is_active && $quote->project->belongsToUser($user);
    }
}
