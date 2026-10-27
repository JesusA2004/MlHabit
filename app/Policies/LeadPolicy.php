<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Prospectos: permiso + regla de asignación configurable
 * (config('crm.lead_visibility')). Con `assigned`, quien no puede asignar
 * solo ve sus prospectos y los que aún no tienen responsable.
 */
class LeadPolicy
{
    public static function restrictedToAssigned(User $user): bool
    {
        return config('crm.lead_visibility') === 'assigned'
            && ! $user->hasPermission(Permission::LeadsAssign);
    }

    /**
     * Aplica la regla de visibilidad a una consulta de prospectos.
     *
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    public static function scopeVisible(Builder $query, User $user): Builder
    {
        if (self::restrictedToAssigned($user)) {
            $query->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhereNull('assigned_to'));
        }

        return $query;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::LeadsView);
    }

    public function view(User $user, Lead $lead): bool
    {
        if (! $user->hasPermission(Permission::LeadsView)) {
            return false;
        }

        return ! self::restrictedToAssigned($user)
            || $lead->assigned_to === null
            || $lead->assigned_to === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::LeadsManage);
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->hasPermission(Permission::LeadsManage) && $this->view($user, $lead);
    }

    public function assign(User $user, Lead $lead): bool
    {
        return $user->hasPermission(Permission::LeadsAssign);
    }

    public function scheduleConsultation(User $user, Lead $lead): bool
    {
        return $user->hasPermission(Permission::ConsultationsManage) && $this->view($user, $lead);
    }

    public function startPrequalification(User $user, Lead $lead): bool
    {
        return $this->update($user, $lead) || $user->hasPermission(Permission::PrequalificationReview);
    }
}
