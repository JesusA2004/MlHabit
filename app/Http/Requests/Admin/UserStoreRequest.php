<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Invitación de una cuenta del equipo interno. Clientes y técnicos se
 * crean desde sus propios módulos (CRM/portal y operaciones).
 */
class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+()\s-]{7,20}$/'],
            'role' => ['required', 'string', Rule::in(self::assignableRoles($this->user()))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Ya existe una cuenta con este correo.',
            'role.in' => 'Elige un rol del equipo interno válido.',
            'phone.regex' => 'Escribe un teléfono válido (solo números, espacios y +).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nombre', 'email' => 'correo', 'phone' => 'teléfono', 'role' => 'rol'];
    }

    /**
     * Roles del equipo que puede asignar quien invita. Solo un
     * superadministrador crea a otro superadministrador.
     *
     * @return list<string>
     */
    public static function assignableRoles(?User $actor): array
    {
        return array_values(array_map(
            fn (UserRole $r) => $r->value,
            array_filter(
                UserRole::cases(),
                fn (UserRole $r) => $r->isStaff() && ($r !== UserRole::SuperAdmin || $actor?->isSuperAdmin()),
            ),
        ));
    }
}
