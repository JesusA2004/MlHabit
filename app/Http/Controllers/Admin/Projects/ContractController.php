<?php

namespace App\Http\Controllers\Admin\Projects;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Services\Projects\DepositService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ContractController extends Controller
{
    /** Anticipo pagado por transferencia/efectivo (payments.record). */
    public function recordDeposit(Request $request, Contract $contract, DepositService $deposits): RedirectResponse
    {
        Gate::authorize('payments.record');
        $data = $request->validate([
            'amount' => ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'method' => ['required', Rule::in([PaymentMethod::Transfer->value, PaymentMethod::Cash->value, PaymentMethod::Other->value])],
            'reference' => ['required', 'string', 'max:120'],
        ], [
            'amount.regex' => 'Escribe el monto con hasta 2 decimales, p. ej. 9850.00.',
            'reference.required' => 'Anota la referencia (folio de transferencia, recibo de caja…).',
        ], ['amount' => 'monto', 'method' => 'forma de pago', 'reference' => 'referencia']);

        $payment = $deposits->recordManual($contract, $data['amount'], PaymentMethod::from($data['method']), $data['reference'], $request->user());

        return back()->with('success', "Pago {$payment->folio} registrado.");
    }
}
