<?php

namespace App\Notifications\Visits;

use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Al cliente: visita confirmada (fecha, ventana, técnico por nombre de pila y comprobante). */
class VisitScheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly SiteVisit $visit,
        public readonly ?string $receiptUrl,
        public readonly bool $needsReschedule = false,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $visit = $this->visit->loadMissing(['technician', 'address', 'customer']);
        $when = VisitText::when($visit);

        $mail = (new MailMessage)
            ->subject("Tu visita técnica {$visit->folio}")
            ->greeting('¡Hola, '.$visit->customer->first_name.'!');

        if ($this->needsReschedule) {
            return $mail
                ->line('Recibimos tu pago. El horario que elegiste se ocupó mientras se confirmaba, así que un asesor te contactará hoy para acordar una nueva fecha.')
                ->line('No tienes que hacer nada más: tu pago está seguro.')
                ->when($this->receiptUrl !== null, fn (MailMessage $m) => $m->action('Ver comprobante', (string) $this->receiptUrl));
        }

        return $mail
            ->line("Tu visita técnica quedó agendada para el {$when}.")
            ->when($visit->technician !== null, fn (MailMessage $m) => $m->line('Te visitará '.VisitText::firstName($visit->technician?->name).', de nuestro equipo técnico.'))
            ->line('Domicilio: '.$visit->address->one_line)
            ->line('Si necesitas reprogramar, responde a este correo o contáctanos con tu folio '.$visit->folio.'.')
            ->when($this->receiptUrl !== null, fn (MailMessage $m) => $m->action('Ver comprobante de pago', (string) $this->receiptUrl));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Visita técnica agendada',
            'body' => 'Tu visita '.$this->visit->folio.' quedó agendada para el '.VisitText::when($this->visit).'.',
            'url' => VisitText::path($this->receiptUrl),
            'tone' => $this->needsReschedule ? 'warning' : 'success',
            'icon' => 'calendar-check',
        ];
    }
}
