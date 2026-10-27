<?php

namespace App\Services\Operations;

use App\Enums\UserRole;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Notifications\Platform\UserInvitation;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Alta de técnicos/instaladores: crea el usuario con rol `tecnico` y una
 * contraseña aleatoria que nadie conoce, y envía un enlace para que la
 * persona defina la suya (no hay registro público).
 */
class TechnicianService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array<string, mixed>  $data  Validado por TechnicianRequest
     * @param  list<int>  $zoneIds
     */
    public function create(array $data, array $zoneIds, ?User $actor = null): TechnicianProfile
    {
        $profile = DB::transaction(function () use ($data, $zoneIds) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => Str::password(40),
                'role' => UserRole::Technician,
                'is_active' => true,
            ]);
            // El correo se confirma al usar el enlace de invitación.
            $user->forceFill(['email_verified_at' => now()])->save();

            $profile = TechnicianProfile::query()->create([
                'user_id' => $user->id,
                ...collect($data)->only(['supplier_id', 'status', 'does_visits', 'does_installations', 'skills', 'notes'])->all(),
            ]);

            $user->coverageZones()->sync($zoneIds);

            $this->logger->log('tecnico.creado', $profile, ['after' => ['user_id' => $user->id, 'email' => $user->email]]);

            return $profile;
        });

        $this->invite($profile, $actor);

        return $profile;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $zoneIds
     */
    public function update(TechnicianProfile $profile, array $data, array $zoneIds): TechnicianProfile
    {
        return DB::transaction(function () use ($profile, $data, $zoneIds) {
            $user = $profile->user;
            $user->fill([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? $user->is_active),
            ]);
            $this->logger->logModelChanges('tecnico.usuario_actualizado', $user, ['name', 'phone', 'is_active']);
            $user->save();

            $profile->fill(collect($data)->only(['supplier_id', 'status', 'does_visits', 'does_installations', 'skills', 'notes'])->all());
            $this->logger->logModelChanges('tecnico.actualizado', $profile);
            $profile->save();

            $user->coverageZones()->sync($zoneIds);

            return $profile;
        });
    }

    /**
     * Envía (o reenvía) la invitación de plataforma: enlace firmado para
     * crear contraseña (App\Notifications\Platform\UserInvitation).
     */
    public function invite(TechnicianProfile $profile, ?User $actor = null): void
    {
        $profile->user->notify(new UserInvitation($actor?->name));
        $this->logger->log('tecnico.invitacion_enviada', $profile, ['user_id' => $profile->user_id]);
    }
}
