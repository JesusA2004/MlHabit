<?php

namespace App\Http\Controllers\Public\Visits;

use App\Enums\PaymentConcept;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\ChargeCardRequest;
use App\Http\Requests\Visits\BookVisitRequest;
use App\Models\Address;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\SiteVisit;
use App\Models\VisitPolicy;
use App\Payments\ChargeResponse;
use App\Payments\Openpay\OpenpayConfig;
use App\Payments\PaymentException;
use App\Payments\PaymentPresenter;
use App\Payments\PaymentService;
use App\Services\CoverageService;
use App\Services\PublicSiteDataService;
use App\Services\Scheduling\SlotUnavailableException;
use App\Services\Visits\VisitAvailability;
use App\Services\Visits\VisitBookingException;
use App\Services\Visits\VisitBookingService;
use App\Services\Visits\VisitCheckoutUrls;
use App\Services\Visits\VisitHoldExpirer;
use App\Services\Visits\VisitNotifier;
use App\Services\Visits\VisitPaymentHandler;
use App\Services\Visits\VisitPresenter;
use App\Services\Web\Seo;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Checkout público de la visita técnica:
 * CP → fecha/horario → domicilio/contacto → revisión → pago (Openpay).
 */
class VisitCheckoutController extends Controller
{
    public function __construct(
        private readonly CoverageService $coverage,
        private readonly VisitAvailability $availability,
    ) {}

    /** GET /agendar-visita (flujo abierto). */
    public function open(): Response
    {
        return $this->render(null);
    }

    /** GET /agendar-visita/{lead:uuid} (firmado; datos precargados del prospecto). */
    public function forLead(Lead $lead): Response
    {
        return $this->render($lead);
    }

    /** POST /agendar-visita/cobertura — ¿damos visitas en este CP? + fechas disponibles. */
    public function coverage(Request $request): JsonResponse
    {
        $data = $request->validate(['postal_code' => ['required', 'digits:5']], ['postal_code.digits' => 'El código postal tiene 5 dígitos.']);
        $policy = VisitPolicy::current();

        if ($policy === null) {
            return response()->json(['covered' => false, 'closed' => true, 'message' => 'Por ahora agenda tu visita con un asesor.', 'zone' => null, 'dates' => []]);
        }

        $postal = (string) $data['postal_code'];
        $zone = $this->coverage->zoneForPostalCode($postal);

        if ($zone === null || ! $this->coverage->acceptsVisits($postal)) {
            return response()->json([
                'covered' => false,
                'closed' => false,
                'message' => 'Todavía no damos visitas técnicas en el C.P. '.$postal.'. Déjanos tus datos y un asesor te ayuda con otras opciones.',
                'zone' => null,
                'dates' => [],
            ]);
        }

        $dates = $this->availability->availableDates($zone, $policy);

        return response()->json([
            'covered' => true,
            'closed' => false,
            'message' => $dates === [] ? 'Por ahora no hay horarios disponibles en tu zona. Un asesor puede agendarte en cuanto se libere un espacio.' : null,
            'zone' => ['name' => $zone->name, 'municipality' => $zone->municipality, 'state' => $zone->state],
            'dates' => $dates,
        ]);
    }

