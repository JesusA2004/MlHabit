<?php

namespace App\Notifications\Crm;

use App\Models\Prequalification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Al equipo de crédito: hay una precalificación lista para revisar. */
class PrequalificationSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Prequalification $prequalification)
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
        $p = $this->prequalification->loadMissing('customer');

        return (new MailMessage)
            ->subject("Precalificación por revisar {$p->folio}")
            ->line("{$p->customer->full_name} envió su información para precalificación.")
            ->action('Revisar', route('admin.precalificaciones.show', $p));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $p = $this->prequalification->loadMissing('customer');

        return [
            'type' => 'crm.prequalification.submitted',
            'title' => "Precalificación por revisar {$p->folio}",
            'body' => $p->customer->full_name,
            'url' => route('admin.precalificaciones.show', $p, false),
        ];
    }
}
