<?php

namespace App\Policies;

use App\Models\Measurement;
use App\Models\SiteVisit;
use App\Models\User;

/**
 * Mediciones: el técnico solo las de SUS visitas; el equipo con
 * designs.view o projects.view. El cliente no ve la medición cruda.
 */
class MeasurementPolicy
{
    public function view(User $user, Measurement $measurement): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isStaff()) {
            return $user->hasPermission('designs.view') || $user->hasPermission('projects.view');
        }

        return $this->ownsAsTechnician($user, $measurement);
    }

    public function update(User $user, Measurement $measurement): bool
    {
        if (! $user->hasPermission('measurements.execute')) {
            return false;
        }

        return $user->isStaff() || $this->ownsAsTechnician($user, $measurement);
    }

    private function ownsAsTechnician(User $user, Measurement $measurement): bool
    {
        if ($measurement->technician_id === $user->id) {
            return true;
        }

        return $measurement->site_visit_id !== null
            && SiteVisit::query()->whereKey($measurement->site_visit_id)->where('technician_id', $user->id)->exists();
    }
}
