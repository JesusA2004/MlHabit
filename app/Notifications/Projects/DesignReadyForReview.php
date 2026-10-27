<?php

namespace App\Notifications\Projects;

use App\Models\DesignVersion;
use Illuminate\Notifications\Messages\MailMessage;

class DesignReadyForReview extends ProjectNotification
{
    public function __construct(public readonly DesignVersion $design)
    {
        parent::__construct();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Tu diseño está listo para revisión')
            ->greeting('¡Hola!')
            ->line("Terminamos la versión {$this->design->number} del diseño de «{$this->design->project->name}».")
            ->line('Revisa la distribución y los acabados. Si algo no te convence, pide cambios con un comentario.');

        return $this->hasPortal($notifiable)
            ? $mail->action('Revisar mi diseño', $this->portalUrl('/disenos'))
            : $mail->line('Tu asesor te lo compartirá para que lo revises.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Tu diseño está listo para revisión',
            'body' => "Versión {$this->design->number}: {$this->design->title}",
            'url' => '/portal/disenos',
        ];
    }

    protected function tone(): string
    {
        return 'info';
    }

    protected function icon(): string
    {
        return 'pencil-ruler';
    }
}
