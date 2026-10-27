<?php

namespace App\Services\Projects;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\Platform\UserInvitation;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Alta de clientes en el portal (nunca hay registro público).
 *  - Crea/vincula un User rol `cliente` con contraseña aleatoria inutilizable.
 *  - Reutiliza la invitación de platform (App\Notifications\Platform\UserInvitation):
 *    URL firmada /invitacion/{user} de 72 h → "Crea tu contraseña" → Fortify
 *    fija la contraseña y verifica el correo; el login lleva al cliente a /portal.
 */
class PortalInvitationService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /** Invita (o reenvía) y devuelve el usuario del portal. */
    public function invite(Customer $customer, User $actor): User
    {
        $email = mb_strtolower(trim((string) $customer->email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'El cliente necesita un correo válido para recibir la invitación.']);
        }

        $user = DB::transaction(function () use ($customer, $email) {
            /** @var Customer $locked */
            $locked = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

            $user = $locked->user_id ? User::query()->find($locked->user_id) : null;
            $byEmail = User::query()->where('email', $email)->first();

            if ($user === null && $byEmail !== null) {
                $linkedElsewhere = Customer::query()->where('user_id', $byEmail->id)->whereKeyNot($locked->id)->exists();
                if ($byEmail->role !== UserRole::Customer || $linkedElsewhere) {
                    throw ValidationException::withMessages(['email' => 'Ese correo ya pertenece a otra cuenta. Usa un correo distinto para este cliente.']);
                }
                $user = $byEmail;
            }

            if ($user === null) {
                /** @var User $user */
                $user = User::query()->create([
                    'name' => $locked->full_name,
                    'email' => $email,
                    'phone' => $locked->phone,
                    'role' => UserRole::Customer,
                    'is_active' => true,
                    'password' => Str::random(64),
                ]);
            }

            if ($user->role !== UserRole::Customer) {
                throw ValidationException::withMessages(['email' => 'La cuenta vinculada no es de cliente.']);
            }

            if ($locked->user_id !== $user->id) {
                $locked->update(['user_id' => $user->id]);
            }

            return $user;
        });

        $user->notify(new UserInvitation($actor->name));
        $this->logger->log('cliente.invitado_portal', $customer, ['user_id' => $user->id, 'by' => $actor->id]);

        return $user;
    }
}
