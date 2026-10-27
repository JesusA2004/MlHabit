<?php

namespace App\Listeners\Projects;

use App\Enums\PaymentConcept;
use App\Events\PaymentRefunded;
use App\Models\Contract;
use App\Services\Projects\ContractService;

/**
 * Reembolso de un anticipo: aplica la regla documentada en
 * ContractService::handleDepositRefund (marca, nunca borra ni retrocede).
 */
class RevertContractDeposit
{
    public function __construct(private readonly ContractService $contracts) {}

    public function handle(PaymentRefunded $event): void
    {
        $payment = $event->payment;
        $concept = $payment->concept;

        if ($concept !== PaymentConcept::Deposit || ! $payment->contract_id) {
            return;
        }

        $contract = Contract::query()->find($payment->contract_id);
        if ($contract) {
            $this->contracts->handleDepositRefund($contract);
        }
    }
}
