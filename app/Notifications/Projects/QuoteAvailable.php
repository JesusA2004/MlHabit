<?php

namespace App\Notifications\Projects;

use App\Models\Quote;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;

class QuoteAvailable extends ProjectNotification
{
    public function __construct(public readonly Quote $quote)
    {
        parent::__construct();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Tu cotización {$this->quote->folio} está lista")
            ->greeting('¡Hola!')
            ->line("Preparamos la cotización de tu proyecto «{$this->quote->project->name}».")
            ->line('Total: '.Money::of($this->quote->total)->format($this->quote->currency).'.');

        if ($this->quote->valid_until) {
            $mail->line('Vigente hasta el '.$this->quote->valid_until->format('d/m/Y').'.');
        }

        return $this->hasPortal($notifiable)
            ? $mail->action('Ver mi cotización', $this->portalUrl("/cotizaciones/{$this->quote->uuid}"))
            : $mail->line('Tu asesor te compartirá el detalle y los siguientes pasos.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Tu cotización está lista',
            'body' => "Cotización {$this->quote->folio} por ".Money::of($this->quote->total)->format(),
            'url' => "/portal/cotizaciones/{$this->quote->uuid}",
        ];
    }

    protected function tone(): string
    {
        return 'info';
    }

    protected function icon(): string
    {
        return 'receipt-text';
    }
}
