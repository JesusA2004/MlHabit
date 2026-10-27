<?php

namespace App\Notifications\Crm;

use App\Models\Consultation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Al asesor: se agendó, reprogramó o canceló una asesoría suya. */
class ConsultationAdvisorAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param 'booked'|'rescheduled'|'cancelled' $event */
    public function __construct(public readonly Consultation $consultation, public readonly string $event)
    {
        // Se envía solo si la transacción del flujo se confirma.
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    private function title(): string
    {
        $when = When::long($this->consultation->starts_at);

        return match ($this->event) {
            'booked' => "Nueva asesoría: {$when}",
            'rescheduled' => "Asesoría reprogramada: {$when}",
            default => "Asesoría cancelada: {$when}",
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        $c = $this->consultation->loadMissing('lead.customer');

        return (new MailMessage)
            ->subject($this->title())
            ->line("{$c->lead->customer->full_name} · {$c->channel->label()} · folio {$c->folio}.")
            ->action('Ver asesoría', route('admin.asesorias.show', $c));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $c = $this->consultation->loadMissing('lead.customer');

        return [
            'type' => 'crm.consultation.'.$this->event,
            'title' => $this->title(),
            'body' => $c->lead->customer->full_name,
            'url' => route('admin.asesorias.show', $c, false),
        ];
    }
}
