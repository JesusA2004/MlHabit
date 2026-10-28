<?php

namespace App\Services\Visits;

use App\Enums\LeadStatus;
use App\Enums\PaymentConcept;
use App\Enums\PaymentStatus;
use App\Enums\VisitCreditStatus;
use App\Enums\VisitStatus;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\SiteVisit;
use App\Models\VisitCredit;
use App\Services\ActivityLogger;
use App\Services\Scheduling\BookingService;
use App\Services\Scheduling\SlotUnavailableException;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Reacciones del módulo de visitas a los eventos de pago (concepto visit_fee):
 *
 *  - PaymentConfirmed → confirma el horario (si el apartado venció y otro lo
 *    tomó, la visita queda "requiere atención" y se avisa al equipo: el
 *    dinero nunca se pierde), visita → scheduled, crea el VisitCredit si la
 *    política congelada es acreditable, prospecto → visit_paid y avisos.
 *  - PaymentRefunded → anula/ajusta el crédito si NO se ha aplicado. Si ya
 *    se aplicó a un contrato NO se toca el contrato: se registra en la
 *    bitácora y se avisa a finanzas para que lo resuelvan.
 *
 * Todo es idempotente (paid_at / site_visit_id UNIQUE en visit_credits).
 */
class VisitPaymentHandler
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly LeadBridge $leads,
        private readonly ActivityLogger $audit,
        private readonly VisitNotifier $notifier,
    ) {}

    public function onPaymentConfirmed(Payment $payment): void
    {
        if ($payment->concept !== PaymentConcept::VisitFee || $payment->site_visit_id === null) {
            return;
        }

        $result = DB::transaction(function () use ($payment) {
            /** @var SiteVisit|null $visit */
            $visit = SiteVisit::query()->whereKey($payment->site_visit_id)->lockForUpdate()->first();

            if ($visit === null || $visit->paid_at !== null) {
                return null;
            }

            $attention = null;
            $wasCancelled = $visit->status === VisitStatus::Cancelled;
            $booking = $visit->latestBooking();

            if ($wasCancelled) {
                $attention = 'Se recibió el pago de una visita cancelada: revisa el reembolso o reprograma con el cliente.';
            } elseif ($booking === null) {
                $attention = 'Pago recibido sin horario apartado: reprograma la visita con el cliente.';
            } else {
                try {
                    $this->bookings->confirm($booking);
                } catch (SlotUnavailableException) {
                    $attention = 'El apartado venció antes de confirmarse el pago y el horario ya lo tomó otra reserva: reprograma con el cliente.';
                }
            }

            $visit->forceFill([
                'status' => $wasCancelled ? VisitStatus::Cancelled : VisitStatus::Scheduled,
                'paid_at' => $payment->paid_at ?? now(),
                'hold_expires_at' => null,
                'attention_reason' => $attention,
                'attention_at' => $attention !== null ? now() : null,
            ])->save();

            if (! $wasCancelled && $visit->is_creditable && $visit->creditMoney()->isPositive()) {
                $days = $visit->policy->credit_validity_days;

                VisitCredit::query()->firstOrCreate(['site_visit_id' => $visit->id], [
                    'payment_id' => $payment->id,
                    'amount' => $visit->creditMoney()->min($visit->feeMoney())->amount,
                    'status' => VisitCreditStatus::Available,
                    'expires_on' => $days ? now()->addDays($days)->toDateString() : null,
                ]);
            }

            $this->audit->log('visita.pagada', $visit, [
                'folio' => $visit->folio,
                'pago' => $payment->folio,
                'monto' => $payment->amount,
                'requiere_atencion' => $attention,
            ]);

            return [$visit, $attention];
        });

        if ($result === null) {
            return;
        }

        [$visit, $attention] = $result;

        $this->leads->advance($visit->lead_id ? Lead::query()->find($visit->lead_id) : null, LeadStatus::VisitPaid, null, "Visita {$visit->folio} pagada ({$payment->folio}).");

        $this->notifier->visitPaid($visit->fresh() ?? $visit, $attention);
    }

    /** Refleja en la visita si su pago está en curso (3DS/conciliación) o de vuelta en espera. */
    public function syncAwaitingStatus(SiteVisit $visit, Payment $payment): void
    {
        $target = $payment->status === PaymentStatus::Processing ? VisitStatus::PaymentProcessing : VisitStatus::PendingPayment;

        SiteVisit::query()
            ->whereKey($visit->id)
            ->whereIn('status', [VisitStatus::PendingPayment->value, VisitStatus::PaymentProcessing->value])
            ->where('status', '!=', $target->value)
            ->update(['status' => $target->value, 'updated_at' => now()]);
    }

    public function onPaymentRefunded(Payment $payment, Refund $refund): void
    {
        if ($payment->concept !== PaymentConcept::VisitFee || $payment->site_visit_id === null) {
            return;
        }

        $flag = DB::transaction(function () use ($payment, $refund) {
            /** @var VisitCredit|null $credit */
            $credit = VisitCredit::query()->where('site_visit_id', $payment->site_visit_id)->lockForUpdate()->first();

            if ($credit === null) {
                return null;
            }

            $netPaid = Money::of($payment->amount)->minus($payment->refunded_amount)->max('0');

            if ($credit->status === VisitCreditStatus::Available) {
                if (! $netPaid->isPositive()) {
                    $credit->forceFill([
                        'status' => VisitCreditStatus::Voided,
                        'voided_at' => now(),
                        'void_reason' => 'Reembolso de la visita ('.$payment->folio.').',
                    ])->save();
                    $this->audit->log('visita.credito_anulado', $credit, ['pago' => $payment->folio, 'reembolso' => $refund->uuid]);
                } elseif ($credit->amountMoney()->compare($netPaid) > 0) {
                    // El crédito nunca puede exceder lo que el cliente pagó en realidad.
                    $before = $credit->amount;
                    $credit->forceFill(['amount' => $netPaid->amount])->save();
                    $this->audit->log('visita.credito_ajustado', $credit, ['before' => ['amount' => $before], 'after' => ['amount' => $netPaid->amount], 'pago' => $payment->folio]);
                }

                return null;
            }

            if ($credit->status === VisitCreditStatus::Applied) {
                $this->audit->log('visita.credito_aplicado_reembolsado', $credit, [
                    'pago' => $payment->folio,
                    'contrato' => $credit->contract_id,
                    'credito' => $credit->amount,
                    'reembolso' => $refund->amount,
                    'accion' => 'El crédito ya se aplicó a un contrato: finanzas debe ajustar el saldo del anticipo manualmente.',
                ], 'warning');

                return $credit;
            }

            return null;
        });

        if ($flag instanceof VisitCredit) {
            $this->notifier->creditRefundReview($flag, $payment, $refund);
        }
    }
}
