<?php

namespace App\Services\Projects;

use App\Enums\ContractStatus;
use App\Enums\LeadStatus;
use App\Enums\PaymentConcept;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Enums\QuoteStatus;
use App\Enums\VisitCreditStatus;
use App\Models\Contract;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Quote;
use App\Models\User;
use App\Models\VisitCredit;
use App\Notifications\Projects\ContractReady;
use App\Notifications\Projects\DepositReceived;
use App\Services\ActivityLogger;
use App\Services\Crm\LeadWorkflow;
use App\Support\Folio;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aceptación de cotización → contrato → anticipo.
 *
 * Aceptar (cliente en portal o equipo a nombre del cliente con motivo) es
 * una transacción con bloqueo de filas (cotización, proyecto, créditos):
 *  - Idempotente: repetir la aceptación devuelve el MISMO contrato.
 *  - Otras versiones enviadas del proyecto → `superseded`.
 *  - contract_amount = quote.total; deposit_percent congelado desde
 *    Settings commercial.deposit_percent; deposit_amount = % del total.
 *  - Se aplica a lo más UN VisitCredit disponible y vigente de visitas del
 *    mismo cliente ligadas a este proyecto o a su prospecto
 *    (visit_credits.contract_id UNIQUE lo garantiza también en BD).
 *  - visit_credit_amount = min(crédito, anticipo);
 *    deposit_due_amount = anticipo − crédito. El total NO cambia.
 *  - Proyecto → pending_deposit (pasando por quoting si estaba en diseño).
 *    Si no queda anticipo por pagar → contrato activo y proyecto autorizado.
 */
class ContractService
{
    public function __construct(
        private readonly ProjectWorkflow $workflow,
        private readonly QuoteService $quotes,
        private readonly ActivityLogger $logger,
        private readonly CustomerNotifier $notifier,
    ) {}

