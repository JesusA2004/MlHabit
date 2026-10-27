<?php

namespace App\Notifications\Projects;

use App\Models\Contract;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;

/** Contrato creado: falta el anticipo. */
class ContractReady extends ProjectNotification
{
    public function __construct(public readonly Contract $contract)
    {
        parent::__construct();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Tu contrato {$this->contract->folio} está listo")
            ->greeting('¡Gracias por tu confianza!')
            ->line('Total del proyecto: '.Money::of($this->contract->contract_amount)->format($this->contract->currency).'.')
            ->line('Anticipo por pagar: '.Money::of($this->contract->deposit_due_amount)->format($this->contract->currency).'.');

        if (Money::of($this->contract->visit_credit_amount)->isPositive()) {
            $mail->line('Ya descontamos el crédito de tu visita técnica: '.Money::of($this->contract->visit_credit_amount)->format().'.');
        }

        return $this->hasPortal($notifiable)
            ? $mail->action('Pagar mi anticipo', $this->portalUrl("/pagos/anticipo/{$this->contract->uuid}"))
            : $mail->line('Tu asesor te enviará las opciones para pagar el anticipo.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Tu contrato está listo',
            'body' => 'Anticipo por pagar: '.Money::of($this->contract->deposit_due_amount)->format(),
            'url' => "/portal/pagos/anticipo/{$this->contract->uuid}",
        ];
    }

    protected function tone(): string
    {
        return 'warning';
    }

    protected function icon(): string
    {
        return 'file-signature';
    }
}
