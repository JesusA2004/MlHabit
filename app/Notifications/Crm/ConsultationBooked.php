<?php

namespace App\Notifications\Crm;

use App\Models\Consultation;
use App\Models\Payment;
use App\Support\Crm\CrmLinks;
use App\Support\Crm\Ics;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

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

        $payment = $c->payment_id !== null ? Payment::query()->find($c->payment_id) : null;
        if ($payment !== null && $payment->status->isSettled()) {
            $mail->line('Recibimos tu pago de '.Money::of($payment->amount)->format()." (folio {$payment->folio}).")
                ->line('Comprobante: '.URL::signedRoute('pagos.comprobante', ['payment' => $payment->uuid]));
        }

        return $mail
            ->line('Ten a la mano fotos y medidas aproximadas del espacio; así aprovechamos mejor el tiempo.')
            ->action('Reprogramar o cancelar', $manage)
            ->attachData(Ics::forConsultation($c, $manage), 'asesoria-ml-habitat.ics', ['mime' => 'text/calendar; charset=UTF-8; method=PUBLISH']);
    }
}
