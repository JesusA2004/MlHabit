<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Consultation;
use App\Models\User;

class ConsultationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ConsultationsView) || $user->hasPermission(Permission::ConsultationsManage);
    }

    public function view(User $user, Consultation $consultation): bool
    {
        return $user->hasPermission(Permission::ConsultationsView)
            || ($user->hasPermission(Permission::ConsultationsManage) && $consultation->advisor_id === $user->id);
    }

    public function update(User $user, Consultation $consultation): bool
    {
        return $user->hasPermission(Permission::ConsultationsManage) && $this->view($user, $consultation);
    }
}
