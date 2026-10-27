<?php

namespace App\Notifications\Crm;

use App\Models\Consultation;
use App\Support\Crm\CrmLinks;
use App\Support\Crm\Ics;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Al cliente: asesoría agendada o reprogramada, con archivo .ics. */
class ConsultationBooked extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Consultation $consultation, public readonly bool $rescheduled = false)
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
        $manage = CrmLinks::manageConsultation($c);

        $mail = (new MailMessage)
            ->subject(($this->rescheduled ? 'Reprogramamos tu asesoría' : 'Tu asesoría está agendada').' · '.When::long($c->starts_at))
            ->greeting("¡Hola, {$c->lead->customer->first_name}!")
            ->line(($this->rescheduled ? 'Tu asesoría quedó para el ' : 'Te esperamos el ').'**'.When::long($c->starts_at).'** (hora del centro de México).')
            ->line('Medio: '.$c->channel->label().'. '.$c->channel->description())
            ->line("Te atenderá {$c->advisor->name}. Folio: {$c->folio}.");

        if ($c->meeting_url) {
            $mail->line("Enlace de la reunión: {$c->meeting_url}");
        }

        if (Money::of($c->price)->isPositive()) {
            $mail->line('La asesoría tiene un costo de '.Money::of($c->price)->format().'; por ahora el pago se coordina directamente con tu asesor.');
        }

        return $mail
            ->line('Ten a la mano fotos y medidas aproximadas del espacio; así aprovechamos mejor el tiempo.')
            ->action('Reprogramar o cancelar', $manage)
            ->attachData(Ics::forConsultation($c, $manage), 'asesoria-ml-habitat.ics', ['mime' => 'text/calendar; charset=UTF-8; method=PUBLISH']);
    }
}
