<?php

namespace App\Services\Projects;

use App\Enums\ContractStatus;
use App\Enums\PaymentConcept;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\User;
use App\Payments\PaymentService;
use App\Services\ActivityLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cobro del anticipo de un contrato a través de PaymentService (visits).
 * La confirmación llega por el evento PaymentConfirmed y la aplica el
 * listener App\Listeners\Projects\ApplyContractPayment.
 */
class DepositService
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly ContractService $contracts,
        private readonly ActivityLogger $logger,
    ) {}

    /** Lo que falta por cubrir del anticipo (nunca negativo). */
    public function remaining(Contract $contract): Money
    {
        return Money::of($contract->deposit_due_amount)->minus($this->contracts->paidDeposit($contract))->max(Money::zero());
    }

    /**
     * Intención de pago para el anticipo. Reutiliza una pendiente del mismo
     * monto; si la anterior falló, crea un intento nuevo con clave
     * `deposit:{contrato}:{n}` (idempotente por contrato + intento).
     */
    public function intentFor(Contract $contract): Payment
    {
        if (! in_array($contract->status, [ContractStatus::PendingDeposit, ContractStatus::DepositRefunded], true)) {
            throw ValidationException::withMessages(['contract' => 'Este contrato no tiene anticipo pendiente.']);
        }

        $remaining = $this->remaining($contract);
        if (! $remaining->isPositive()) {
            throw ValidationException::withMessages(['contract' => 'El anticipo ya está cubierto.']);
        }

        return DB::transaction(function () use ($contract, $remaining) {
            Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();

            /** @var Payment|null $open */
            $open = Payment::query()
                ->where('contract_id', $contract->id)
                ->where('concept', PaymentConcept::Deposit->value)
                ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Processing->value])
                ->latest('id')
                ->first();

            if ($open && Money::of((string) $open->amount)->equals($remaining)) {
                return $open;
            }

            $attempt = Payment::query()->where('contract_id', $contract->id)->where('concept', PaymentConcept::Deposit->value)->count() + 1;
            $project = $contract->project;

            return $this->payments->createIntent(
                $project->customer,
                PaymentConcept::Deposit,
                $remaining,
                "Anticipo del contrato {$contract->folio}",
                ['project_id' => $project->id, 'contract_id' => $contract->id],
                "deposit:{$contract->uuid}:{$attempt}",
            );
        });
    }

    /** Registro manual (transferencia/efectivo) desde el panel. Requiere payments.record. */
    public function recordManual(Contract $contract, string $amount, PaymentMethod $method, string $reference, User $actor): Payment
    {
        if (! $actor->hasPermission('payments.record')) {
            throw ValidationException::withMessages(['amount' => 'No tienes permiso para registrar pagos.']);
        }

        $amountMoney = Money::of($amount);
        $remaining = $this->remaining($contract);
        if (! $amountMoney->isPositive() || $amountMoney->compare($remaining) > 0) {
            throw ValidationException::withMessages(['amount' => 'El monto debe ser mayor que cero y no exceder lo pendiente del anticipo ('.$remaining->format().').']);
        }

        $project = $contract->project;
        $attempt = Payment::query()->where('contract_id', $contract->id)->where('concept', PaymentConcept::Deposit->value)->count() + 1;

        $payment = $this->payments->createIntent(
            $project->customer,
            PaymentConcept::Deposit,
            $amountMoney,
            "Anticipo del contrato {$contract->folio}",
            ['project_id' => $project->id, 'contract_id' => $contract->id],
            "deposit-manual:{$contract->uuid}:{$attempt}",
        );

        $payment = $this->payments->recordManual($payment, $method, $reference, $actor);
        $this->logger->log('contrato.anticipo_manual', $contract, ['payment' => $payment->folio, 'method' => $method->value, 'amount' => $amountMoney->amount]);

        // Por si el evento ya se procesó o la pasarela no lo despacha en este flujo.
        $this->contracts->syncDeposit($contract, $actor);

        return $payment;
    }
}
