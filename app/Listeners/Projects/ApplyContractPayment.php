<?php

namespace App\Listeners\Projects;

use App\Enums\PaymentConcept;
use App\Events\PaymentConfirmed;
use App\Models\Contract;
use App\Models\Project;
use App\Models\ProjectEvent;
use App\Services\Projects\ContractService;
use App\Services\Projects\ProjectWorkflow;
use App\Support\Money;

/**
 * Pagos de contrato confirmados (anticipo, parcialidad, finiquito).
 * Registrado por descubrimiento de eventos (handle tipado).
 *
 * Idempotente: el evento puede llegar dos veces (API + webhook); el
 * contrato se activa una sola vez (fila bloqueada + estado) y el evento de
 * cronología del pago se deduplica por payment_id.
 */
class ApplyContractPayment
{
    public function __construct(
        private readonly ContractService $contracts,
        private readonly ProjectWorkflow $workflow,
    ) {}

    public function handle(PaymentConfirmed $event): void
    {
        $payment = $event->payment;
        $concept = $payment->concept;

        if (! in_array($concept, [PaymentConcept::Deposit, PaymentConcept::Installment, PaymentConcept::Balance], true)) {
            return;
        }

        $contract = $payment->contract_id ? Contract::query()->find($payment->contract_id) : null;
        $project = $payment->project_id ? Project::query()->find($payment->project_id) : $contract?->project;
        if ($project === null) {
            return;
        }

        $alreadyLogged = ProjectEvent::query()
            ->where('project_id', $project->id)
            ->where('type', 'payment_received')
            ->where('metadata->payment_id', $payment->id)
            ->exists();

        if (! $alreadyLogged) {
            $this->workflow->record(
                $project,
                'payment_received',
                'Pago recibido: '.Money::of((string) $payment->amount)->format(),
                $concept->label(),
                null,
                'internal',
                metadata: ['payment_id' => $payment->id, 'concept' => $concept->value],
            );
        }

        if ($concept === PaymentConcept::Deposit && $contract !== null) {
            $this->contracts->syncDeposit($contract);
        }
    }
}
