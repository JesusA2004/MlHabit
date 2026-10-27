<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WarrantyClaim;

/** Evidencia de reclamos: quien ve la garantía ve el reclamo. */
class WarrantyClaimPolicy
{
    public function view(User $user, WarrantyClaim $claim): bool
    {
        return (new WarrantyPolicy)->view($user, $claim->warranty);
    }

    public function update(User $user, WarrantyClaim $claim): bool
    {
        return (new WarrantyPolicy)->update($user, $claim->warranty);
    }
}
