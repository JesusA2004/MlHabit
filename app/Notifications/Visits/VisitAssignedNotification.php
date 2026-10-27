<?php

namespace App\Notifications\Visits;

use App\Models\SiteVisit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Al técnico: visita asignada o reprogramada. */
class VisitAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly SiteVisit $visit, public readonly string $headline = 'Nueva visita asignada') {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $visit = $this->visit->loadMissing(['address', 'customer']);

        return (new MailMessage)
            ->subject($this->headline.' · '.$visit->folio)
            ->line($this->headline.': '.VisitText::when($visit).'.')
            ->line('Cliente: '.$visit->customer->full_name)
            ->line('Domicilio: '.$visit->address->one_line)
            ->when($visit->access_notes !== null, fn (MailMessage $m) => $m->line('Indicaciones de acceso: '.$visit->access_notes))
            ->action('Ver mis visitas', url('/tecnico/visitas'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->headline,
            'body' => $this->visit->folio.' · '.VisitText::when($this->visit),
            'url' => '/tecnico/visitas',
            'tone' => 'info',
            'icon' => 'map-pinned',
        ];
    }
}
