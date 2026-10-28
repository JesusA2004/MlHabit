<?php

namespace App\Http\Controllers\Public\Crm;

use App\Enums\ConsultationStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\BookConsultationRequest;
use App\Http\Requests\Payments\ChargeCardRequest;
use App\Models\Consultation;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Payment;
use App\Payments\ChargeResponse;
use App\Payments\Openpay\OpenpayConfig;
use App\Payments\PaymentException;
use App\Payments\PaymentService;
use App\Services\Crm\ConsultationCheckoutUrls;
use App\Services\Crm\ConsultationHoldExpirer;
use App\Services\Crm\ConsultationScheduler;
use App\Services\Crm\CustomerMatcher;
use App\Services\Scheduling\SlotUnavailableException;
use App\Services\Web\Seo;
use App\Support\Crm\CrmLinks;
use App\Support\Crm\Present;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Autoagenda de la asesoría remota (enlace firmado por prospecto), pago con
 * tarjeta (Openpay: toda asesoría tiene costo) y pantalla para ver el estado,
 * reprogramar o cancelar (enlace firmado por asesoría).
 *
 * Flujo: horario + medio → apartado (pending_payment, hold) + intención de
 * cobro → /asesoria/{uuid}/pago (tarjeta tokenizada en el navegador; aquí
 * solo llega el token) → PaymentConfirmed → asesoría confirmada.
 */
class ConsultationBookingController extends Controller
{
    public function __construct(
        private readonly ConsultationScheduler $scheduler,
        private readonly ConsultationHoldExpirer $expirer,
    ) {}

    public function create(Lead $lead): Response|RedirectResponse
    {
        $upcoming = $lead->consultations()->active()->where('starts_at', '>', now())->orderBy('starts_at')->first();
        if ($upcoming && $this->expirer->expire($upcoming)) {
            $upcoming = null;
        }
        if ($upcoming) {
            return redirect()->to($upcoming->status->isAwaitingPayment() ? ConsultationCheckoutUrls::pay($upcoming) : CrmLinks::manageConsultation($upcoming));
        }

        $lead->loadMissing('customer');
        $settings = $this->scheduler->settings();
        [$from, $until] = $this->scheduler->window();

        return Inertia::render('public/asesoria/Create', [
            'seo' => app(Seo::class)->private('Agenda tu asesoría'),
            'lead' => ['folio' => $lead->folio, 'firstName' => $lead->customer->first_name],
            'settings' => $settings->toArray(),
            'needsEmail' => blank($lead->customer->email),
            'window' => ['min' => $from->toDateString(), 'max' => $until->toDateString()],
            'availabilityUrl' => route('public.asesoria.disponibilidad'),
            'submitUrl' => URL::signedRoute('public.asesoria.store', ['lead' => $lead->uuid]),
        ]);
    }

    /** Fechas disponibles del mes (?mes=YYYY-MM) u horarios de un día (?fecha=YYYY-MM-DD). */
    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mes' => ['nullable', 'date_format:Y-m'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if (! empty($data['fecha'])) {
            $slots = $this->scheduler->slotsForDate(CarbonImmutable::parse($data['fecha']));

