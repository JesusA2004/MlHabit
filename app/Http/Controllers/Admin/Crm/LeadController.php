<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Enums\BudgetRange;
use App\Enums\ConsultationChannel;
use App\Enums\LeadActivityType;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\LostReason;
use App\Enums\PaymentPreference;
use App\Enums\Permission;
use App\Enums\PreferredContact;
use App\Enums\Timeframe;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Crm\LeadRequestController;
use App\Http\Requests\Crm\ChangeLeadStatusRequest;
use App\Http\Requests\Crm\StoreLeadRequest;
use App\Models\Lead;
use App\Models\SiteVisit;
use App\Models\User;
use App\Policies\LeadPolicy;
use App\Services\CoverageService;
use App\Services\Crm\ConsultationScheduler;
use App\Services\Crm\LeadIntakeService;
use App\Services\Crm\LeadWorkflow;
use App\Services\Crm\PrequalificationService;
use App\Support\Crm\CrmLinks;
use App\Support\Crm\Options;
use App\Support\Crm\Present;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /admin/prospectos: lista (y tablero por etapa), detalle y acciones
 * comerciales. Toda transición de etapa pasa por LeadWorkflow.
 */
class LeadController extends Controller
{
    private const SORTS = ['recientes', 'antiguos', 'seguimiento', 'folio'];

