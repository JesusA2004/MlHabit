<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * Restablece la contraseña desde el enlace del correo. También es el paso
 * final de una invitación: quien recibió el enlace en su buzón demostró que
 * el correo es suyo, así que se marca como verificado.
 */
class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => $input['password'],
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();
    }
}