    public function acceptQuote(Quote $quote, User $actor, ?string $onBehalfReason = null, ?string $ip = null): Contract
    {
        $created = false;

        $contract = DB::transaction(function () use ($quote, $actor, $onBehalfReason, $ip, &$created) {
            /** @var Quote $lockedQuote */
            $lockedQuote = Quote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            /** @var Project $project */
            $project = Project::query()->whereKey($lockedQuote->project_id)->lockForUpdate()->firstOrFail();

            if ($lockedQuote->status === QuoteStatus::Accepted) {
                return Contract::query()->where('quote_id', $lockedQuote->id)->firstOrFail();
            }

            if (Contract::query()->where('project_id', $project->id)->exists()) {
                throw ValidationException::withMessages(['quote' => 'Este proyecto ya tiene un contrato con otra cotización.']);
            }
            if ($project->status === ProjectStatus::Cancelled) {
                throw ValidationException::withMessages(['quote' => 'El proyecto está cancelado.']);
            }
            if ($lockedQuote->status !== QuoteStatus::Sent) {
                throw ValidationException::withMessages(['quote' => 'Esta cotización ya no se puede aceptar.']);
            }
            if ($lockedQuote->isPastValidity()) {
                throw ValidationException::withMessages(['quote' => 'La vigencia de esta cotización terminó. Pide una versión actualizada a tu asesor.']);
            }

            $lockedQuote->update([
                'status' => QuoteStatus::Accepted,
                'accepted_at' => now(),
                'accepted_by' => $actor->id,
                'accepted_ip' => $ip,
            ]);

            Quote::query()
                ->where('project_id', $project->id)
                ->whereKeyNot($lockedQuote->id)
                ->whereIn('status', [QuoteStatus::Sent, QuoteStatus::Draft])
                ->update(['status' => QuoteStatus::Superseded]);

            $contractAmount = Money::of($lockedQuote->total);
            $rawPercent = (string) Settings::get('commercial.deposit_percent', '10.00');
            if (! is_numeric($rawPercent) || bccomp($rawPercent, '0', 2) < 0 || bccomp($rawPercent, '100', 2) > 0) {
                throw ValidationException::withMessages(['quote' => 'El porcentaje de anticipo configurado no es válido.']);
            }
            $depositPercent = bcadd($rawPercent, '0', 2);
            $depositAmount = $contractAmount->percent($depositPercent);

            $credit = $this->lockAvailableCredit($project);
            $creditAmount = $credit ? Money::of($credit->amount)->min($depositAmount)->max(Money::zero()) : Money::zero();
            $due = $depositAmount->minus($creditAmount)->max(Money::zero());

            /** @var Contract $contract */
            $contract = Contract::query()->create([
                'folio' => Folio::next('K'),
                'project_id' => $project->id,
                'quote_id' => $lockedQuote->id,
                'status' => ContractStatus::PendingDeposit,
                'contract_amount' => $contractAmount->amount,
                'deposit_percent' => $depositPercent,
                'deposit_amount' => $depositAmount->amount,
                'visit_credit_amount' => $creditAmount->amount,
                'deposit_due_amount' => $due->amount,
                'currency' => $lockedQuote->currency,
                'terms_snapshot' => $lockedQuote->terms,
                'signed_at' => now(),
                'created_by' => $actor->id,
            ]);
            $created = true;

            if ($credit) {
                $credit->update([
                    'status' => VisitCreditStatus::Applied,
                    'contract_id' => $contract->id,
                    'applied_at' => now(),
                    'applied_by' => $actor->id,
                ]);
            }

            $project->update(['contracted_at' => now()]);
            $this->quotes->advanceToQuoting($project, $actor);
            $this->workflow->transition($project, ProjectStatus::PendingDeposit, $actor, null, false);

            $byClient = ! $actor->isStaff();
            $this->workflow->record(
                $project,
                'quote_accepted',
                $byClient ? "Aceptaste la cotización {$lockedQuote->folio}" : "Cotización {$lockedQuote->folio} aceptada a nombre del cliente",
                $byClient ? null : $onBehalfReason,
                $actor,
                $byClient ? 'client' : 'internal',
                metadata: ['quote_id' => $lockedQuote->id, 'contract_id' => $contract->id],
            );
            $this->workflow->record(
                $project,
                'contract_created',
                'Tu contrato está listo',
                $due->isPositive() ? 'El siguiente paso es cubrir el anticipo para iniciar la fabricación.' : null,
                $actor,
                'client',
                metadata: ['contract_id' => $contract->id],
            );

            $this->logger->log('cotizacion.aceptada', $lockedQuote, [
                'contract' => $contract->folio,
                'on_behalf' => $byClient ? null : $onBehalfReason,
                'ip' => $ip,
            ]);
            $this->logger->log('contrato.creado', $contract, [
                'contract_amount' => $contract->contract_amount,
                'deposit_percent' => $contract->deposit_percent,
                'deposit_amount' => $contract->deposit_amount,
                'visit_credit_amount' => $contract->visit_credit_amount,
                'deposit_due_amount' => $contract->deposit_due_amount,
                'visit_credit_id' => $credit?->id,
            ]);

            if (! $due->isPositive()) {
                $this->activate($contract, $actor);
            }

            return $contract;
        });

        if ($created) {
            $this->afterAcceptance($contract, $actor);
        }

        return $contract;
    }

    /**
     * Re-evalúa el anticipo con los pagos confirmados. Idempotente: si ya
     * está activo no hace nada. Devuelve true si en esta llamada se activó.
     */
    public function syncDeposit(Contract $contract, ?User $actor = null): bool
    {
        $activated = DB::transaction(function () use ($contract, $actor) {
            /** @var Contract $locked */
            $locked = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === ContractStatus::Active || $locked->status === ContractStatus::Cancelled) {
                return false;
            }

            if ($this->paidDeposit($locked)->compare($locked->deposit_due_amount) < 0) {
                return false;
            }

            $this->activate($locked, $actor);

            return true;
        });

        if ($activated) {
            $contract->refresh();
            $this->notifier->notify($contract->project->customer, new DepositReceived($contract));
            $advisorId = $contract->project->advisor_id;
            if ($advisorId) {
                $this->notifier->notifyStaff(User::query()->find($advisorId), new DepositReceived($contract, internal: true));
            }
        }

