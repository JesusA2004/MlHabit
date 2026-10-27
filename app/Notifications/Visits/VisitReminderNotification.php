<?php

namespace App\Notifications\Visits;

use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Recordatorio 24 h antes (cliente y técnico). */
class VisitReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly SiteVisit $visit, public readonly bool $forTechnician = false) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $visit = $this->visit->loadMissing(['address', 'customer', 'technician']);

        if ($this->forTechnician) {
            return (new MailMessage)
                ->subject('Mañana: visita '.$visit->folio)
                ->line('Recordatorio: tienes una visita técnica el '.VisitText::when($visit).'.')
                ->line('Cliente: '.$visit->customer->full_name)
                ->line('Domicilio: '.$visit->address->one_line)
                ->action('Ver mis visitas', url('/tecnico/visitas'));
        }

        return (new MailMessage)
            ->subject('Recordatorio de tu visita técnica '.$visit->folio)
            ->greeting('¡Hola, '.$visit->customer->first_name.'!')
            ->line('Te recordamos tu visita técnica el '.VisitText::when($visit).'.')
            ->when($visit->technician !== null, fn (MailMessage $m) => $m->line('Te visitará '.VisitText::firstName($visit->technician?->name).'.'))
            ->line('Ten a la mano las medidas aproximadas o fotos del espacio si las tienes. Si necesitas reprogramar, contáctanos con tu folio '.$visit->folio.'.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Recordatorio de visita',
            'body' => $this->visit->folio.' · '.VisitText::when($this->visit),
            'url' => $this->forTechnician ? '/tecnico/visitas' : null,
            'tone' => 'info',
            'icon' => 'bell',
        ];
    }
}
