<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un pago quedó confirmado por el backend (respuesta del API verificada,
 * webhook reconciliado o registro manual autorizado). Se despacha UNA vez
 * por pago, dentro de la transacción que lo marca como pagado
 * (afterCommit), para que los módulos reaccionen: visitas → visita pagada
 * y crédito; contratos → anticipo cubierto.
 */
class PaymentConfirmed
{
    use Dispatchable;

    public function __construct(public readonly Payment $payment) {}
}
