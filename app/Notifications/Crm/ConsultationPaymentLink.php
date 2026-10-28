<?php

namespace App\Notifications\Crm;

use App\Models\Consultation;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Al cliente: el equipo apartó su asesoría; se confirma al pagar con tarjeta. */
class ConsultationPaymentLink extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Consultation $consultation, public readonly string $payUrl)
    {
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
        $hold = $c->hold_expires_at !== null ? ' antes del '.When::long($c->hold_expires_at) : '';

        return (new MailMessage)
            ->subject('Confirma tu asesoría con el pago · '.When::long($c->starts_at))
            ->greeting("¡Hola, {$c->lead->customer->first_name}!")
            ->line('Apartamos tu asesoría para el **'.When::long($c->starts_at).'** (hora del centro de México) por '.$c->channel->label().'.')
            ->line('Para confirmarla, realiza el pago de **'.Money::of($c->price)->format().'** con tarjeta'.$hold.'. Si no se paga a tiempo, el horario se libera.')
            ->action('Pagar y confirmar', $this->payUrl)
            ->line('El pago es seguro y lo procesa Openpay: no guardamos los datos de tu tarjeta.');
    }
}
