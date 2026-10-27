<?php

namespace App\Listeners\Visits;

use App\Events\PaymentConfirmed;
use App\Services\Visits\VisitPaymentHandler;

/** PaymentConfirmed (visit_fee) → visita agendada + crédito + avisos. */
class ConfirmVisitOnPayment
{
    public function __construct(private readonly VisitPaymentHandler $handler) {}

    public function handle(PaymentConfirmed $event): void
    {
        $this->handler->onPaymentConfirmed($event->payment);
    }
}