            return response()->json(['slots' => array_map(fn ($s) => ['value' => $s['starts_at'], 'time' => $s['time']], $slots)]);
        }

        $month = CarbonImmutable::parse(($data['mes'] ?? now()->format('Y-m')).'-01');

        return response()->json(['dates' => $this->scheduler->availableDates($month->year, $month->month)]);
    }

    /** Aparta el horario y crea la intención de cobro; continúa en la pantalla de pago. */
    public function store(BookConsultationRequest $request, Lead $lead): RedirectResponse
    {
        $this->ensureEmail($lead, $request->validated('email'));

        try {
            $consultation = $this->scheduler->book(
                $lead,
                $request->startsAt(),
                $request->channel() ?? throw ValidationException::withMessages(['channel' => 'Elige cómo prefieres tu asesoría.']),
                $request->validated('notes'),
            );
        } catch (SlotUnavailableException $e) {
            throw ValidationException::withMessages(['starts_at' => $e->getMessage()]);
        } catch (PaymentException $e) {
            throw ValidationException::withMessages(['starts_at' => $e->getMessage()]);
        }

        return redirect()->to(ConsultationCheckoutUrls::pay($consultation));
    }

    /** GET /asesoria/{uuid}/pago (firmado): resumen + formulario de tarjeta. */
    public function pay(Consultation $consultation, OpenpayConfig $openpay): Response|RedirectResponse
    {
        $this->expirer->expire($consultation);
        $consultation->refresh();
        $payment = $this->paymentOf($consultation);

        if ($payment === null || $payment->status->isSettled() || ! $consultation->status->isAwaitingPayment()) {
            return redirect()->to(CrmLinks::manageConsultation($consultation));
        }

        $consultation->loadMissing(['lead.customer', 'advisor']);

        return Inertia::render('public/asesoria/Pago', [
            'seo' => app(Seo::class)->private('Pago de tu asesoría'),
            'consultation' => $this->summary($consultation),
            'payment' => $this->paymentProps($payment),
            'openpay' => $openpay->jsConfig(),
            'chargeUrl' => ConsultationCheckoutUrls::charge($consultation),
            'manageUrl' => CrmLinks::manageConsultation($consultation),
            'restartUrl' => CrmLinks::bookConsultation($consultation->lead),
        ]);
    }

    /** POST /asesoria/{uuid}/pago (firmado) → JSON ChargeResponse para OpenpayCardForm. */
    public function charge(ChargeCardRequest $request, Consultation $consultation, PaymentService $payments): JsonResponse
    {
        $payment = $this->paymentOf($consultation);
        abort_if($payment === null, 404);

        $manage = CrmLinks::manageConsultation($consultation);

        if ($payment->status->isSettled()) {
            return response()->json(ChargeResponse::fromPayment($payment, $manage));
        }

        if ($consultation->status->isAwaitingPayment() && ! $consultation->holdIsActive() && $this->expirer->expire($consultation)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Tu apartado venció y el horario se liberó. No se realizó ningún cargo; vuelve a elegir fecha y hora.',
                'redirectUrl' => null,
                'nextUrl' => CrmLinks::bookConsultation($consultation->lead),
            ]);
        }

        $consultation->refresh();
        if (! $consultation->status->isAwaitingPayment()) {
            return response()->json(ChargeResponse::fromPayment($payment->fresh() ?? $payment, $manage));
        }

        try {
            $payment = $payments->chargeCard(
                $payment,
                (string) $request->validated('token_id'),
                (string) $request->validated('device_session_id'),
                $request,
                ConsultationCheckoutUrls::threeDsReturn($payment),
            );
        } catch (PaymentException $e) {
            return response()->json(ChargeResponse::fromException($e, $manage));
        }

        return response()->json(ChargeResponse::fromPayment($payment, $manage));
    }

    public function show(Consultation $consultation, PaymentService $payments): Response|RedirectResponse
    {
        // Si se reprogramó, se muestra la cita vigente.
        $latest = $this->latest($consultation);
        if ($latest->id !== $consultation->id) {
            return redirect()->to(CrmLinks::manageConsultation($latest));
        }

        $payment = $this->paymentOf($consultation);

        // Pago "en curso" que no se movió en un rato (3DS, red): se concilia aquí.
        if ($payment !== null && $payment->status === PaymentStatus::Processing && $payment->updated_at?->lt(now()->subSeconds(20))) {
            try {
                $payment = $payments->reconcile($payment);
            } catch (Throwable) {
                // Se reintenta en el siguiente sondeo.
            }
        }

        $this->expirer->expire($consultation);
        $consultation->refresh();
        $payment = $payment?->fresh();

        $consultation->loadMissing(['lead.customer', 'advisor']);
        $settings = $this->scheduler->settings();
        [$from, $until] = $this->scheduler->window();
        $awaiting = $consultation->status->isAwaitingPayment();
        $canChange = $consultation->status->isBooked() && $consultation->starts_at->isFuture();

        return Inertia::render('public/asesoria/Show', [
            'seo' => app(Seo::class)->private('Tu asesoría'),
            'consultation' => [
                ...Present::consultation($consultation),
                // El cliente solo ve el nombre de pila de su asesor.
                'advisor' => ['name' => explode(' ', trim($consultation->advisor->name))[0]],
                'durationMinutes' => (int) $consultation->starts_at->diffInMinutes($consultation->ends_at),
                'holdExpiresAt' => $awaiting ? $consultation->hold_expires_at?->toIso8601String() : null,
                'paidAt' => $consultation->paid_at?->toIso8601String(),
            ],
            'payment' => $payment ? $this->paymentProps($payment) : null,
            'payUrl' => $awaiting && $consultation->holdIsActive() && $payment !== null && ! $payment->status->isSettled() ? ConsultationCheckoutUrls::pay($consultation) : null,
            'receiptUrl' => $payment !== null && $payment->status->isSettled() ? ConsultationCheckoutUrls::receipt($payment) : null,
            'lead' => ['folio' => $consultation->lead->folio, 'firstName' => $consultation->lead->customer->first_name],
            'canChange' => $canChange,
            'canCancel' => $consultation->status->isActive() && $consultation->starts_at->isFuture(),
            'settings' => $settings->toArray(),
            'window' => ['min' => $from->toDateString(), 'max' => $until->toDateString()],
            'availabilityUrl' => route('public.asesoria.disponibilidad'),
            'rescheduleUrl' => URL::signedRoute('public.asesoria.reprogramar', ['consultation' => $consultation->uuid]),
            'cancelUrl' => URL::signedRoute('public.asesoria.cancelar', ['consultation' => $consultation->uuid]),
            'bookAgainUrl' => $consultation->status === ConsultationStatus::Cancelled && $settings->enabled ? CrmLinks::bookConsultation($consultation->lead) : null,
        ]);
    }

    public function reschedule(BookConsultationRequest $request, Consultation $consultation): RedirectResponse
    {
        if ($consultation->status->isAwaitingPayment()) {
            throw ValidationException::withMessages(['starts_at' => 'Primero completa el pago de tu asesoría; después podrás reprogramarla.']);
        }
        if (! $consultation->status->isActive() || ! $consultation->starts_at->isFuture()) {
            throw ValidationException::withMessages(['starts_at' => 'Esta asesoría ya no se puede reprogramar desde aquí. Escríbenos y te ayudamos.']);
        }

        try {
            $new = $this->scheduler->reschedule($consultation, $request->startsAt(), null, false, $request->channel());
        } catch (SlotUnavailableException $e) {
            throw ValidationException::withMessages(['starts_at' => $e->getMessage()]);
        }

        return redirect()->to(CrmLinks::manageConsultation($new))
            ->with('success', 'Reprogramamos tu asesoría. Te enviamos la nueva fecha por correo.');
    }

    public function cancel(Request $request, Consultation $consultation): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);

        if ($consultation->status->isActive() && ! $consultation->starts_at->isFuture()) {
            throw ValidationException::withMessages(['reason' => 'La asesoría ya comenzó; no es posible cancelarla desde aquí.']);
        }

        $wasPaid = $consultation->paid_at !== null;
        $this->scheduler->cancel($consultation, $data['reason'] ?? 'Cancelada por el cliente');

        return redirect()->to(CrmLinks::manageConsultation($consultation))
            ->with('success', $wasPaid
                ? 'Cancelamos tu asesoría. Un asesor te contactará para el reembolso según la política vigente.'
                : 'Cancelamos tu asesoría. No se realizó ningún cargo; cuando quieras, puedes agendar otra.');
    }

    /** Sin correo no hay comprobante ni cobro (Openpay lo exige): se pide al reservar. */
    private function ensureEmail(Lead $lead, mixed $email): void
    {
        /** @var Customer $customer */
        $customer = Customer::query()->withTrashed()->findOrFail($lead->customer_id);

        if (filled($customer->email)) {
            return;
        }

        $normalized = is_string($email) ? CustomerMatcher::normalizeEmail($email) : null;
        if ($normalized === null || $normalized === '') {
            throw ValidationException::withMessages(['email' => 'Escribe tu correo: ahí te enviamos la confirmación y el comprobante de pago.']);
        }

        $customer->forceFill(['email' => $normalized])->save();
    }

    private function paymentOf(Consultation $consultation): ?Payment
    {
        return $consultation->payment_id !== null ? Payment::query()->find($consultation->payment_id) : null;
    }

    /** @return array<string, mixed> */
    private function summary(Consultation $consultation): array
    {
        return [
            'uuid' => $consultation->uuid,
            'folio' => $consultation->folio,
            'startsAt' => $consultation->starts_at->toIso8601String(),
            'endsAt' => $consultation->ends_at->toIso8601String(),
            'durationMinutes' => (int) $consultation->starts_at->diffInMinutes($consultation->ends_at),
            'channel' => [
                'value' => $consultation->channel->value,
                'label' => $consultation->channel->label(),
                'description' => $consultation->channel->description(),
            ],
            'advisorFirstName' => explode(' ', trim($consultation->advisor->name))[0],
            'customerFirstName' => $consultation->lead->customer->first_name,
            'holdExpiresAt' => $consultation->hold_expires_at?->toIso8601String(),
            'price' => $consultation->price,
        ];
    }

    /** @return array<string, mixed> */
    private function paymentProps(Payment $payment): array
    {
        return [
            'uuid' => $payment->uuid,
            'folio' => $payment->folio,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'description' => $payment->description,
            'status' => $payment->status->toArray(),
            'cardBrand' => $payment->card_brand,
            'cardLast4' => $payment->card_last4,
            'paidAt' => $payment->paid_at?->toIso8601String(),
            'errorMessage' => $payment->status === PaymentStatus::Failed ? $payment->error_message : null,
        ];
    }

    private function latest(Consultation $consultation): Consultation
    {
        $current = $consultation;
        for ($i = 0; $i < 20 && $current->status === ConsultationStatus::Rescheduled; $i++) {
            $next = $current->rescheduledTo()->first();
            if (! $next) {
                break;
            }
            $current = $next;
        }

        return $current;
    }
}
