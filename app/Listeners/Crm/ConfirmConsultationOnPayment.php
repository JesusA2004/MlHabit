<?php

namespace App\Listeners\Crm;

use App\Events\PaymentConfirmed;
use App\Services\Crm\ConsultationPaymentHandler;

/** PaymentConfirmed (consultation_fee) → asesoría confirmada + reunión + avisos. */
class ConfirmConsultationOnPayment
{
    public function __construct(private readonly ConsultationPaymentHandler $handler) {}

    public function handle(PaymentConfirmed $event): void
    {
        $this->handler->onPaymentConfirmed($event->payment);
    }
}
