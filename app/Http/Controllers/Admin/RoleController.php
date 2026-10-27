<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RolePermissionsRequest;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\Permissions\RoleDefaults;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /admin/roles: matriz de permisos por rol (role_permissions).
 * - super_admin: bloqueado, siempre todos los permisos (Gate implícito).
 * - cliente: bloqueado, sin permisos internos (su acceso es el portal).
 * Cada cambio se audita con antes/después y limpia la caché del mapa, así
 * que aplica en la siguiente petición de cualquier usuario.
 */
class RoleController extends Controller
{
    public function index(): Response
    {
        $map = RolePermission::map();
        $userCounts = User::query()->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

        $groups = [];
        foreach (Permission::cases() as $permission) {
            $groups[$permission->group()][] = ['value' => $permission->value, 'label' => $permission->label()];
        }

        return Inertia::render('admin/roles/Index', [
            'groups' => array_map(
                fn (string $label, array $permissions) => ['label' => $label, 'permissions' => $permissions],
                array_keys($groups),
                array_values($groups),
            ),
            'roles' => array_map(fn (UserRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
                'description' => $role->description(),
                'locked' => self::lockFor($role),
                'permissions' => $role === UserRole::SuperAdmin
                    ? array_map(fn (Permission $p) => $p->value, Permission::cases())
                    : $map[$role->value] ?? [],
                'defaults' => array_map(fn (Permission $p) => $p->value, RoleDefaults::for($role)),
                'userCount' => (int) ($userCounts[$role->value] ?? 0),
            ], UserRole::cases()),
        ]);
    }

    public function update(RolePermissionsRequest $request, UserRole $role, ActivityLogger $activity): RedirectResponse
    {
        abort_if(self::lockFor($role) !== null, 422, 'Este rol no es editable.');

        $this->apply($role, $request->permissions(), 'permisos.actualizados', $activity);

        return back()->with('success', "Permisos de {$role->label()} actualizados.");
    }

    public function reset(UserRole $role, ActivityLogger $activity): RedirectResponse
    {
        abort_if(self::lockFor($role) !== null, 422, 'Este rol no es editable.');

        $defaults = array_map(fn (Permission $p) => $p->value, RoleDefaults::for($role));
        $this->apply($role, $defaults, 'permisos.restablecidos', $activity);

        return back()->with('success', "{$role->label()}: valores recomendados restablecidos.");
    }

    /**
     * @param  list<string>  $permissions
     */
    private function apply(UserRole $role, array $permissions, string $action, ActivityLogger $activity): void
    {
        $before = RolePermission::map()[$role->value] ?? [];
        sort($before);

        RolePermission::sync($role, $permissions);
        RolePermission::flush();

        $after = RolePermission::map()[$role->value] ?? [];
        sort($after);

        $activity->log($action, null, [
            'role' => $role->value,
            'before' => ['permissions' => $before],
            'after' => ['permissions' => $after],
            'added' => array_values(array_diff($after, $before)),
            'removed' => array_values(array_diff($before, $after)),
        ]);
    }

    /** 'all' (super_admin), 'none' (cliente) o null si es editable. */
    public static function lockFor(UserRole $role): ?string
    {
        return match ($role) {
            UserRole::SuperAdmin => 'all',
            UserRole::Customer => 'none',
            default => null,
        };
    }
}
