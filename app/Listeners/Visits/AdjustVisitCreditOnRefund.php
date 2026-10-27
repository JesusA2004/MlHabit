<?php

namespace App\Listeners\Visits;

use App\Events\PaymentRefunded;
use App\Services\Visits\VisitPaymentHandler;

/** PaymentRefunded (visit_fee) → anula/ajusta el crédito o avisa a finanzas si ya se aplicó. */
class AdjustVisitCreditOnRefund
{
    public function __construct(private readonly VisitPaymentHandler $handler) {}

    public function handle(PaymentRefunded $event): void
    {
        $this->handler->onPaymentRefunded($event->payment, $event->refund);
    }
}
