<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ContractStatus;
use App\Enums\PaymentConcept;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResolvesPortalCustomer;
use App\Http\Presenters\Projects\ProjectPresenter as P;
use App\Models\Contract;
use App\Models\Payment;
use App\Payments\ChargeResponse;
use App\Payments\Openpay\OpenpayConfig;
use App\Payments\PaymentException;
use App\Payments\PaymentService;
use App\Services\Projects\ContractService;
use App\Services\Projects\DepositService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    use ResolvesPortalCustomer;

    /** Pagos del cliente (solo lectura) + anticipos pendientes. */
    public function index(Request $request): Response
    {
        $customer = $this->customer($request);
        $projects = $this->projects($customer);

        $payments = $customer ? $this->attempt(fn () => Payment::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['paid', 'partially_refunded', 'refunded', 'processing'])
            ->latest('id')->limit(100)->get()
            ->map(fn (Payment $p) => P::payment($p, forClient: true))->values()->all(), []) : [];

        $pending = Contract::query()
            ->whereIn('project_id', $projects->pluck('id'))
            ->whereIn('status', [ContractStatus::PendingDeposit->value, ContractStatus::DepositRefunded->value])
            ->with('project:id,folio,name')
            ->get()
            ->map(fn (Contract $c) => P::contract($c, [
                'project' => ['folio' => $c->project->folio, 'name' => $c->project->name],
                'pay_url' => "/portal/pagos/anticipo/{$c->uuid}",
            ]))->values();

        $contracts = Contract::query()
            ->whereIn('project_id', $projects->pluck('id'))
            ->with('project:id,folio,name')
            ->get()
            ->map(fn (Contract $c) => P::contract($c, ['project' => ['folio' => $c->project->folio, 'name' => $c->project->name]]))
            ->values();

        return Inertia::render('portal/Pagos', [
            'payments' => $payments,
            'pendingDeposits' => $pending,
            'contracts' => $contracts,
        ]);
    }

    /** Página de pago del anticipo con OpenpayCardForm. */
    public function deposit(Request $request, Contract $contract, DepositService $deposits): Response|RedirectResponse
    {
        abort_unless(Gate::allows('view', $contract), 404);
        Gate::authorize('pay', $contract);
        $contract->load(['project:id,uuid,folio,name', 'quote:id,uuid,folio']);

        $remaining = $deposits->remaining($contract);
        $payable = in_array($contract->status, [ContractStatus::PendingDeposit, ContractStatus::DepositRefunded], true) && $remaining->isPositive();
        $payment = $payable ? $deposits->intentFor($contract) : null;

        return Inertia::render('portal/PagoAnticipo', [
            'contract' => P::contract($contract, [
                'project' => ['folio' => $contract->project->folio, 'name' => $contract->project->name],
                'remaining' => $remaining->amount,
            ]),
            'payment' => $payment ? [
                'uuid' => $payment->uuid,
                'amount' => (string) $payment->amount,
                'charge_url' => route('portal.pagos.cobrar', $payment->uuid),
            ] : null,
            'openpay' => app(OpenpayConfig::class)->jsConfig(),
            'paid' => ! $payable,
        ]);
    }

    /**
     * chargeUrl de OpenpayCardForm: cobra la intención de anticipo del
     * propio cliente vía PaymentService::chargeCard (3DS incluido).
     */
    public function charge(Request $request, Payment $payment, PaymentService $payments, ContractService $contracts): JsonResponse
    {
        $customer = $this->customer($request);
        $concept = $payment->concept;
        abort_unless($customer !== null && $payment->customer_id === $customer->id && $concept === PaymentConcept::Deposit && $payment->contract_id, 404);

        $data = $request->validate([
            'token_id' => ['required', 'string', 'max:100'],
            'device_session_id' => ['required', 'string', 'max:255'],
            'attempt_key' => ['nullable', 'uuid'],
        ]);

        $contract = Contract::query()->find($payment->contract_id);
        $returnUrl = $contract ? route('portal.pagos.anticipo', $contract->uuid) : route('portal.pagos.index');

        if ($payment->status->isSettled()) {
            return response()->json(ChargeResponse::fromPayment($payment, $returnUrl));
        }

        try {
            $payment = $payments->chargeCard($payment, $data['token_id'], $data['device_session_id'], $request, $returnUrl);
        } catch (PaymentException $e) {
            return response()->json(ChargeResponse::fromException($e, $returnUrl));
        }

        if ($contract) {
            $contracts->syncDeposit($contract, $request->user());
        }

        return response()->json(ChargeResponse::fromPayment($payment, $returnUrl));
    }
}
