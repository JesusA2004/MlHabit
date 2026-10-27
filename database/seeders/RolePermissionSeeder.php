<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\RolePermission;
use App\Support\Permissions\RoleDefaults;
use Illuminate\Database\Seeder;

/**
 * Siembra la matriz de permisos recomendada (RoleDefaults) para cada rol.
 * Solo configura roles que aún no tienen su marca `_configured`: si un
 * administrador ya ajustó un rol desde /admin/roles, no se sobrescribe.
 * Para forzar los valores recomendados usa "Restablecer" en la pantalla.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $configured = RolePermission::query()
            ->where('permission', RolePermission::MARKER)
            ->pluck('role')
            ->all();

        foreach (UserRole::cases() as $role) {
            if (in_array($role->value, $configured, true)) {
                continue;
            }

            RolePermission::sync($role, array_map(fn (Permission $p) => $p->value, RoleDefaults::for($role)));
        }

        RolePermission::flush();
    }
}
