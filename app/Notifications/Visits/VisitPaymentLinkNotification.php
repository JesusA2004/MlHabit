<?php

namespace App\Notifications\Visits;

use App\Models\SiteVisit;
use App\Models\User;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Al cliente: enlace seguro para pagar una visita apartada por el equipo. */
class VisitPaymentLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly SiteVisit $visit, public readonly string $payUrl) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $visit = $this->visit->loadMissing('customer');

        return (new MailMessage)
            ->subject("Confirma tu visita técnica {$visit->folio}")
            ->greeting('¡Hola, '.$visit->customer->first_name.'!')
            ->line('Apartamos tu visita técnica para el '.VisitText::when($visit).'.')
            ->line('Para confirmarla, realiza el pago de '.Money::of($visit->fee_amount)->format().' con tarjeta en el siguiente enlace seguro.')
            ->when($visit->hold_expires_at !== null, fn (MailMessage $m) => $m->line('El horario queda apartado hasta el '.VisitText::date($visit->hold_expires_at).' a las '.$visit->hold_expires_at?->format('H:i').' h.'))
            ->action('Pagar y confirmar mi visita', $this->payUrl);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['title' => 'Confirma tu visita técnica', 'body' => 'Realiza el pago para confirmar tu visita '.$this->visit->folio.'.', 'url' => VisitText::path($this->payUrl), 'tone' => 'brand', 'icon' => 'credit-card'];
    }
}
