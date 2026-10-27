<?php

namespace App\Notifications\Crm;

use App\Models\Lead;
use App\Support\Crm\CrmLinks;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Confirmación al cliente: recibimos tu solicitud (con folio). */
class LeadSubmittedConfirmation extends Notification implements ShouldQueue
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
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lead = $this->lead->loadMissing('customer');

        $mail = (new MailMessage)
            ->subject("Recibimos tu solicitud · folio {$lead->folio}")
            ->greeting("¡Hola, {$lead->customer->first_name}!")
            ->line('Gracias por contarnos sobre tu proyecto. Tu folio es **'.$lead->folio.'**.')
            ->line('Un asesor de ML Habitat revisará tu solicitud y te contactará en horario laboral por el medio que elegiste.');

        if (Settings::get('consultation.enabled', true)) {
            $mail->line('Si quieres avanzar desde ahora, puedes elegir el horario de tu asesoría remota:')
                ->action('Agendar mi asesoría', CrmLinks::bookConsultation($lead));
        }

        return $mail->line('Si no fuiste tú quien envió esta solicitud, puedes ignorar este correo.');
    }
}
