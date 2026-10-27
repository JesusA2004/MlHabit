<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;

/** El cargo fue rechazado o cancelado de forma definitiva. */
class PaymentFailed
{
    use Dispatchable;

    public function __construct(public readonly Payment $payment) {}
}
