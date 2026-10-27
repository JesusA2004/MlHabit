<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Support\Permissions\RoleDefaults;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $role
 * @property string $permission
 */
class RolePermission extends Model
{
    public const CACHE_KEY = 'role_permissions.map';

    /** Marca que el rol ya fue configurado (permite guardar un rol sin permisos). */
    public const MARKER = '_configured';

    protected $fillable = ['role', 'permission'];

    /**
     * Mapa rol → lista de permisos, cacheado. Si la tabla está vacía para un
     * rol (instalación nueva sin sembrar), se usan los valores por defecto.
     *
     * @return array<string, list<string>>
     */
    public static function map(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $stored = static::query()->get(['role', 'permission'])
                ->groupBy('role')
                ->map(fn ($rows) => array_values(array_filter($rows->pluck('permission')->map(fn ($p) => (string) $p)->all(), fn (string $p) => $p !== self::MARKER)))
                ->all();

            $map = [];
            foreach (UserRole::cases() as $role) {
                $map[$role->value] = $stored[$role->value]
                    ?? array_map(fn (Permission $p) => $p->value, RoleDefaults::for($role));
            }

            return $map;
        });
    }

    /**
     * @param  list<string>  $permissions
     */
    public static function sync(UserRole $role, array $permissions): void
    {
        $valid = array_values(array_intersect($permissions, array_map(fn (Permission $p) => $p->value, Permission::cases())));

        $keep = [...$valid, self::MARKER];
        static::query()->where('role', $role->value)->whereNotIn('permission', $keep)->delete();
        foreach ($keep as $permission) {
            static::query()->firstOrCreate(['role' => $role->value, 'permission' => $permission]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
