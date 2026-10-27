<?php

namespace App\Notifications\Crm;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso al equipo comercial: llegó una solicitud nueva. */
class NewLeadReceived extends Notification implements ShouldQueue
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
        $categories = collect($lead->categorySummaries())->pluck('name')->implode(', ');

        return (new MailMessage)
            ->subject("Nueva solicitud {$lead->folio} · {$lead->customer->full_name}")
            ->greeting('Tienes una solicitud nueva')
            ->line("{$lead->customer->full_name} pidió información".($categories ? " sobre {$categories}" : '').'.')
            ->line('C.P. '.($lead->postal_code ?? 'sin indicar').($lead->coverageZone ? ' · dentro de cobertura' : ' · sin cobertura operativa registrada'))
            ->action('Ver prospecto', route('admin.prospectos.show', $lead))
            ->line('Contactar en las primeras horas aumenta mucho la probabilidad de cierre.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'crm.lead.new',
            'title' => "Nueva solicitud {$this->lead->folio}",
            'body' => $this->lead->customer->full_name,
            'url' => route('admin.prospectos.show', $this->lead, false),
            'lead_uuid' => $this->lead->uuid,
        ];
    }
}
