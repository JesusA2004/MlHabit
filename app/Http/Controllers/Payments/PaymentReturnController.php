<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * GET /pagos/{payment:uuid}/retorno — retorno genérico de 3D Secure para
 * módulos que no definen el suyo (p. ej. anticipos). Nunca confía en el
 * `?id=` de la URL: concilia con el API y redirige a `metadata.next_url`
 * o al comprobante firmado.
 */
class PaymentReturnController extends Controller
{
    public function __invoke(Payment $payment, PaymentService $payments): RedirectResponse
    {
        try {
            $payment = $payments->reconcile($payment);
        } catch (Throwable) {
            // Lo conciliará payments:reconcile.
        }

        $next = $payment->metadata['next_url'] ?? null;

        if (is_string($next) && str_starts_with($next, url('/'))) {
            return redirect()->to($next);
        }

        if ($payment->status->isSettled()) {
            return redirect()->to(URL::signedRoute('pagos.comprobante', ['payment' => $payment->uuid]));
        }

        return redirect('/')->with('error', $payment->error_message ?: 'Estamos confirmando tu pago. Te avisaremos por correo en cuanto se acredite.');
    }
}
