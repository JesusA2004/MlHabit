<?php

namespace App\Policies;

use App\Enums\InstallationStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\InstallationOrder;
use App\Models\User;

/**
 * Órdenes de instalación. Además del personal con permiso, la ven:
 * el instalador líder (solo SUS órdenes, IDOR) y el cliente dueño del
 * proyecto (desde que se programa). Esta Policy también autoriza la
 * descarga de evidencia privada (archivos.show).
 */
class InstallationOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::InstallationsView);
    }

    public function view(User $user, InstallationOrder $order): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isStaff() && (
            $user->hasPermission(Permission::InstallationsView)
            || $user->hasPermission(Permission::QualityView)
            || $user->hasPermission(Permission::PayoutsView)
        )) {
            return true;
        }

        if ($this->execute($user, $order)) {
            return true;
        }

        return $this->viewAsClient($user, $order);
    }

    /** Cliente dueño del proyecto, para órdenes ya programadas. */
    public function viewAsClient(User $user, InstallationOrder $order): bool
    {
        return $user->is_active
            && $user->role === UserRole::Customer
            && ! in_array($order->status, [InstallationStatus::Draft, InstallationStatus::Cancelled], true)
            && $order->project->belongsToUser($user);
    }

    /** Técnico con installations.execute y líder de ESTA orden. */
    public function execute(User $user, InstallationOrder $order): bool
    {
        return $user->role === UserRole::Technician
            && $user->hasPermission(Permission::InstallationsExecute)
            && $order->isLedBy($user);
    }

    /** Conformidad del cliente: solo el dueño y solo mientras espera su revisión. */
    public function conform(User $user, InstallationOrder $order): bool
    {
        return $this->viewAsClient($user, $order) && $order->status === InstallationStatus::ClientReview;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::InstallationsManage);
    }

    public function update(User $user, InstallationOrder $order): bool
    {
        return $user->hasPermission(Permission::InstallationsManage);
    }

    public function review(User $user, InstallationOrder $order): bool
    {
        return $user->hasPermission(Permission::QualityReview);
    }
}