    /** GET /agendar-visita/horarios?cp=&fecha= — ventanas libres de un día. */
    public function slots(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cp' => ['required', 'digits:5'],
            'fecha' => ['required', 'date_format:Y-m-d'],
        ]);

        $policy = VisitPolicy::current();
        $zone = $this->coverage->zoneForPostalCode((string) $data['cp']);

        if ($policy === null || $zone === null || ! $this->coverage->acceptsVisits((string) $data['cp'])) {
            return response()->json(['slots' => []]);
        }

        $slots = $this->availability->slotsForDay($zone, $policy, CarbonImmutable::parse($data['fecha']));

        return response()->json([
            'slots' => array_map(fn (array $s) => ['start' => $s['start'], 'end' => $s['end']], $slots),
        ]);
    }

    /** POST /agendar-visita — aparta el horario y crea la intención de cobro. */
    public function store(BookVisitRequest $request, VisitBookingService $booking): RedirectResponse
    {
        $data = $request->validated();
        $lead = $this->leadFromToken($data['lead_token'] ?? null);

        try {
            $visit = $booking->book($data, (string) $data['idempotency_key'], $lead);
        } catch (SlotUnavailableException $e) {
            throw ValidationException::withMessages(['start' => $e->getMessage()]);
        } catch (VisitBookingException $e) {
            throw ValidationException::withMessages([$e->reason === VisitBookingException::NO_COVERAGE ? 'postal_code' : 'form' => $e->getMessage()]);
        } catch (PaymentException $e) {
            throw ValidationException::withMessages(['form' => $e->getMessage()]);
        }

        // Toda visita se cobra: la reserva siempre crea su intención de pago.
        $payment = $visit->payment()->firstOrFail();

        return redirect()->to(VisitCheckoutUrls::pay($payment));
    }

    /** GET /agendar-visita/pago/{payment} (firmado). */
    public function pay(Payment $payment, OpenpayConfig $openpay, VisitHoldExpirer $expirer): Response|RedirectResponse
    {
        $visit = $this->visitFor($payment);
        $expirer->expire($visit);
        $visit->refresh();
        $payment->refresh();

        if ($payment->status->isSettled() || ! $visit->status->isAwaitingPayment()) {
            return redirect()->to(VisitCheckoutUrls::status($payment));
        }

        return Inertia::render('public/visita/Pago', [
            'seo' => app(Seo::class)->private('Pago de la visita técnica'),
            ...$this->publicBase(),
            'visit' => VisitPresenter::forCustomer($visit),
            'payment' => $this->paymentProps($payment),
            'openpay' => $openpay->jsConfig(),
            'chargeUrl' => VisitCheckoutUrls::charge($payment),
            'statusUrl' => VisitCheckoutUrls::status($payment),
            'restartUrl' => route('public.visita.abierta'),
        ]);
    }

    /** POST /agendar-visita/pago/{payment}/cobrar (firmado) → JSON ChargeResponse. */
    public function charge(ChargeCardRequest $request, Payment $payment, PaymentService $payments, VisitHoldExpirer $expirer, VisitPaymentHandler $handler): JsonResponse
    {
        $visit = $this->visitFor($payment);
        $statusUrl = VisitCheckoutUrls::status($payment);

        if ($payment->status->isSettled()) {
            return response()->json(ChargeResponse::fromPayment($payment, $statusUrl));
        }

        if ($visit->status->isAwaitingPayment() && ! $visit->holdIsActive() && $expirer->expire($visit)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Tu apartado venció y el horario se liberó. No se realizó ningún cargo; vuelve a elegir fecha y hora.',
                'redirectUrl' => null,
                'nextUrl' => route('public.visita.abierta'),
            ]);
        }

        $visit->refresh();
        if (! $visit->status->isAwaitingPayment()) {
            return response()->json(ChargeResponse::fromPayment($payment->fresh() ?? $payment, $statusUrl));
        }

        try {
            $payment = $payments->chargeCard(
                $payment,
                (string) $request->validated('token_id'),
                (string) $request->validated('device_session_id'),
                $request,
                VisitCheckoutUrls::threeDsReturn($payment),
            );
        } catch (PaymentException $e) {
            return response()->json(ChargeResponse::fromException($e, $statusUrl));
        }

        $handler->syncAwaitingStatus($visit, $payment);

        return response()->json(ChargeResponse::fromPayment($payment, $statusUrl));
    }

    /** GET /agendar-visita/pago/{payment}/retorno — regreso de 3D Secure: concilia por API. */
    public function threeDsReturn(Payment $payment, PaymentService $payments, VisitPaymentHandler $handler): RedirectResponse
    {
        $visit = $this->visitFor($payment);

        try {
            $payment = $payments->reconcile($payment);
        } catch (Throwable) {
            // El estado se mostrará como "confirmando" y el comando lo conciliará.
        }

        $handler->syncAwaitingStatus($visit, $payment);

        return redirect()->to(VisitCheckoutUrls::status($payment));
    }

    /** GET /agendar-visita/pago/{payment}/estado (firmado) — nunca muestra éxito sin confirmación del backend. */
    public function status(Payment $payment, PaymentService $payments, VisitNotifier $notifier): Response
    {
        $visit = $this->visitFor($payment);

        if ($payment->status->value === 'processing' && $payment->updated_at?->lt(now()->subSeconds(20))) {
            try {
                $payment = $payments->reconcile($payment);
                $visit->refresh();
            } catch (Throwable) {
                // Se reintenta en el siguiente sondeo.
            }
        }

        return Inertia::render('public/visita/Estado', [
            'seo' => app(Seo::class)->private('Estado de tu visita técnica'),
            ...$this->publicBase(),
            'visit' => VisitPresenter::forCustomer($visit->fresh(['address', 'zone', 'technician', 'policy', 'customer']) ?? $visit),
            'payment' => $this->paymentProps($payment),
            'receiptUrl' => $payment->status->isSettled() ? $notifier->receiptUrl($payment) : null,
            'payUrl' => $visit->status->isAwaitingPayment() && $visit->holdIsActive() && ! $payment->status->isSettled() ? VisitCheckoutUrls::pay($payment) : null,
            'restartUrl' => route('public.visita.abierta'),
        ]);
    }

    private function render(?Lead $lead): Response
    {
        $policy = VisitPolicy::current();

        return Inertia::render('public/visita/Agendar', [
            'seo' => app(Seo::class)->private('Agenda tu visita técnica'),
            ...$this->publicBase(),
            'policy' => $policy ? VisitPresenter::policy($policy) : null,
            'prefill' => $lead ? $this->prefill($lead) : null,
            'urls' => [
                'coverage' => route('public.visita.cobertura'),
                'slots' => route('public.visita.horarios'),
                'store' => route('public.visita.reservar'),
                'contact' => url('/solicitar'),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function prefill(Lead $lead): array
    {
        /** @var Customer $customer */
        $customer = Customer::query()->withTrashed()->findOrFail($lead->customer_id);
        /** @var Address|null $address */
        $address = Address::query()->where('customer_id', $customer->id)->latest('id')->first();

        return [
            'leadToken' => Crypt::encryptString($lead->uuid.'|'.now()->addDay()->timestamp),
            'leadFolio' => $lead->folio,
            'firstName' => $customer->first_name,
            'lastName' => $customer->last_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'postalCode' => $lead->postal_code ?? $address?->postal_code,
            'address' => $address ? [
                'street' => $address->street,
                'exteriorNumber' => $address->exterior_number,
                'interiorNumber' => $address->interior_number,
                'neighborhood' => $address->neighborhood,
                'municipality' => $address->municipality,
                'state' => $address->state,
                'references' => $address->references,
            ] : null,
        ];
    }

    private function leadFromToken(?string $token): ?Lead
    {
        if ($token === null || $token === '') {
            return null;
        }

        try {
            [$uuid, $expires] = explode('|', Crypt::decryptString($token), 2) + [null, null];
        } catch (DecryptException) {
            throw ValidationException::withMessages(['form' => 'El enlace de tu asesor no es válido. Vuelve a abrirlo desde el correo o agenda desde el sitio.']);
        }

        if ((int) $expires < now()->timestamp) {
            throw ValidationException::withMessages(['form' => 'El enlace de tu asesor expiró. Vuelve a abrirlo desde el correo.']);
        }

        return Lead::query()->where('uuid', $uuid)->first();
    }

    private function visitFor(Payment $payment): SiteVisit
    {
        abort_unless($payment->concept === PaymentConcept::VisitFee && $payment->site_visit_id !== null, 404);

        return SiteVisit::query()->with(['address', 'zone', 'technician', 'policy', 'customer'])->findOrFail($payment->site_visit_id);
    }

    /** @return array<string, mixed> */
    private function paymentProps(Payment $payment): array
    {
        $summary = PaymentPresenter::summary($payment);

        return [
            'uuid' => $summary['uuid'],
            'folio' => $summary['folio'],
            'amount' => $summary['amount'],
            'currency' => $summary['currency'],
            'description' => $summary['description'],
            'status' => $summary['status'],
            'cardBrand' => $summary['cardBrand'],
            'cardLast4' => $summary['cardLast4'],
            'paidAt' => $summary['paidAt'],
            'errorMessage' => $payment->status->value === 'failed' ? $payment->error_message : null,
        ];
    }

    /** @return array<string, mixed> */
    private function publicBase(): array
    {
        try {
            return app(PublicSiteDataService::class)->base();
        } catch (Throwable) {
            return ['site' => ['name' => 'ML Habitat']];
        }
    }
}
