<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Payment;
use App\Models\User;

/** Finanzas ve todo; un cliente solo sus propios pagos (IDOR). */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && $user->hasPermission(Permission::PaymentsView);
    }

    public function view(User $user, Payment $payment): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isStaff() && $user->hasPermission(Permission::PaymentsView)) {
            return true;
        }

        return $payment->belongsToUser($user);
    }

    public function refund(User $user, Payment $payment): bool
    {
        return $user->isStaff() && $user->hasPermission(Permission::PaymentsRefund);
    }

    public function record(User $user, Payment $payment): bool
    {
        return $user->isStaff() && $user->hasPermission(Permission::PaymentsRecord);
    }

    public function reconcile(User $user, Payment $payment): bool
    {
        return $this->viewAny($user);
    }
}
