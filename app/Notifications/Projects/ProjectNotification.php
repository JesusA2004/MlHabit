<?php

namespace App\Notifications\Projects;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;

/**
 * Base de las notificaciones del módulo de proyectos: correo + centro de
 * notificaciones (database). A un cliente sin cuenta de portal solo se le
 * envía correo. Se encolan después del commit de la transacción.
 */
abstract class ProjectNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['mail', 'database'];
    }

    /**
     * Datos de la notificación (title, body, url interna).
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(object $notifiable): array;

    /**
     * Forma que espera el centro de notificaciones (platform):
     * {title, body?, url? (ruta interna), tone?, icon?}.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $data = $this->toArray($notifiable) + ['tone' => $this->tone(), 'icon' => $this->icon()];

        return array_intersect_key($data, array_flip(['title', 'body', 'url', 'tone', 'icon']));
    }

    protected function tone(): string
    {
        return 'brand';
    }

    protected function icon(): string
    {
        return 'folder-kanban';
    }

    protected function hasPortal(object $notifiable): bool
    {
        return ! $notifiable instanceof AnonymousNotifiable;
    }

    protected function portalUrl(string $path = ''): string
    {
        return url('/portal'.$path);
    }
}
