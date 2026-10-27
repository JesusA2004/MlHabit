<?php

namespace App\Notifications\Projects;

use App\Models\Contract;
use Illuminate\Notifications\Messages\MailMessage;

/** Anticipo cubierto: proyecto autorizado. `internal` = aviso al asesor. */
class DepositReceived extends ProjectNotification
{
    public function __construct(public readonly Contract $contract, public readonly bool $internal = false)
    {
        parent::__construct();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $project = $this->contract->project;

        if ($this->internal) {
            return (new MailMessage)
                ->subject("Anticipo cubierto · {$project->folio}")
                ->line("El contrato {$this->contract->folio} de «{$project->name}» ya tiene su anticipo cubierto.")
                ->action('Ver proyecto', url("/admin/proyectos/{$project->uuid}"));
        }

        $mail = (new MailMessage)
            ->subject('Recibimos tu anticipo')
            ->greeting('¡Listo!')
            ->line("Tu proyecto «{$project->name}» está autorizado y entra a la cola de fabricación.")
            ->line('Te avisaremos cuando tengamos fecha de instalación.');

        return $this->hasPortal($notifiable) ? $mail->action('Ver mi proyecto', $this->portalUrl()) : $mail;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $project = $this->contract->project;

        return [
            'title' => $this->internal ? "Anticipo cubierto · {$project->folio}" : 'Recibimos tu anticipo',
            'body' => $this->internal ? $project->name : 'Tu proyecto está autorizado.',
            'url' => $this->internal ? "/admin/proyectos/{$project->uuid}" : '/portal',
        ];
    }

    protected function tone(): string
    {
        return 'success';
    }

    protected function icon(): string
    {
        return 'wallet';
    }
}
