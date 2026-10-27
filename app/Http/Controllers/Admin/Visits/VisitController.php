<?php

namespace App\Http\Controllers\Admin\Visits;

use App\Enums\PaymentMethod;
use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\RecordManualPaymentRequest;
use App\Http\Requests\Visits\CancelVisitRequest;
use App\Http\Requests\Visits\RescheduleVisitRequest;
use App\Http\Requests\Visits\StoreAdminVisitRequest;
use App\Models\ActivityLog;
use App\Models\Address;
use App\Models\CoverageZone;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Project;
use App\Models\SiteVisit;
use App\Models\User;
use App\Models\VisitPolicy;
use App\Payments\PaymentException;
use App\Payments\PaymentPresenter;
use App\Payments\PaymentService;
use App\Services\ActivityLogger;
use App\Services\CoverageService;
use App\Services\Scheduling\SlotUnavailableException;
use App\Services\Visits\VisitAvailability;
use App\Services\Visits\VisitBookingException;
use App\Services\Visits\VisitBookingService;
use App\Services\Visits\VisitCheckoutUrls;
use App\Services\Visits\VisitNotifier;
use App\Services\Visits\VisitPresenter;
use App\Services\Visits\VisitWorkflow;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** /admin/visitas — agenda de visitas técnicas. */
class VisitController extends Controller
{
    /** Horas que el personal aparta un horario al enviar un enlace de pago. */
    public const STAFF_LINK_HOLD_HOURS = 24;

    private const TABS = ['hoy', 'proximas', 'pendientes', 'atencion', 'realizadas', 'todas'];

    private const ACTION_LABELS = [
        'visita.apartada' => 'Horario apartado',
        'visita.pagada' => 'Visita pagada',
        'visita.reprogramada' => 'Visita reprogramada',
        'visita.tecnico_asignado' => 'Técnico asignado',
        'visita.cancelada' => 'Visita cancelada',
        'visita.apartado_vencido' => 'Apartado vencido',
        'visita.confirmada' => 'Confirmada con el cliente',
        'visita.iniciada' => 'Visita iniciada',
        'visita.realizada' => 'Visita realizada',
        'visita.cliente_ausente' => 'Cliente ausente',
        'visita.enlace_pago' => 'Enlace de pago enviado',
    ];

    public function __construct(
        private readonly CoverageService $coverage,
        private readonly VisitAvailability $availability,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', SiteVisit::class);

        $tab = in_array($request->query('vista'), self::TABS, true) ? (string) $request->query('vista') : 'proximas';
        $filters = $request->only(['zona', 'tecnico', 'estado', 'q']);

        $query = SiteVisit::query()
            ->with(['customer', 'zone', 'technician'])
            ->tap(fn (Builder $q) => $this->applyTab($q, $tab))
            ->when((int) ($filters['zona'] ?? 0), fn ($q, $id) => $q->where('coverage_zone_id', $id))
            ->when((int) ($filters['tecnico'] ?? 0), fn ($q, $id) => $q->where('technician_id', $id))
            ->when(VisitStatus::tryFrom((string) ($filters['estado'] ?? '')), fn ($q, $s) => $q->where('status', $s->value))
            ->when(trim((string) ($filters['q'] ?? '')), function ($q, $term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $term).'%';
                $q->where(fn ($w) => $w->where('folio', 'like', $like)
                    ->orWhereHas('customer', fn ($c) => $c->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('phone', 'like', $like)));
            });

        $query = in_array($tab, ['realizadas', 'todas'], true) ? $query->orderByDesc('starts_at') : $query->orderBy('starts_at');

        $counts = [];
        foreach (self::TABS as $t) {
            $counts[$t] = SiteVisit::query()->tap(fn (Builder $q) => $this->applyTab($q, $t))->count();
        }

