<?php

namespace App\Notifications\Crm;

use App\Models\Consultation;
use App\Support\Crm\CrmLinks;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Al cliente: su asesoría se canceló. */
class ConsultationCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Consultation $consultation)
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
        $c = $this->consultation->loadMissing('lead.customer');

        $mail = (new MailMessage)
            ->subject('Tu asesoría del '.When::long($c->starts_at).' se canceló')
            ->greeting("Hola, {$c->lead->customer->first_name}")
            ->line('Cancelamos la asesoría que tenías agendada para el '.When::long($c->starts_at).'.');

        if (Settings::get('consultation.enabled', true)) {
            $mail->line('Cuando quieras, puedes elegir un nuevo horario:')
                ->action('Agendar otra fecha', CrmLinks::bookConsultation($c->lead));
        }

        return $mail->line('Si tienes dudas, responde a este correo y con gusto te ayudamos.');
    }
}
