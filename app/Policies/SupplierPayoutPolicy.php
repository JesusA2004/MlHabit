<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SupplierPayout;
use App\Models\User;
use App\Services\Operations\PayoutService;

/** Pagos a proveedores con separación de funciones (ver PayoutService). */
class SupplierPayoutPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::PayoutsView);
    }

    public function view(User $user, SupplierPayout $payout): bool
    {
        return $user->hasPermission(Permission::PayoutsView);
    }

    /** Autorizar o rechazar: payouts.authorize y no ser solicitante ni instalador líder. */
    public function approve(User $user, SupplierPayout $payout): bool
    {
        return PayoutService::canAuthorize($user, $payout);
    }

    public function pay(User $user, SupplierPayout $payout): bool
    {
        return $user->hasPermission(Permission::PayoutsPay)
            && $payout->installationOrder?->lead_installer_id !== $user->id;
    }
}