        return Inertia::render('admin/visitas/Index', [
            'visits' => $query->paginate(25)->withQueryString()->through(fn (SiteVisit $v) => VisitPresenter::row($v)),
            'tab' => $tab,
            'counts' => $counts,
            'filters' => $filters,
            'options' => [
                'zones' => CoverageZone::query()->orderBy('name')->get(['id', 'name'])->map(fn ($z) => ['value' => (string) $z->id, 'label' => $z->name])->values(),
                'technicians' => User::query()->whereHas('coverageZones')->where('is_active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['value' => (string) $u->id, 'label' => $u->name])->values(),
                'statuses' => VisitStatus::options(),
            ],
            'can' => [
                'create' => $request->user()?->can('create', SiteVisit::class) ?? false,
                'policy' => $request->user()?->can('create', VisitPolicy::class) ?? false,
            ],
            'hasActivePolicy' => VisitPolicy::current() !== null,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', SiteVisit::class);

        $lead = null;
        if (is_string($request->query('prospecto')) && class_exists(Lead::class)) {
            $lead = Lead::query()->where('uuid', $request->query('prospecto'))->first();
        }

        $prefill = null;
        if ($lead !== null) {
            /** @var Customer $customer */
            $customer = Customer::query()->withTrashed()->findOrFail($lead->customer_id);
            $address = Address::query()->where('customer_id', $customer->id)->latest('id')->first();
            $prefill = [
                'lead' => $lead->uuid,
                'leadFolio' => $lead->folio,
                'firstName' => $customer->first_name,
                'lastName' => $customer->last_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'postalCode' => $lead->postal_code ?? $address?->postal_code,
                'address' => $address ? VisitPresenter::address($address) : null,
            ];
        }

        $policy = VisitPolicy::current();

        return Inertia::render('admin/visitas/Crear', [
            'policy' => $policy ? VisitPresenter::policy($policy) : null,
            'prefill' => $prefill,
            'manualMethods' => array_map(fn (PaymentMethod $m) => $m->toArray(), PaymentMethod::manualCases()),
            'can' => ['record' => $request->user()?->hasPermission('payments.record') ?? false],
            'holdHours' => self::STAFF_LINK_HOLD_HOURS,
        ]);
    }

    /** GET /admin/visitas/disponibilidad?cp=&fecha=&tecnico=&visita= */
    public function availability(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', SiteVisit::class);

        $data = $request->validate([
            'cp' => ['required', 'digits:5'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'tecnico' => ['nullable', 'integer'],
            'visita' => ['nullable', 'uuid'],
        ]);

        $policy = VisitPolicy::current();
        $zone = $this->coverage->zoneForPostalCode((string) $data['cp']);

        if ($policy === null || $zone === null || ! $this->coverage->acceptsVisits((string) $data['cp'])) {
            return response()->json([
                'covered' => false,
                'message' => $policy === null ? 'No hay una política de visita activa.' : 'El código postal no tiene cobertura de visitas.',
                'zone' => null, 'dates' => [], 'slots' => [], 'technicians' => [],
            ]);
        }

        $ignore = isset($data['visita']) ? SiteVisit::query()->where('uuid', $data['visita'])->value('id') : null;
        $technician = isset($data['tecnico']) ? (int) $data['tecnico'] : null;

        return response()->json([
            'covered' => true,
            'message' => null,
            'zone' => ['id' => $zone->id, 'name' => $zone->name],
            'dates' => isset($data['fecha']) ? [] : $this->availability->availableDates($zone, $policy),
            'slots' => isset($data['fecha'])
                ? array_map(fn (array $s) => ['start' => $s['start'], 'end' => $s['end'], 'technicians' => count($s['technicianIds'])], $this->availability->slotsForDay($zone, $policy, CarbonImmutable::parse($data['fecha']), $technician, $ignore ? (int) $ignore : null))
                : [],
            'technicians' => $this->availability->technicians($zone)->map(fn (User $u) => ['value' => (string) $u->id, 'label' => $u->name])->values(),
        ]);
    }

    public function store(StoreAdminVisitRequest $request, VisitBookingService $booking, PaymentService $payments, VisitNotifier $notifier): RedirectResponse
    {
        Gate::authorize('create', SiteVisit::class);

        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validated();
        $manual = $data['payment_action'] === 'manual';

        if ($manual && ! $actor->hasPermission('payments.record')) {
            throw ValidationException::withMessages(['payment_action' => 'No tienes permiso para registrar pagos manuales.']);
        }

        $lead = isset($data['lead']) && class_exists(Lead::class) ? Lead::query()->where('uuid', $data['lead'])->first() : null;

        try {
            $visit = $booking->book(
                $data,
                (string) $data['idempotency_key'],
                $lead,
                $actor,
                CarbonImmutable::now()->addHours(self::STAFF_LINK_HOLD_HOURS),
                isset($data['technician_id']) ? (int) $data['technician_id'] : null,
            );

            $payment = $visit->payment()->first();

            if ($payment !== null && $manual) {
                $payments->recordManual($payment, (string) $data['method'], (string) $data['reference'], $actor);
            } elseif ($payment !== null) {
                $notifier->paymentLink($visit, VisitCheckoutUrls::pay($payment));
            }
        } catch (SlotUnavailableException $e) {
            throw ValidationException::withMessages(['start' => $e->getMessage()]);
        } catch (VisitBookingException|PaymentException $e) {
            throw ValidationException::withMessages(['form' => $e->getMessage()]);
        }

        return redirect()->route('admin.visitas.show', $visit)->with('success', $manual
            ? "Visita {$visit->folio} agendada con pago registrado."
            : "Visita {$visit->folio} apartada. Enviamos el enlace de pago al cliente.");
    }

    public function show(Request $request, SiteVisit $visit): Response
    {
        Gate::authorize('view', $visit);

        /** @var User $user */
        $user = $request->user();

        $visit->load(['customer', 'address', 'zone', 'technician', 'policy', 'credit', 'payment', 'creator']);
        $payment = $visit->payment;
        $lead = $visit->lead_id && class_exists(Lead::class) ? Lead::query()->find($visit->lead_id) : null;

        $logs = ActivityLog::query()->with('user')
            ->where('subject_type', SiteVisit::class)->where('subject_id', $visit->id)
            ->latest('id')->limit(50)->get();

        return Inertia::render('admin/visitas/Show', [
            'visit' => [
                ...VisitPresenter::row($visit),
                'customer' => [
                    'name' => $visit->customer->full_name,
                    'email' => $visit->customer->email,
                    'phone' => $visit->customer->phone,
                ],
                'address' => VisitPresenter::address($visit->address),
                'technicianId' => $visit->technician_id,
                'accessNotes' => $visit->access_notes,
                'customerNotes' => $visit->customer_notes,
                'internalNotes' => $visit->internal_notes,
                'paidAt' => $visit->paid_at?->toIso8601String(),
                'startedAt' => $visit->started_at?->toIso8601String(),
                'completedAt' => $visit->completed_at?->toIso8601String(),
                'cancelledAt' => $visit->cancelled_at?->toIso8601String(),
                'cancelReason' => $visit->cancel_reason,
                'attentionAt' => $visit->attention_at?->toIso8601String(),
                'isCreditable' => $visit->is_creditable,
                'creditAmount' => $visit->credit_amount,
                'policyVersion' => $visit->policy->version,
                'durationMinutes' => (int) $visit->starts_at->diffInMinutes($visit->ends_at),
                'postalCode' => $visit->address->postal_code,
                'createdBy' => $visit->creator?->name,
                'lead' => $lead ? ['folio' => $lead->folio, 'url' => url('/admin/prospectos/'.$lead->uuid)] : null,
                'project' => $visit->project_id ? ['url' => url('/admin/proyectos/'.(Project::query()->whereKey($visit->project_id)->value('uuid') ?? ''))] : null,
            ],
            'payment' => $payment ? [
                ...PaymentPresenter::summary($payment),
                'adminUrl' => $user->can('view', $payment) ? route('admin.pagos.show', $payment) : null,
                'receiptUrl' => $payment->status->isSettled() ? URL::signedRoute('pagos.comprobante', ['payment' => $payment->uuid]) : null,
                'payUrl' => $visit->status->isAwaitingPayment() && $visit->holdIsActive() ? VisitCheckoutUrls::pay($payment) : null,
            ] : null,
            'credit' => $visit->credit ? [
                'amount' => $visit->credit->amount,
                'status' => $visit->credit->status->toArray(),
                'expiresOn' => $visit->credit->expires_on?->toDateString(),
                'voidReason' => $visit->credit->void_reason,
            ] : null,
            'timeline' => $logs->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'title' => self::ACTION_LABELS[$log->action] ?? $log->action,
                'description' => null,
                'date' => $log->created_at?->toIso8601String(),
                'actor' => $log->user->name ?? 'Sistema',
                'tone' => match ($log->action) {
                    'visita.pagada', 'visita.realizada' => 'success',
                    'visita.cancelada', 'visita.cliente_ausente' => 'danger',
                    'visita.apartado_vencido' => 'warning',
                    default => 'neutral',
                },
            ])->values(),
            'technicians' => $this->availability->technicians($visit->zone)->map(fn (User $u) => ['value' => (string) $u->id, 'label' => $u->name])->values(),
            'manualMethods' => array_map(fn (PaymentMethod $m) => $m->toArray(), PaymentMethod::manualCases()),
            'can' => [
                'manage' => $user->can('update', $visit),
                'assign' => $user->can('assign', $visit) && ! $visit->status->isFinal() && $visit->status !== VisitStatus::InProgress,
                'reschedule' => $user->can('update', $visit) && ($visit->status->isUpcoming() || $visit->holdIsActive()),
                'cancel' => $user->can('update', $visit) && ! $visit->status->isFinal() && $visit->status !== VisitStatus::InProgress,
                'noShow' => $user->can('update', $visit) && in_array($visit->status, [VisitStatus::Scheduled, VisitStatus::Confirmed, VisitStatus::InProgress], true),
                'confirm' => $user->can('update', $visit) && $visit->status === VisitStatus::Scheduled,
                'complete' => $user->can('update', $visit) && in_array($visit->status, [VisitStatus::Scheduled, VisitStatus::Confirmed, VisitStatus::InProgress], true),
                'refund' => $payment !== null && $user->can('refund', $payment) && $payment->status->isRefundable(),
                'record' => $payment !== null && $user->can('record', $payment) && in_array($payment->status->value, ['pending', 'failed'], true) && $visit->status->isAwaitingPayment(),
                'sendLink' => $payment !== null && $user->can('update', $visit) && $visit->holdIsActive(),
            ],
        ]);
    }

    public function assign(Request $request, SiteVisit $visit, VisitWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('assign', $visit);
        $data = $request->validate(['technician_id' => ['required', 'integer', 'exists:users,id']]);

        /** @var User $actor */
        $actor = $request->user();

        try {
            $workflow->assign($visit, User::query()->findOrFail((int) $data['technician_id']), $actor);
        } catch (SlotUnavailableException|VisitBookingException $e) {
            throw ValidationException::withMessages(['technician_id' => $e->getMessage()]);
        }

        return back()->with('success', 'Técnico asignado.');
    }

    public function reschedule(RescheduleVisitRequest $request, SiteVisit $visit, VisitWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('update', $visit);

        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validated();

        try {
            $workflow->reschedule($visit, CarbonImmutable::parse($data['date'].' '.$data['start']), isset($data['technician_id']) ? (int) $data['technician_id'] : null, $actor);
        } catch (SlotUnavailableException|VisitBookingException $e) {
            throw ValidationException::withMessages(['start' => $e->getMessage()]);
        }

        return back()->with('success', 'Visita reprogramada.');
    }

    public function cancel(CancelVisitRequest $request, SiteVisit $visit, VisitWorkflow $workflow, PaymentService $payments): RedirectResponse
    {
        Gate::authorize('update', $visit);

        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validated();

        try {
            $workflow->cancel($visit, $actor, (string) $data['reason']);

            if ($request->boolean('refund') && ($payment = $visit->payment()->first()) instanceof Payment) {
                Gate::authorize('refund', $payment);
                $refund = $payments->refund($payment, Money::of((string) $data['refund_amount']), 'Cancelación de visita: '.$data['reason'], (string) $data['idempotency_key'], $actor);

                if ($refund->status->value === 'failed') {
                    return back()->with('error', 'La visita se canceló, pero el reembolso no se completó: '.($refund->error_message ?? 'intenta desde el detalle del pago.'));
                }
            }
        } catch (VisitBookingException|PaymentException $e) {
            throw ValidationException::withMessages(['reason' => $e->getMessage()]);
        }

        return back()->with('success', 'Visita cancelada.');
    }

    public function noShow(Request $request, SiteVisit $visit, VisitWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('update', $visit);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);

        return $this->run(fn (User $actor) => $workflow->markNoShow($visit, $actor, $data['reason'] ?? null), $request, 'Visita marcada como cliente ausente.');
    }

