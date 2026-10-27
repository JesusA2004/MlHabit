<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserStoreRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\User;
use App\Notifications\Platform\UserInvitation;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /admin/usuarios: invitar al equipo interno, cambiar rol y estado,
 * reenviar invitaciones y restablecer la verificación en dos pasos.
 * Nunca se borran cuentas (tienen historial): se desactivan.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'rol' => (string) $request->query('rol', ''),
            'estado' => (string) $request->query('estado', ''),
            'tipo' => (string) $request->query('tipo', 'equipo'),
        ];

        $users = User::query()
            ->when($filters['q'] !== '', function (Builder $query) use ($filters) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['q']).'%';
                $query->where(fn (Builder $q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when(UserRole::tryFrom($filters['rol']), fn (Builder $q, UserRole $role) => $q->where('role', $role->value))
            ->when($filters['estado'] === 'activos', fn (Builder $q) => $q->where('is_active', true))
            ->when($filters['estado'] === 'inactivos', fn (Builder $q) => $q->where('is_active', false))
            ->when($filters['estado'] === 'pendientes', fn (Builder $q) => $q->where('is_active', true)->whereNull('last_login_at'))
            ->when($filters['tipo'] === 'equipo', fn (Builder $q) => $q->whereNotIn('role', [UserRole::Customer->value, UserRole::Technician->value]))
            ->when($filters['tipo'] === 'clientes', fn (Builder $q) => $q->where('role', UserRole::Customer->value))
            ->when($filters['tipo'] === 'tecnicos', fn (Builder $q) => $q->where('role', UserRole::Technician->value))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('admin/usuarios/Index', [
            'users' => $users->through(fn (User $user) => $this->present($user, $actor)),
            'filters' => $filters,
            'roles' => array_map(fn (UserRole $r) => [
                'value' => $r->value,
                'label' => $r->label(),
                'description' => $r->description(),
                'isStaff' => $r->isStaff(),
            ], UserRole::cases()),
            'assignableRoles' => UserStoreRequest::assignableRoles($actor),
            'counts' => [
                'equipo' => User::query()->whereNotIn('role', [UserRole::Customer->value, UserRole::Technician->value])->count(),
                'clientes' => User::query()->where('role', UserRole::Customer->value)->count(),
                'tecnicos' => User::query()->where('role', UserRole::Technician->value)->count(),
            ],
        ]);
    }

    public function store(UserStoreRequest $request, ActivityLogger $activity): RedirectResponse
    {
        $data = $request->validated();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'role' => UserRole::from($data['role']),
            'is_active' => true,
            // Contraseña aleatoria que nadie conoce: la persona crea la suya
            // desde el enlace de la invitación.
            'password' => Str::password(40),
        ]);

        $user->notify(new UserInvitation($request->user()?->name));

        $activity->log('usuario.invitado', $user, [
            'after' => ['name' => $user->name, 'email' => $user->email, 'role' => $user->role->value],
        ]);

        return back()->with('success', "Invitación enviada a {$user->email}.");
    }

    public function update(UserUpdateRequest $request, User $user, ActivityLogger $activity): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_if($user->isSuperAdmin() && ! $actor->isSuperAdmin(), 403, 'Solo un superadministrador puede editar a otro superadministrador.');

        $data = $request->validated();

        DB::transaction(function () use ($user, $data, $activity) {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $fill = [];
            foreach (['name', 'phone', 'is_active'] as $field) {
                if (array_key_exists($field, $data)) {
                    $fill[$field] = $data[$field];
                }
            }
            if (array_key_exists('role', $data)) {
                $fill['role'] = UserRole::from($data['role']);
            }
            $locked->fill($fill);

            $losesSuperAdmin = $locked->getOriginal('role') === UserRole::SuperAdmin
                && $locked->getOriginal('is_active')
                && ($locked->role !== UserRole::SuperAdmin || ! $locked->is_active);

            if ($losesSuperAdmin) {
                $remaining = User::query()
                    ->where('role', UserRole::SuperAdmin->value)
                    ->where('is_active', true)
                    ->whereKeyNot($locked->getKey())
                    ->lockForUpdate()
                    ->count();

                if ($remaining === 0) {
                    throw ValidationException::withMessages([
                        $locked->isDirty('role') ? 'role' : 'is_active' => 'Debe quedar al menos un superadministrador activo.',
                    ]);
                }
            }

            $activity->logModelChanges('usuario.actualizado', $locked, ['name', 'phone', 'role', 'is_active']);
            $locked->save();

            // Una cuenta desactivada sale de inmediato de todas sus sesiones.
            if ($locked->wasChanged('is_active') && ! $locked->is_active) {
                DB::table('sessions')->where('user_id', $locked->getKey())->delete();
                $locked->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
            }
        });

        return back()->with('success', 'Cambios guardados.');
    }

    public function resendInvitation(Request $request, User $user, ActivityLogger $activity): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_if($user->isSuperAdmin() && ! $actor->isSuperAdmin(), 403);

        if (! $user->is_active) {
            return back()->with('error', 'Activa la cuenta antes de reenviar la invitación.');
        }

        if ($user->last_login_at !== null) {
            return back()->with('error', 'Esta persona ya inició sesión; puede usar "¿Olvidaste tu contraseña?".');
        }

        $user->notify(new UserInvitation($actor->name));
        $activity->log('usuario.invitacion_reenviada', $user);

        return back()->with('success', "Invitación reenviada a {$user->email}.");
    }

    public function resetTwoFactor(Request $request, User $user, ActivityLogger $activity): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_if($user->isSuperAdmin() && ! $actor->isSuperAdmin(), 403);

        if ($user->is($actor)) {
            return back()->with('error', 'Administra tu propia verificación desde Cuenta → Seguridad.');
        }

        if ($user->two_factor_secret === null && $user->two_factor_confirmed_at === null) {
            return back()->with('error', 'Esta cuenta no tiene verificación en dos pasos activa.');
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        // Cierra sus sesiones para que vuelva a configurar desde cero.
        DB::table('sessions')->where('user_id', $user->getKey())->delete();

        $activity->log('usuario.dos_pasos_restablecido', $user);

        return back()->with('success', "Verificación en dos pasos restablecida para {$user->name}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $user, User $actor): array
    {
        $protected = $user->isSuperAdmin() && ! $actor->isSuperAdmin();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role->value,
            'roleLabel' => $user->role->label(),
            'isStaff' => $user->isStaff(),
            'isActive' => $user->is_active,
            'twoFactorEnabled' => $user->two_factor_confirmed_at !== null,
            'invitationPending' => $user->is_active && $user->last_login_at === null,
            'lastLoginAt' => $user->last_login_at?->toIso8601String(),
            'createdAt' => $user->created_at?->toIso8601String(),
            'isSelf' => $user->is($actor),
            'canEdit' => ! $protected,
        ];
    }
}
