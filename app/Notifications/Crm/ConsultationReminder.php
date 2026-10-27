<?php

namespace App\Notifications\Crm;

use App\Models\Consultation;
use App\Support\Crm\CrmLinks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Recordatorio de asesoría al cliente (24 h y 1 h antes). */
class ConsultationReminder extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param '24h'|'1h' $window */
    public function __construct(public readonly Consultation $consultation, public readonly string $window)
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
        $c = $this->consultation->loadMissing(['lead.customer', 'advisor']);

        $mail = (new MailMessage)
            ->subject($this->window === '1h' ? 'Tu asesoría empieza en una hora' : 'Mañana es tu asesoría con ML Habitat')
            ->greeting("¡Hola, {$c->lead->customer->first_name}!")
            ->line('Te recordamos tu asesoría: **'.When::long($c->starts_at).'** por '.mb_strtolower($c->channel->label()).'.')
            ->line("Te atenderá {$c->advisor->name}.");

        if ($c->meeting_url) {
            $mail->action('Entrar a la reunión', $c->meeting_url);
        }

        return $mail->line('¿No puedes asistir? Reprograma aquí: '.CrmLinks::manageConsultation($c));
    }
}
