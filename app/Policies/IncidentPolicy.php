<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\User;

/**
 * Incidencias. El cliente dueño ve las visibles para él; el instalador
 * líder ve las de su orden. También autoriza la evidencia privada.
 */
class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::IncidentsView);
    }

    public function view(User $user, Incident $incident): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isStaff() && $user->hasPermission(Permission::IncidentsView)) {
            return true;
        }

        if ($user->role === UserRole::Customer) {
            return $incident->visible_to_client && $incident->project->belongsToUser($user);
        }

        if ($user->role === UserRole::Technician) {
            return $incident->installationOrder?->isLedBy($user) ?? false;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::IncidentsManage);
    }

    public function update(User $user, Incident $incident): bool
    {
        return $user->hasPermission(Permission::IncidentsManage);
    }
}