    public function __construct(private readonly LeadWorkflow $workflow) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Lead::class);

        /** @var User $user */
        $user = $request->user();
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', Rule::enum(LeadStatus::class)],
            'origen' => ['nullable', Rule::enum(LeadSource::class)],
            'responsable' => ['nullable', 'string', 'max:20'],
            'categoria' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
            'vista' => ['nullable', Rule::in(['nuevos', 'sin_contactar', 'mios', 'seguimiento_hoy'])],
            'orden' => ['nullable', Rule::in(self::SORTS)],
            'modo' => ['nullable', Rule::in(['lista', 'tablero'])],
        ]);

        $followUpHours = (int) Settings::get('leads.follow_up_hours', 24);
        $base = $this->filtered($filters, $user, $followUpHours);
        $mode = $filters['modo'] ?? 'lista';

        $props = [
            'filters' => array_merge(['modo' => $mode, 'orden' => $filters['orden'] ?? 'recientes'], array_filter($filters, fn ($v) => $v !== null && $v !== '')),
            'counts' => $this->quickCounts($user, $followUpHours),
            'followUpHours' => $followUpHours,
            'options' => $this->listOptions($user),
            'can' => [
                'create' => $user->can('create', Lead::class),
                'assign' => $user->hasPermission(Permission::LeadsAssign),
                'update' => $user->hasPermission(Permission::LeadsManage),
            ],
        ];

        if ($mode === 'tablero') {
            $props['board'] = $this->board($base);
            $props['leads'] = null;
        } else {
            $sorted = $this->sorted($base, $filters['orden'] ?? 'recientes');
            $page = $sorted->with(['customer', 'assignee'])->paginate(25)->withQueryString();
            $categories = Present::categoriesFor(array_values($page->getCollection()->map(fn (Lead $l) => $l->id)->all()));
            $page->through(fn (Lead $lead) => Present::leadRow($lead, $categories[$lead->id] ?? []));
            $props['leads'] = $page;
            $props['board'] = null;
        }

        return Inertia::render('admin/prospectos/Index', $props);
    }

    public function show(Request $request, Lead $lead, CoverageService $coverage, PrequalificationService $prequalifications, ConsultationScheduler $scheduler): Response
    {
        Gate::authorize('view', $lead);

        /** @var User $user */
        $user = $request->user();
        $lead->load(['customer', 'assignee', 'coverageZone', 'activities.user', 'consultations.advisor']);

        $photos = $lead->mediaWithRole(Lead::PHOTO_ROLE)->get()->map(fn ($m) => Present::media($m))->values();
        $prequalification = $user->hasPermission(Permission::PrequalificationView) ? $prequalifications->current($lead) : null;
        $settings = $scheduler->settings();

        return Inertia::render('admin/prospectos/Show', [
            'lead' => [
                ...Present::leadRow($lead),
                'paymentPreference' => Options::maybe($lead->payment_preference),
                'timeframe' => Options::maybe($lead->timeframe),
                'measurementsNote' => $lead->measurements_note,
                'comments' => $lead->comments,
                'state' => $lead->state,
                'lostReason' => Options::maybe($lead->lost_reason),
                'lostNotes' => $lead->lost_notes,
                'closedAt' => $lead->closed_at?->toIso8601String(),
                'coverage' => [
                    'zone' => $lead->coverageZone?->name,
                    'acceptsVisits' => $coverage->acceptsVisits($lead->postal_code),
                ],
                'attribution' => [
                    'source' => Options::one($lead->source),
                    'form' => $lead->form,
                    'utmSource' => $lead->utm_source,
                    'utmMedium' => $lead->utm_medium,
                    'utmCampaign' => $lead->utm_campaign,
                    'utmTerm' => $lead->utm_term,
                    'utmContent' => $lead->utm_content,
                    'referrer' => $lead->referrer,
                    'landingUrl' => $lead->landing_url,
                    'deviceType' => $lead->device_type,
                ],
                'customerNotes' => $lead->customer->notes,
            ],
            'customerUrl' => $user->hasPermission(Permission::CustomersView) ? route('admin.clientes.show', $lead->customer) : null,
            'photos' => $photos,
            'activities' => $lead->activities->take(150)->map(fn ($a) => Present::activity($a))->values(),
            'consultations' => $lead->consultations->sortByDesc('starts_at')->map(fn ($c) => Present::consultation($c))->values(),
            'visits' => $this->visits($lead),
            'prequalification' => $prequalification ? [
                'uuid' => $prequalification->uuid,
                'folio' => $prequalification->folio,
                'status' => Options::one($prequalification->status),
                'url' => route('admin.precalificaciones.show', $prequalification),
            ] : null,
            'project' => $lead->project ? [
                'folio' => $lead->project->folio,
                'url' => Route::has('admin.proyectos.show') ? route('admin.proyectos.show', $lead->project) : null,
            ] : null,
            'options' => [
                'transitions' => Options::of($lead->status->allowedTransitions()),
                'lostReasons' => Options::of(LostReason::class),
                'activityTypes' => Options::of(LeadActivityType::manual()),
                'assignees' => $user->hasPermission(Permission::LeadsAssign) ? $this->assignees() : [],
                'channels' => $settings->toArray()['channels'],
                'advisors' => $user->can('scheduleConsultation', $lead)
                    ? $scheduler->advisors()->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name])->values()
                    : [],
            ],
            'consultationSettings' => ['durationMinutes' => $settings->durationMinutes],
            'can' => [
                'update' => $user->can('update', $lead),
                'assign' => $user->can('assign', $lead),
                'scheduleConsultation' => $user->can('scheduleConsultation', $lead),
                'startPrequalification' => $user->can('startPrequalification', $lead) && $prequalification === null && ! $lead->status->isClosed(),
                'scheduleVisit' => $user->hasPermission(Permission::VisitsManage) && ! $lead->status->isClosed(),
                'createProject' => $user->hasPermission(Permission::ProjectsManage) && $lead->project === null && ! in_array($lead->status, [LeadStatus::Lost, LeadStatus::Cancelled], true),
            ],
            'links' => [
                'whatsapp' => $lead->customer->phone ? 'https://wa.me/52'.$lead->customer->phone.'?text='.rawurlencode("Hola {$lead->customer->first_name}, te saluda ML Habitat sobre tu solicitud {$lead->folio}.") : null,
                'tel' => $lead->customer->phone ? 'tel:+52'.$lead->customer->phone : null,
                'mailto' => $lead->customer->email ? 'mailto:'.$lead->customer->email.'?subject='.rawurlencode("Tu solicitud {$lead->folio} · ML Habitat") : null,
                'scheduleVisit' => '/admin/visitas/crear?prospecto='.$lead->uuid,
                'createProject' => '/admin/prospectos/'.$lead->uuid.'/proyecto',
                'customerBooking' => $settings->enabled ? CrmLinks::bookConsultation($lead) : null,
                'customerVisit' => CrmLinks::bookVisit($lead),
                'availability' => route('admin.asesorias.disponibilidad'),
            ],
        ]);
    }

    public function store(StoreLeadRequest $request, LeadIntakeService $intake): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();
        if (! $user->hasPermission(Permission::LeadsAssign)) {
            $data['assigned_to'] = $user->id;
        }

        $lead = $intake->create($data, [], [], LeadSource::from($data['source']), 'panel', $user);

        return redirect()->route('admin.prospectos.show', $lead)->with('success', "Prospecto {$lead->folio} registrado.");
    }

    public function updateStatus(ChangeLeadStatusRequest $request, Lead $lead): RedirectResponse
    {
        $to = LeadStatus::from($request->validated('status'));

        $this->workflow->transition($lead, $to, $request->user(), $request->validated('note'), attributes: [
            'lost_reason' => $request->validated('lost_reason'),
            'lost_notes' => $request->validated('lost_notes'),
        ]);

        return back()->with('success', "Etapa actualizada: {$to->label()}.");
    }

    public function assign(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('assign', $lead);
        $data = $request->validate(['assigned_to' => ['nullable', 'integer', 'exists:users,id']]);

        $assignee = isset($data['assigned_to'])
            ? User::query()->withPermission(Permission::LeadsView)->whereKey((int) $data['assigned_to'])->first()
            : null;
        if (isset($data['assigned_to']) && ! $assignee) {
            return back()->withErrors(['assigned_to' => 'Esa persona no puede atender prospectos.']);
        }

        $this->workflow->assign($lead, $assignee, $request->user());

        return back()->with('success', $assignee ? "Asignado a {$assignee->name}." : 'Prospecto sin responsable.');
    }

    public function followUp(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('update', $lead);
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['date.after_or_equal' => 'Elige hoy o una fecha futura.']);

        $at = isset($data['date']) ? CarbonImmutable::parse($data['date'].' '.($data['time'] ?? '10:00')) : null;
        /** @var User $user */
        $user = $request->user();
        $this->workflow->scheduleFollowUp($lead, $at, $user, $data['note'] ?? null);

        return back()->with('success', $at ? 'Seguimiento programado.' : 'Seguimiento quitado.');
    }

    public function storeActivity(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('update', $lead);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_map(fn ($t) => $t->value, LeadActivityType::manual()))],
            'body' => ['required', 'string', 'max:2000'],
        ], ['body.required' => 'Escribe la nota.']);

        /** @var User $user */
        $user = $request->user();
        $this->workflow->logInteraction($lead, LeadActivityType::from($data['type']), $data['body'], $user);

        return back()->with('success', 'Registrado en la bitácora.');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Lead>
     */
    private function filtered(array $filters, User $user, int $followUpHours): Builder
    {
        $query = LeadPolicy::scopeVisible(Lead::query(), $user);

        if ($term = trim((string) ($filters['q'] ?? ''))) {
            $digits = preg_replace('/\D/', '', $term);
            $query->where(function (Builder $q) use ($term, $digits) {
                $q->where('folio', 'like', "%{$term}%")
                    ->orWhereHas('customer', function (Builder $c) use ($term, $digits) {
                        $c->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%")
                            ->orWhereRaw("CONCAT(first_name, ' ', COALESCE(last_name, '')) LIKE ?", ["%{$term}%"])
                            ->orWhere('email', 'like', "%{$term}%");
                        if ($digits !== null && strlen($digits) >= 4) {
                            $c->orWhere('phone', 'like', "%{$digits}%");
                        }
                    });
            });
        }

        $query->when($filters['estado'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['origen'] ?? null, fn ($q, $v) => $q->where('source', $v))
            ->when($filters['categoria'] ?? null, fn ($q, $v) => $q->inCategory((int) $v))
            ->when($filters['desde'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['hasta'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v));

        $assigned = $filters['responsable'] ?? null;
        if ($assigned === 'yo') {
            $query->where('assigned_to', $user->id);
        } elseif ($assigned === 'sin') {
            $query->whereNull('assigned_to');
        } elseif (is_string($assigned) && ctype_digit($assigned)) {
            $query->where('assigned_to', (int) $assigned);
        }

        match ($filters['vista'] ?? null) {
            'nuevos' => $query->where('status', LeadStatus::New->value),
            'sin_contactar' => $query->awaitingContact($followUpHours),
            'mios' => $query->where('assigned_to', $user->id)->open(),
            'seguimiento_hoy' => $query->open()->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now()->endOfDay()),
            default => null,
        };

        return $query;
    }

    /**
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    private function sorted(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'antiguos' => $query->orderBy('created_at')->orderBy('id'),
            'seguimiento' => $query->orderByRaw('next_follow_up_at IS NULL')->orderBy('next_follow_up_at')->orderByDesc('id'),
            'folio' => $query->orderBy('folio'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }

    /**
     * Tablero: columnas por etapa abierta (máx. 30 tarjetas por columna).
     *
     * @param  Builder<Lead>  $base
     * @return list<array<string, mixed>>
     */
    private function board(Builder $base): array
    {
        $columns = [];
        $totals = (clone $base)->toBase()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        foreach ([...LeadStatus::open(), LeadStatus::Won] as $status) {
            $leads = (clone $base)->where('status', $status->value)
                ->with(['customer', 'assignee'])
                ->orderByDesc('updated_at')
                ->limit(30)
                ->get();
            $categories = Present::categoriesFor(array_values($leads->map(fn (Lead $l) => $l->id)->all()));

            $columns[] = [
                'status' => Options::one($status),
                'total' => (int) ($totals[$status->value] ?? 0),
                'leads' => $leads->map(fn (Lead $l) => [
                    ...Present::leadRow($l, $categories[$l->id] ?? []),
                    'transitions' => array_map(fn ($s) => $s->value, $l->status->allowedTransitions()),
                ])->values()->all(),
            ];
        }

        return $columns;
    }

    /** @return array<string, int> */
    private function quickCounts(User $user, int $followUpHours): array
    {
        $scope = fn () => LeadPolicy::scopeVisible(Lead::query(), $user);

        return [
            'nuevos' => $scope()->where('status', LeadStatus::New->value)->count(),
            'sin_contactar' => $scope()->awaitingContact($followUpHours)->count(),
            'mios' => $scope()->where('assigned_to', $user->id)->open()->count(),
            'seguimiento_hoy' => $scope()->open()->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now()->endOfDay())->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function listOptions(User $user): array
    {
        return [
            'statuses' => Options::of(LeadStatus::class),
            'sources' => Options::of(LeadSource::class),
            'categories' => array_map(fn ($c) => ['value' => $c['id'], 'label' => $c['name']], LeadRequestController::categories()),
            'assignees' => $this->assignees(),
            'budgetRanges' => Options::of(BudgetRange::class),
            'paymentPreferences' => Options::of(PaymentPreference::class),
            'timeframes' => Options::of(Timeframe::class),
            'preferredContacts' => Options::of(PreferredContact::class),
            'channels' => Options::of(ConsultationChannel::class),
            'currentUserId' => $user->id,
        ];
    }

    /** @return list<array{value: int, label: string}> */
    private function assignees(): array
    {
        return array_values(User::query()->withPermission(Permission::LeadsManage)->orderBy('name')->get(['id', 'name'])
            ->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name])->all());
    }

    /**
     * Visitas del prospecto si el módulo de visitas ya existe.
     *
     * @return list<array<string, mixed>>
     */
    private function visits(Lead $lead): array
    {
        // El modelo pertenece al módulo de visitas (desarrollo en paralelo).
        if (! class_exists(SiteVisit::class)) {
            return [];
        }

        return array_values(SiteVisit::query()->where('lead_id', $lead->id)->orderByDesc('starts_at')->limit(20)->get()
            ->map(fn (SiteVisit $visit) => [
                'uuid' => $visit->uuid,
                'folio' => $visit->folio,
                'startsAt' => $visit->starts_at->toIso8601String(),
                'status' => Options::one($visit->status),
                'url' => Route::has('admin.visitas.show') ? route('admin.visitas.show', $visit->uuid) : null,
            ])->all());
    }
}
