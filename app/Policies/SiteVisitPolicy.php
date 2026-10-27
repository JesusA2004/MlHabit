<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SiteVisit;
use App\Models\User;

/**
 * Personal con visits.* ve/gestiona; el técnico solo sus visitas asignadas;
 * el cliente solo las suyas (IDOR).
 */
class SiteVisitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && $user->hasPermission(Permission::VisitsView);
    }

    public function view(User $user, SiteVisit $visit): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isStaff() && $user->hasPermission(Permission::VisitsView)) {
            return true;
        }

        if ($visit->technician_id !== null && $visit->technician_id === $user->id) {
            return true;
        }

        return $visit->belongsToUser($user);
    }

    public function create(User $user): bool
    {
        return $user->isStaff() && $user->hasPermission(Permission::VisitsManage);
    }

    public function update(User $user, SiteVisit $visit): bool
    {
        return $user->isStaff() && $user->hasPermission(Permission::VisitsManage);
    }

    public function assign(User $user, SiteVisit $visit): bool
    {
        return $user->isStaff() && $user->hasPermission(Permission::VisitsAssign);
    }

    /** Iniciar/terminar: el técnico asignado (con measurements.execute) o personal con visits.manage. */
    public function execute(User $user, SiteVisit $visit): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isStaff() && $user->hasPermission(Permission::VisitsManage)) {
            return true;
        }

        return $visit->technician_id === $user->id && $user->hasPermission(Permission::MeasurementsExecute);
    }
}