        return $activated;
    }

    /**
     * Regla de reembolso de anticipo (documentada en docs/PROYECTOS.md):
     * nunca se borra nada ni se retrocede el proyecto automáticamente. Si el
     * neto pagado ya no cubre el anticipo de un contrato activo, el contrato
     * pasa a `deposit_refunded` y se avisa al equipo para decidir.
     */
    public function handleDepositRefund(Contract $contract): void
    {
        DB::transaction(function () use ($contract) {
            /** @var Contract $locked */
            $locked = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== ContractStatus::Active) {
                return;
            }
            if ($this->paidDeposit($locked)->compare($locked->deposit_due_amount) >= 0) {
                return;
            }

            $locked->update(['status' => ContractStatus::DepositRefunded]);
            $this->workflow->record(
                $locked->project,
                'deposit_refunded',
                'Anticipo reembolsado: revisar contrato',
                'El neto pagado ya no cubre el anticipo. Decide si se cobra de nuevo o se cancela el proyecto.',
                null,
                'internal',
                metadata: ['contract_id' => $locked->id],
            );
            $this->logger->log('contrato.anticipo_reembolsado', $locked, ['paid' => $this->paidDeposit($locked)->amount]);
        });
    }

    /** Neto confirmado de pagos de anticipo del contrato (pagado − reembolsado). */
    public function paidDeposit(Contract $contract): Money
    {
        $rows = Payment::query()
            ->where('contract_id', $contract->id)
            ->where('concept', PaymentConcept::Deposit->value)
            ->whereIn('status', [PaymentStatus::Paid->value, PaymentStatus::PartiallyRefunded->value])
            ->get(['amount', 'refunded_amount']);

        $sum = Money::zero();
        foreach ($rows as $row) {
            $sum = $sum->plus(Money::of((string) $row->amount)->minus((string) ($row->refunded_amount ?? '0')));
        }

        return $sum;
    }

    private function activate(Contract $contract, ?User $actor): void
    {
        $contract->update(['status' => ContractStatus::Active, 'deposit_paid_at' => $contract->deposit_paid_at ?? now()]);

        $project = Project::query()->findOrFail($contract->project_id);
        if ($project->status === ProjectStatus::PendingDeposit) {
            $this->workflow->transition($project, ProjectStatus::Authorized, $actor, 'Anticipo cubierto');
        }
        $title = Money::of($contract->deposit_due_amount)->isZero()
            ? 'Tu anticipo quedó cubierto con el crédito de tu visita'
            : 'Recibimos tu anticipo';
        $this->workflow->record($project, 'deposit_paid', $title, 'Tu proyecto entra a la cola de fabricación.', $actor, 'client', metadata: ['contract_id' => $contract->id]);
        $this->logger->log('contrato.activado', $contract, ['deposit_paid_at' => (string) $contract->deposit_paid_at]);

        if ($project->lead_id) {
            $lead = Lead::query()->find($project->lead_id);
            if ($lead) {
                app(LeadWorkflow::class)->transition($lead, LeadStatus::Won, $actor, "Contrato {$contract->folio} activo", force: true);
            }
        }
    }

    private function afterAcceptance(Contract $contract, User $actor): void
    {
        $contract->refresh();
        if ($contract->status === ContractStatus::PendingDeposit) {
            $this->notifier->notify($contract->project->customer, new ContractReady($contract));
        } else {
            $this->notifier->notify($contract->project->customer, new DepositReceived($contract));
        }
    }

    /**
     * Crédito de visita disponible y vigente (bloqueado). Solo de visitas del
     * mismo cliente ligadas a este proyecto o a su prospecto.
     */
    private function lockAvailableCredit(Project $project): ?VisitCredit
    {
        /** @var VisitCredit|null $credit */
        $credit = VisitCredit::query()
            ->where('status', VisitCreditStatus::Available->value)
            ->whereNull('contract_id')
            ->where(fn ($q) => $q->whereNull('expires_on')->orWhereDate('expires_on', '>=', now()->toDateString()))
            ->whereIn('site_visit_id', function (QueryBuilder $q) use ($project) {
                $q->select('id')->from('site_visits')
                    ->where('customer_id', $project->customer_id)
                    ->where(function (QueryBuilder $w) use ($project) {
                        $w->where('project_id', $project->id);
                        if ($project->lead_id) {
                            $w->orWhere('lead_id', $project->lead_id);
                        }
                    });
            })
            ->orderBy('expires_on')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        return $credit;
    }
}
