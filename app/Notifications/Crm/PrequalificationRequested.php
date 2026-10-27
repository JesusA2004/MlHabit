<?php

namespace App\Notifications\Crm;

use App\Enums\PrequalificationStatus;
use App\Models\Prequalification;
use App\Support\Crm\CrmLinks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Al cliente: enlace para llenar (o completar) su precalificación. */
class PrequalificationRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Prequalification $prequalification, public readonly ?string $message = null)
    {
        // Se envía solo si la transacción del flujo se confirma.
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->prequalification->loadMissing(['lead', 'customer']);
        $infoRequested = $p->status === PrequalificationStatus::InfoRequested;

        $mail = (new MailMessage)
            ->subject($infoRequested ? 'Necesitamos un poco más de información' : 'Tu solicitud de pago a plazos')
            ->greeting("Hola, {$p->customer->first_name}")
            ->line($infoRequested
                ? 'Para continuar con la revisión de tu solicitud necesitamos algunos datos o documentos adicionales.'
                : 'Como lo platicamos, puedes enviarnos tu información para revisar si es posible ofrecerte pago a plazos.');

        if ($this->message) {
            $mail->line('Nota de tu asesor: '.$this->message);
        }

        return $mail
            ->action($infoRequested ? 'Completar mi información' : 'Llenar mi solicitud', CrmLinks::prequalification($p->lead))
            ->line('Esta revisión es una precalificación: no es una aprobación de crédito y está sujeta a evaluación final.');
    }
}
