<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property UserRole $role
 * @property bool $is_active
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'phone', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /** @return HasOne<Customer, $this> */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    /** @return HasOne<TechnicianProfile, $this> */
    public function technicianProfile(): HasOne
    {
        return $this->hasOne(TechnicianProfile::class);
    }

    /** @return BelongsToMany<CoverageZone, $this> */
    public function coverageZones(): BelongsToMany
    {
        return $this->belongsToMany(CoverageZone::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    public function hasPermission(Permission|string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        $value = $permission instanceof Permission ? $permission->value : $permission;

        return in_array($value, $this->permissions(), true);
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        if ($this->isSuperAdmin()) {
            return array_map(fn (Permission $p) => $p->value, Permission::cases());
        }

        return RolePermission::map()[$this->role->value] ?? [];
    }

    /**
     * Personal interno que tiene un permiso dado (para selectores de
     * asignación: asesores, diseñadores, técnicos…).
     *
     * @param  Builder<User>  $query
     */
    public function scopeWithPermission(Builder $query, Permission $permission): void
    {
        $roles = collect(RolePermission::map())
            ->filter(fn (array $perms) => in_array($permission->value, $perms, true))
            ->keys()
            ->push(UserRole::SuperAdmin->value)
            ->unique()
            ->all();

        $query->where('is_active', true)->whereIn('role', $roles);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeRole(Builder $query, UserRole ...$roles): void
    {
        $query->whereIn('role', array_map(fn (UserRole $r) => $r->value, $roles));
    }
}