    public function confirm(Request $request, SiteVisit $visit, VisitWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('update', $visit);

        return $this->run(fn (User $actor) => $workflow->confirm($visit, $actor), $request, 'Visita confirmada con el cliente.');
    }

    public function complete(Request $request, SiteVisit $visit, VisitWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('update', $visit);

        return $this->run(fn (User $actor) => $workflow->complete($visit, $actor), $request, 'Visita marcada como realizada.');
    }

    public function sendPaymentLink(SiteVisit $visit, VisitNotifier $notifier): RedirectResponse
    {
        Gate::authorize('update', $visit);

        $payment = $visit->payment()->first();
        abort_if($payment === null || ! $visit->holdIsActive(), 422, 'La visita no tiene un apartado vigente.');

        $notifier->paymentLink($visit, VisitCheckoutUrls::pay($payment));
        app(ActivityLogger::class)->log('visita.enlace_pago', $visit, ['folio' => $visit->folio]);

        return back()->with('success', 'Enlace de pago enviado al cliente.');
    }

    public function recordPayment(RecordManualPaymentRequest $request, SiteVisit $visit, PaymentService $payments): RedirectResponse
    {
        $payment = $visit->payment()->first();
        abort_if($payment === null, 404);
        Gate::authorize('record', $payment);

        /** @var User $actor */
        $actor = $request->user();

        try {
            $payments->recordManual($payment, (string) $request->validated('method'), (string) $request->validated('reference'), $actor);
        } catch (PaymentException $e) {
            throw ValidationException::withMessages(['reference' => $e->getMessage()]);
        }

        return back()->with('success', 'Pago registrado; la visita quedó agendada.');
    }

    private function run(callable $action, Request $request, string $message): RedirectResponse
    {
        try {
            $action($request->user());
        } catch (VisitBookingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
    }

    /** @param Builder<SiteVisit> $query */
    private function applyTab(Builder $query, string $tab): void
    {
        $upcoming = [VisitStatus::Scheduled->value, VisitStatus::Confirmed->value, VisitStatus::InProgress->value];

        match ($tab) {
            'hoy' => $query->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])->whereIn('status', [...$upcoming, VisitStatus::Completed->value, VisitStatus::NoShow->value, VisitStatus::PendingPayment->value, VisitStatus::PaymentProcessing->value]),
            'proximas' => $query->where('ends_at', '>=', now())->whereIn('status', $upcoming),
            'pendientes' => $query->whereIn('status', [VisitStatus::PendingPayment->value, VisitStatus::PaymentProcessing->value]),
            'atencion' => $query->whereNotNull('attention_at'),
            'realizadas' => $query->whereIn('status', [VisitStatus::Completed->value, VisitStatus::NoShow->value]),
            default => null,
        };
    }
}
