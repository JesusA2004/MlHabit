<?php

namespace App\Notifications\Crm;

use App\Models\Consultation;
use App\Models\Payment;
use App\Support\Calendar\CalendarEvent;
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

        $payment = $c->payment_id !== null ? Payment::query()->find($c->payment_id) : null;
        $paid = $payment !== null && $payment->status->isSettled();
        $advisorFirstName = explode(' ', trim($c->advisor->name))[0];

        $mail = (new MailMessage)
            ->subject(($this->rescheduled ? 'Reprogramamos tu asesoría' : ($paid ? 'Asesoría confirmada' : 'Tu asesoría está agendada')).' · '.When::long($c->starts_at))
            ->greeting("¡Hola, {$c->lead->customer->first_name}!")
            ->line(($this->rescheduled ? 'Tu asesoría quedó para el ' : 'Te esperamos el ').'**'.When::long($c->starts_at).'** (hora del centro de México).')
            ->line('Medio: '.$c->channel->label().' · '.$c->starts_at->diffInMinutes($c->ends_at).' minutos. '.$c->channel->description())
            ->line("Te atenderá {$advisorFirstName}. Folio de la asesoría: **{$c->folio}**.");

        if ($c->meeting_url) {
            $mail->line("**Videollamada:** [Unirse a la videollamada]({$c->meeting_url}) — {$c->meeting_url}")
                ->line('Abre el enlace unos minutos antes desde tu computadora o celular; no necesitas instalar nada.');
        }

        if ($paid) {
            $mail->line('Recibimos tu pago de **'.Money::of($payment->amount)->format().' MXN**'
                .($payment->card_last4 ? ' con tarjeta terminación '.$payment->card_last4 : '')
                ." (folio {$payment->folio}).")
                ->line('[Ver comprobante de pago]('.URL::signedRoute('pagos.comprobante', ['payment' => $payment->uuid]).')');
        }

        $google = CalendarEvent::forConsultation($c, $manage)->googleCalendarUrl();

        return $mail
            ->line("Adjuntamos el archivo para tu calendario. ¿Usas Google Calendar? [Agrégala aquí]({$google}).")
            ->line('Ten a la mano fotos y medidas aproximadas del espacio; así aprovechamos mejor el tiempo.')
            ->action('Ver mi asesoría', $manage)
            ->line('Desde ese enlace puedes reprogramar o cancelar.')
            ->attachData(Ics::forConsultation($c, $manage), 'asesoria-ml-habitat.ics', ['mime' => 'text/calendar; charset=UTF-8; method=PUBLISH']);
    }
}
