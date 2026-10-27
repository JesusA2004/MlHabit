<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Edición de nombre, teléfono, rol y estado. Reglas de negocio:
 * - nadie cambia su propio rol ni se desactiva a sí mismo;
 * - solo un superadministrador otorga o retira el rol super_admin;
 * - el rol de clientes y técnicos no se cambia aquí (sus módulos los crean);
 * - el último superadministrador activo no puede perder el rol ni desactivarse
 *   (se verifica también con bloqueo en el controlador).
 */
class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $actor */
        $actor = $this->user();
        $target = $this->route('user');

        if ($actor === null || ! $actor->can('users.manage')) {
            return false;
        }

        // Solo un superadministrador edita a otro superadministrador.
        return ! ($target instanceof User && $target->isSuperAdmin() && ! $actor->isSuperAdmin());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^[0-9+()\s-]{7,20}$/'],
            'role' => ['sometimes', 'required', 'string', Rule::enum(UserRole::class)],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nombre', 'phone' => 'teléfono', 'role' => 'rol', 'is_active' => 'estado'];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var User $actor */
            $actor = $this->user();
            /** @var User $target */
            $target = $this->route('user');

            $newRole = $this->has('role') ? UserRole::tryFrom((string) $this->input('role')) : null;
            $roleChanges = $newRole !== null && $newRole !== $target->role;
            $deactivates = $this->has('is_active') && ! $this->boolean('is_active') && $target->is_active;

            if ($target->is($actor) && $roleChanges) {
                $validator->errors()->add('role', 'No puedes cambiar tu propio rol.');
            }

            if ($target->is($actor) && $deactivates) {
                $validator->errors()->add('is_active', 'No puedes desactivar tu propia cuenta.');
            }

            if ($roleChanges && ($newRole === UserRole::SuperAdmin || $target->isSuperAdmin()) && ! $actor->isSuperAdmin()) {
                $validator->errors()->add('role', 'Solo un superadministrador puede otorgar o retirar ese rol.');
            }

            if ($roleChanges && (! $target->isStaff() || ! $newRole->isStaff())) {
                $validator->errors()->add('role', 'El rol de clientes y técnicos se administra desde su propio módulo.');
            }

            if ($deactivates && $target->isSuperAdmin() && ! $actor->isSuperAdmin()) {
                $validator->errors()->add('is_active', 'Solo un superadministrador puede desactivar a otro.');
            }
        }];
    }
}
