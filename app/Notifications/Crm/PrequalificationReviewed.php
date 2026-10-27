<?php

namespace App\Notifications\Crm;

use App\Enums\PrequalificationStatus;
use App\Models\Prequalification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Al cliente: resultado de la revisión manual (lenguaje prudente, nunca "aprobado"). */
class PrequalificationReviewed extends Notification implements ShouldQueue
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
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->prequalification->loadMissing('customer');
        $mail = (new MailMessage)
            ->subject('Revisamos tu solicitud de pago a plazos')
            ->greeting("Hola, {$p->customer->first_name}");

        if ($p->status === PrequalificationStatus::Prequalified) {
            return $mail
                ->line('Con la información que nos compartiste, tu solicitud quedó **precalificada**.')
                ->line('Esto no es una aprobación de crédito: las condiciones finales están sujetas a evaluación y se confirman al formalizar tu proyecto.')
                ->line('Tu asesor te contactará para explicarte los siguientes pasos.');
        }

        return $mail
            ->line('Gracias por tu paciencia. Por ahora no es posible ofrecerte pago a plazos con la información disponible.')
            ->line('Tu proyecto puede seguir adelante con otras formas de pago; tu asesor te contactará para platicar las opciones.');
    }
}
