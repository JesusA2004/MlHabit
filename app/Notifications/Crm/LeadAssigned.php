<?php

namespace App\Notifications\Crm;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Se asignó un prospecto a un miembro del equipo. */
class LeadAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Lead $lead)
    {
        // Se envía solo si la transacción del flujo se confirma.
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lead = $this->lead->loadMissing('customer');

        return (new MailMessage)
            ->subject("Te asignaron el prospecto {$lead->folio}")
            ->greeting('Nuevo prospecto a tu cargo')
            ->line("{$lead->customer->full_name} ({$lead->status->label()}).")
            ->action('Abrir prospecto', route('admin.prospectos.show', $lead));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'crm.lead.assigned',
            'title' => "Te asignaron {$this->lead->folio}",
            'body' => $this->lead->customer->full_name,
            'url' => route('admin.prospectos.show', $this->lead, false),
            'lead_uuid' => $this->lead->uuid,
        ];
    }
}
