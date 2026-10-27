<?php

namespace App\Services\Operations;

use App\Enums\Permission;
use App\Models\Project;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

/**
 * Envía notificaciones de operaciones sin romper el flujo de negocio: un
 * fallo de correo se reporta, pero nunca revierte una transición ya hecha.
 */
class OperationsNotifier
{
    /** Cliente dueño del proyecto (si tiene cuenta activa de portal). */
    public function clientOf(Project $project): ?User
    {
        $user = $project->customer?->user;

        return $user !== null && $user->is_active ? $user : null;
    }

    public function client(Project $project, Notification $notification): void
    {
        $this->send($this->clientOf($project), $notification);
    }

    /**
     * @param  User|iterable<User|null>|null  $users
     */
    public function send(User|iterable|null $users, Notification $notification): void
    {
        $list = collect($users instanceof User ? [$users] : ($users ?? []))
            ->filter(fn ($u) => $u instanceof User && $u->is_active)
            ->unique('id')
            ->values();

        if ($list->isEmpty()) {
            return;
        }

        try {
            NotificationFacade::send($list, $notification);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Personal activo con un permiso (excluye a quien dispara la acción).
     *
     * @return list<User>
     */
    public function staffWith(Permission $permission, ?User $except = null): array
    {
        return array_values(User::query()
            ->withPermission($permission)
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->limit(50)
            ->get()
            ->filter(fn (User $u) => $u->isStaff())
            ->all());
    }
}
