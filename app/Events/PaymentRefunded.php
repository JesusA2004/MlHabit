<?php

namespace App\Events;

use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Foundation\Events\Dispatchable;

/** Se completó un reembolso (parcial o total) de un pago. */
class PaymentRefunded
{
    use Dispatchable;

    public function __construct(public readonly Payment $payment, public readonly Refund $refund) {}
}
