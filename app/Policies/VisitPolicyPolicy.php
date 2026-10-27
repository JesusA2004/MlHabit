<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\VisitPolicy;

/**
 * Política comercial de visitas: configuración (settings.manage) o
 * Dirección con visits.manage. Afecta dinero: mínimo privilegio.
 */
class VisitPolicyPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manage($user) || ($user->isStaff() && $user->hasPermission(Permission::VisitsView));
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function activate(User $user, VisitPolicy $policy): bool
    {
        return $this->manage($user);
    }

    private function manage(User $user): bool
    {
        if (! $user->is_active || ! $user->isStaff()) {
            return false;
        }

        return $user->hasPermission(Permission::SettingsManage)
            || ($user->hasPermission(Permission::VisitsManage) && in_array($user->role, [UserRole::Direction, UserRole::SuperAdmin], true));
    }
}
