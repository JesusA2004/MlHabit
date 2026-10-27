<?php

namespace App\Services\Crm;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Asignación automática round-robin de prospectos nuevos entre personal
 * comercial activo con `leads.manage`. Sin estado propio: elige a quien
 * lleva más tiempo sin recibir un prospecto (o nunca ha recibido uno).
 * Si no hay nadie elegible, el prospecto queda sin asignar.
 */
class LeadAssigner
{
    public function next(): ?User
    {
        $candidates = User::query()
            ->withPermission(Permission::LeadsManage)
            ->role(UserRole::Sales)
            ->pluck('id');

        if ($candidates->isEmpty()) {
            return null;
        }

        $lastAssigned = DB::table('leads')
            ->whereIn('assigned_to', $candidates)
            ->groupBy('assigned_to')
            ->selectRaw('assigned_to, MAX(id) as last_id')
            ->pluck('last_id', 'assigned_to');

        $id = $candidates
            ->sortBy(fn (int $id) => [(int) ($lastAssigned[$id] ?? 0), $id])
            ->first();

        return User::query()->whereKey($id)->first();
    }
}
