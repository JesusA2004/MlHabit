<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Enums\ConsultationOutcome;
use App\Enums\ConsultationStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\BookConsultationRequest;
use App\Models\Consultation;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Crm\ConsultationCheckoutUrls;
use App\Services\Crm\ConsultationScheduler;
use App\Services\Scheduling\SlotUnavailableException;
use App\Support\Crm\CrmLinks;
use App\Support\Crm\Options;
use App\Support\Crm\Present;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /admin/asesorias: agenda de asesorías remotas, detalle, resultado,
 * reprogramación y cancelación. También agenda desde un prospecto.
 */
class ConsultationController extends Controller
{
    public function __construct(private readonly ConsultationScheduler $scheduler) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Consultation::class);

        /** @var User $user */
        $user = $request->user();
        $filters = $request->validate([
            'periodo' => ['nullable', Rule::in(['hoy', 'proximas', 'pasadas', 'todas'])],
            'estado' => ['nullable', Rule::enum(ConsultationStatus::class)],
            'asesor' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);
        $period = $filters['periodo'] ?? 'proximas';

        $query = Consultation::query()->with(['lead.customer', 'advisor', 'payment']);

        if (! $user->hasPermission(Permission::ConsultationsView)) {
            $query->where('advisor_id', $user->id);
        }

        match ($period) {
            'hoy' => $query->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])->orderBy('starts_at'),
            'proximas' => $query->where('ends_at', '>=', now())->active()->orderBy('starts_at'),
            'pasadas' => $query->where('starts_at', '<', now())->orderByDesc('starts_at'),
            default => $query->orderByDesc('starts_at'),
        };

        $query->when($filters['estado'] ?? null, fn ($q, $v) => $q->where('status', $v));
        $advisor = $filters['asesor'] ?? null;
        if ($advisor === 'yo') {
            $query->where('advisor_id', $user->id);
        } elseif (is_string($advisor) && ctype_digit($advisor)) {
            $query->where('advisor_id', (int) $advisor);
        }
        if ($term = trim((string) ($filters['q'] ?? ''))) {
            $query->where(fn (Builder $q) => $q->where('folio', 'like', "%{$term}%")
                ->orWhereHas('lead', fn (Builder $l) => $l->where('folio', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn (Builder $c) => $c->whereRaw("CONCAT(first_name, ' ', COALESCE(last_name, '')) LIKE ?", ["%{$term}%"]))));
        }

        $page = $query->paginate(25)->withQueryString()->through(fn (Consultation $c) => [...Present::consultation($c, true), 'payment' => self::paymentBrief($c->payment), 'holdExpiresAt' => $c->status->isAwaitingPayment() ? $c->hold_expires_at?->toIso8601String() : null, 'needsAttention' => $c->attention_reason !== null]);

        return Inertia::render('admin/asesorias/Index', [
            'consultations' => $page,
            'filters' => ['periodo' => $period, ...array_filter($filters, fn ($v) => $v !== null && $v !== '')],
            'counts' => [
                'hoy' => Consultation::query()->active()->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])
                    ->when(! $user->hasPermission(Permission::ConsultationsView), fn ($q) => $q->where('advisor_id', $user->id))->count(),
            ],
            'options' => [
                'statuses' => Options::of(ConsultationStatus::class),
                'advisors' => $this->scheduler->advisors()->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name])->values(),
            ],
        ]);
    }

    public function show(Request $request, Consultation $consultation): Response
    {
        Gate::authorize('view', $consultation);

        /** @var User $user */
        $user = $request->user();
        $consultation->load(['lead.customer', 'advisor', 'rescheduledFrom', 'rescheduledTo', 'payment']);
        $settings = $this->scheduler->settings();
        $payment = $consultation->payment;

        return Inertia::render('admin/asesorias/Show', [
            'consultation' => [
                ...Present::consultation($consultation, true),
                'customerNotes' => $consultation->customer_notes,
                'internalNotes' => $consultation->internal_notes,
                'outcomeNotes' => $consultation->outcome_notes,
                'cancelReason' => $consultation->cancel_reason,
                'meetingProvider' => $consultation->meeting_provider,
                'completedAt' => $consultation->completed_at?->toIso8601String(),
                'cancelledAt' => $consultation->cancelled_at?->toIso8601String(),
                'rescheduledFrom' => $consultation->rescheduledFrom ? ['uuid' => $consultation->rescheduledFrom->uuid, 'folio' => $consultation->rescheduledFrom->folio] : null,
                'rescheduledTo' => $consultation->rescheduledTo ? ['uuid' => $consultation->rescheduledTo->uuid, 'folio' => $consultation->rescheduledTo->folio] : null,
                'leadStatus' => Options::one($consultation->lead->status),
                'customerPhone' => $consultation->lead->customer->phone,
                'holdExpiresAt' => $consultation->status->isAwaitingPayment() ? $consultation->hold_expires_at?->toIso8601String() : null,
                'paidAt' => $consultation->paid_at?->toIso8601String(),
                'attentionReason' => $consultation->attention_reason,
            ],
            'payment' => $payment ? [
                ...self::paymentBrief($payment),
                'description' => $payment->description,
                'method' => $payment->method->label(),
                'cardBrand' => $payment->card_brand,
                'cardLast4' => $payment->card_last4,
                'authorization' => $payment->provider_authorization,
                'errorMessage' => $payment->error_message,
                'refundedAmount' => $payment->refunded_amount,
                'url' => $user->can('view', $payment) ? route('admin.pagos.show', $payment) : null,
            ] : null,
            'payUrl' => $consultation->status->isAwaitingPayment() && $consultation->holdIsActive() ? ConsultationCheckoutUrls::pay($consultation) : null,
            'leadUrl' => $user->can('view', $consultation->lead) ? route('admin.prospectos.show', $consultation->lead) : null,
            'customerManageUrl' => CrmLinks::manageConsultation($consultation),
            'options' => [
                'outcomes' => Options::of(ConsultationOutcome::class),
                'statuses' => Options::of([ConsultationStatus::Confirmed, ConsultationStatus::Completed, ConsultationStatus::NoShow]),
                'channels' => $settings->toArray()['channels'],
                'advisors' => $this->scheduler->advisors()->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name])->values(),
            ],
            'availabilityUrl' => route('admin.asesorias.disponibilidad'),
            'can' => ['update' => $user->can('update', $consultation)],
        ]);
    }

    /** Agenda desde el detalle del prospecto (panel). */
    public function storeForLead(BookConsultationRequest $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('scheduleConsultation', $lead);

        try {
            $consultation = $this->scheduler->book(
                $lead,
                $request->startsAt(),
                $request->channel() ?? throw ValidationException::withMessages(['channel' => 'Elige el medio.']),
                $request->validated('notes'),
                $request->user(),
                $request->validated('advisor_id') ? (int) $request->validated('advisor_id') : null,
                staffBooking: true,
            );
        } catch (SlotUnavailableException $e) {
            throw ValidationException::withMessages(['starts_at' => $e->getMessage()]);
        }

        return back()->with('success', "Asesoría {$consultation->folio} apartada con {$consultation->advisor->name}. Enviamos al cliente el enlace de pago; se confirma al pagar.");
    }

    /** Reenvía al cliente el enlace de pago de una asesoría apartada. */
    public function sendPaymentLink(Consultation $consultation): RedirectResponse
    {
        Gate::authorize('update', $consultation);

        if (! $consultation->status->isAwaitingPayment() || ! $consultation->holdIsActive()) {
            throw ValidationException::withMessages(['status' => 'Esta asesoría no tiene un pago pendiente vigente.']);
        }

        $this->scheduler->notifyPaymentLink($consultation);

        return back()->with('success', 'Reenviamos el enlace de pago al cliente.');
    }

    /**
     * @return array{uuid: string, folio: string, amount: string, status: array<string, mixed>, paidAt: string|null}|null
     */
    private static function paymentBrief(?Payment $payment): ?array
    {
        if ($payment === null) {
            return null;
        }

        return [
            'uuid' => $payment->uuid,
            'folio' => $payment->folio,
            'amount' => $payment->amount,
            'status' => $payment->status->toArray(),
            'paidAt' => $payment->paid_at?->toIso8601String(),
        ];
    }

    /** Disponibilidad para el panel (sin anticipación mínima; filtro por asesor). */
    public function availability(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission(Permission::ConsultationsManage), 403);
        $data = $request->validate([
            'mes' => ['nullable', 'date_format:Y-m'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'asesor' => ['nullable', 'integer'],
        ]);
        $advisor = isset($data['asesor']) ? (int) $data['asesor'] : null;

        if (! empty($data['fecha'])) {
            $slots = $this->scheduler->slotsForDate(CarbonImmutable::parse($data['fecha']), $advisor, 0);

            return response()->json(['slots' => array_map(fn ($s) => ['value' => $s['starts_at'], 'time' => $s['time'], 'advisors' => $s['advisors']], $slots)]);
        }

        $month = CarbonImmutable::parse(($data['mes'] ?? now()->format('Y-m')).'-01');

        return response()->json(['dates' => $this->scheduler->availableDates($month->year, $month->month, $advisor, 0)]);
    }

    public function update(Request $request, Consultation $consultation, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('update', $consultation);
        $data = $request->validate([
            'meeting_url' => ['nullable', 'url:https', 'max:500'],
            'internal_notes' => ['nullable', 'string', 'max:4000'],
        ], ['meeting_url.url' => 'Pega un enlace completo que empiece con https://']);

        $consultation->fill($data);
        $logger->logModelChanges('asesoria.actualizada', $consultation, ['meeting_url']);
        $consultation->save();

        return back()->with('success', 'Asesoría actualizada.');
    }

    public function updateStatus(Request $request, Consultation $consultation): RedirectResponse
    {
        Gate::authorize('update', $consultation);
        $data = $request->validate(['status' => ['required', Rule::in(['confirmed', 'completed', 'no_show'])]]);

        /** @var User $user */
        $user = $request->user();
        $this->scheduler->markStatus($consultation, ConsultationStatus::from($data['status']), $user);

        return back()->with('success', 'Estado actualizado.');
    }

    public function outcome(Request $request, Consultation $consultation): RedirectResponse
    {
        Gate::authorize('update', $consultation);
        $data = $request->validate([
            'outcome' => ['required', Rule::enum(ConsultationOutcome::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['outcome.required' => 'Elige el resultado de la asesoría.']);

        /** @var User $user */
        $user = $request->user();
        $this->scheduler->recordOutcome($consultation, ConsultationOutcome::from($data['outcome']), $data['notes'] ?? null, $user);

        return back()->with('success', 'Resultado registrado.');
    }

    public function reschedule(BookConsultationRequest $request, Consultation $consultation): RedirectResponse
    {
        Gate::authorize('update', $consultation);

        try {
            $new = $this->scheduler->reschedule(
                $consultation,
                $request->startsAt(),
                $request->user(),
                true,
                $request->channel(),
                $request->validated('advisor_id') ? (int) $request->validated('advisor_id') : null,
            );
        } catch (SlotUnavailableException $e) {
            throw ValidationException::withMessages(['starts_at' => $e->getMessage()]);
        }

        return redirect()->route('admin.asesorias.show', $new)->with('success', 'Asesoría reprogramada. Avisamos al cliente por correo.');
    }

    public function cancel(Request $request, Consultation $consultation): RedirectResponse
    {
        Gate::authorize('update', $consultation);
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']], ['reason.required' => 'Escribe el motivo; se guarda en el historial.']);

        $this->scheduler->cancel($consultation, $data['reason'], $request->user());

        return back()->with('success', 'Asesoría cancelada. Avisamos al cliente por correo.');
    }
}
