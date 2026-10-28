<?php

namespace App\Services\Crm;

use App\Enums\ConsultationChannel;
use App\Enums\ConsultationStatus;
use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Enums\PaymentConcept;
use App\Models\Consultation;
use App\Models\Payment;
use App\Notifications\Crm\When;
use App\Services\ActivityLogger;
use App\Services\Crm\Meetings\MeetingProvider;
use App\Services\Scheduling\BookingService;
use App\Services\Scheduling\SlotUnavailableException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reacción de crm a PaymentConfirmed (concepto consultation_fee):
 *
 *  - Confirma el horario apartado (BookingService::confirm). Si el apartado
 *    venció y otro tomó el horario, la asesoría queda "agendada" con
 *    attention_reason para que el equipo la reprograme: el dinero nunca se pierde.
 *  - Asesoría → confirmed, paid_at, sin apartado.
 *  - Crea la reunión (videollamada), avanza el prospecto a
 *    consultation_scheduled y envía los avisos existentes (ConsultationBooked
 *    con .ics al cliente, alerta al asesor).
 *  - Pago recibido de una asesoría ya cancelada (apartado vencido): no la
 *    revive; marca attention_reason para reembolso o reagenda.
 *
 * Idempotente: paid_at se fija una sola vez bajo bloqueo.
 */
class ConsultationPaymentHandler
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly LeadWorkflow $workflow,
        private readonly MeetingProvider $meetings,
        private readonly ActivityLogger $audit,
        private readonly ConsultationScheduler $scheduler,
    ) {}

    /** Asesoría vigente de un pago (el pago se hereda al reprogramar). */
    public static function consultationFor(Payment $payment): ?Consultation
    {
        return Consultation::query()
            ->where('payment_id', $payment->id)
            ->where('status', '!=', ConsultationStatus::Rescheduled->value)
            ->latest('id')
            ->first()
            ?? ($payment->consultation_id !== null ? Consultation::query()->find($payment->consultation_id) : null);
    }

    public function onPaymentConfirmed(Payment $payment): void
    {
        if ($payment->concept !== PaymentConcept::ConsultationFee) {
            return;
        }

        $current = self::consultationFor($payment);
        if ($current === null) {
            return;
        }

        $result = DB::transaction(function () use ($payment, $current) {
            /** @var Consultation|null $consultation */
            $consultation = Consultation::query()->whereKey($current->id)->lockForUpdate()->first();

            if ($consultation === null || $consultation->paid_at !== null) {
                return null;
            }

            $attention = null;
            $wasCancelled = $consultation->status === ConsultationStatus::Cancelled;
            $status = $consultation->status;

            if ($wasCancelled) {
                $attention = 'Se recibió el pago de una asesoría cancelada: reembolsa el pago o reagenda con el cliente.';
            } elseif ($consultation->status->isAwaitingPayment()) {
                $booking = $consultation->booking()->first();

                try {
                    if ($booking === null) {
                        throw new SlotUnavailableException;
                    }
                    $this->bookings->confirm($booking);
                    $status = ConsultationStatus::Confirmed;
                } catch (SlotUnavailableException) {
                    $attention = 'El apartado venció antes de confirmarse el pago y el horario ya no está libre: reprograma la asesoría con el cliente.';
                    $status = ConsultationStatus::Scheduled;
                }
            }

            $consultation->forceFill([
                'status' => $status,
                'paid_at' => $payment->paid_at ?? now(),
                'hold_expires_at' => null,
                'payment_id' => $consultation->payment_id ?? $payment->id,
                'attention_reason' => $attention,
            ])->save();

            $this->audit->log('asesoria.pagada', $consultation, [
                'folio' => $consultation->folio,
                'pago' => $payment->folio,
                'monto' => $payment->amount,
                'requiere_atencion' => $attention,
            ], $attention !== null ? 'warning' : 'success');

            return [$consultation, $wasCancelled, $attention];
        });

        if ($result === null) {
            return;
        }

        /** @var Consultation $consultation */
        [$consultation, $wasCancelled, $attention] = $result;
        $consultation->loadMissing(['lead.customer', 'advisor']);
        $lead = $consultation->lead;

        $this->workflow->record(
            $lead,
            LeadActivityType::ConsultationUpdated,
            "Pago de asesoría recibido ({$payment->folio})".($attention !== null ? ". {$attention}" : '. Asesoría confirmada para el '.When::long($consultation->starts_at).'.'),
            null,
            ['consultation_id' => $consultation->id, 'payment_id' => $payment->id],
        );

        if ($wasCancelled) {
            return;
        }

        if ($consultation->channel === ConsultationChannel::Video && $consultation->meeting_url === null) {
            try {
                $url = $this->meetings->create($consultation);
                if ($url !== null) {
                    $consultation->forceFill(['meeting_url' => $url])->save();
                }
            } catch (Throwable $e) {
                Log::warning('crm.consultation_meeting_failed', ['consultation' => $consultation->id, 'error' => $e->getMessage()]);
            }
        }

        // force: el sistema solo avanza (nunca regresa ni toca prospectos cerrados).
        $this->workflow->transition($lead, LeadStatus::ConsultationScheduled, null, 'Asesoría pagada', force: true);

        $this->scheduler->notifyBooked($consultation->fresh(['lead.customer', 'advisor']) ?? $consultation, $consultation->rescheduled_from_id !== null, null);
    }
}
