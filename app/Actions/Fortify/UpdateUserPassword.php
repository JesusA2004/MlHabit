<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

/**
 * Cambio de contraseña desde Cuenta → Seguridad (PUT /user/password).
 */
class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * @param  array<string, string>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => $this->currentPasswordRules(),
            'password' => $this->passwordRules(),
        ], [
            'current_password.current_password' => 'La contraseña actual no es correcta.',
        ])->validate();

        $user->forceFill([
            'password' => $input['password'],
        ])->save();

        $this->activity->log('usuario.contrasena_actualizada', $user);
    }
}
