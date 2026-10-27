<?php

namespace App\Notifications\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Notifications\Messages\MailMessage;

/** Cambio de etapa visible para el cliente. */
class ProjectStatusUpdated extends ProjectNotification
{
    public function __construct(public readonly Project $project, public readonly ProjectStatus $status, public readonly ?string $note = null)
    {
        parent::__construct();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Tu proyecto avanzó: {$this->status->label()}")
            ->greeting('¡Hola!')
            ->line($this->status->clientDescription());

        if ($this->note) {
            $mail->line($this->note);
        }

        return $this->hasPortal($notifiable) ? $mail->action('Ver mi proyecto', $this->portalUrl()) : $mail;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Tu proyecto avanzó: {$this->status->label()}",
            'body' => $this->status->clientDescription(),
            'url' => '/portal',
        ];
    }

    protected function tone(): string
    {
        return 'brand';
    }

    protected function icon(): string
    {
        return 'folder-kanban';
    }
}
