<?php

namespace App\Http\Controllers\Admin\Projects;

use App\Enums\ContractStatus;
use App\Enums\DesignStatus;
use App\Enums\Permission;
use App\Enums\ProjectStatus;
use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\Projects\ProjectPresenter as P;
use App\Http\Requests\Projects\ProjectRequest;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\Projects\ContractService;
use App\Services\Projects\CustomerNotifier;
use App\Services\Projects\ProjectService;
use App\Services\Projects\ProjectWorkflow;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProjectController extends Controller
{
    /** Vistas rápidas → estados. */
    private const VIEWS = [
        'activos' => null,
        'pendientes-anticipo' => [ProjectStatus::PendingDeposit],
        'en-fabricacion' => [ProjectStatus::Authorized, ProjectStatus::Fabrication, ProjectStatus::ReadyToInstall],
        'en-instalacion' => [ProjectStatus::InstallationScheduled, ProjectStatus::Installing, ProjectStatus::QualityReview, ProjectStatus::PunchList],
        'todos' => null,
    ];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Project::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'vista' => ['nullable', Rule::in(array_keys(self::VIEWS))],
            'estado' => ['nullable', Rule::enum(ProjectStatus::class)],
            'asesor' => ['nullable', 'integer'],
            'disenador' => ['nullable', 'integer'],
            'categoria' => ['nullable', 'integer'],
        ]);
        $view = $filters['vista'] ?? 'activos';

        $projects = Project::query()
            ->with(['customer:id,uuid,first_name,last_name', 'category:id,name', 'advisor:id,name', 'designer:id,name'])
            ->when($filters['q'] ?? null, function (Builder $q, string $term) {
                $q->where(function (Builder $w) use ($term) {
                    $w->where('folio', 'like', "%{$term}%")
                        ->orWhere('name', 'like', "%{$term}%")
                        ->orWhereHas('customer', fn (Builder $c) => $c->where(DB::raw("CONCAT(first_name, ' ', COALESCE(last_name, ''))"), 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%"));
                });
            })
            ->when($view === 'activos', fn (Builder $q) => $q->whereNotIn('status', [ProjectStatus::Completed->value, ProjectStatus::Cancelled->value]))
            ->when(is_array(self::VIEWS[$view] ?? null), fn (Builder $q) => $q->whereIn('status', array_map(fn ($s) => $s->value, self::VIEWS[$view])))
            ->when($filters['estado'] ?? null, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($filters['asesor'] ?? null, fn (Builder $q, int $id) => $q->where('advisor_id', $id))
            ->when($filters['disenador'] ?? null, fn (Builder $q, int $id) => $q->where('designer_id', $id))
            ->when($filters['categoria'] ?? null, fn (Builder $q, int $id) => $q->where('product_category_id', $id))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Project $p) => P::summary($p));

        $counts = Project::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $sum = fn (?array $statuses) => (int) collect($counts)->filter(fn ($v, $k) => $statuses === null
            ? ! in_array($k, [ProjectStatus::Completed->value, ProjectStatus::Cancelled->value], true)
            : in_array($k, array_map(fn ($s) => $s->value, $statuses), true))->sum();

        return Inertia::render('admin/proyectos/Index', [
            'projects' => $projects,
            'filters' => ['q' => $filters['q'] ?? '', 'vista' => $view, 'estado' => $filters['estado'] ?? null, 'asesor' => $filters['asesor'] ?? null, 'disenador' => $filters['disenador'] ?? null, 'categoria' => $filters['categoria'] ?? null],
            'views' => [
                ['key' => 'activos', 'label' => 'Activos', 'count' => $sum(null)],
                ['key' => 'pendientes-anticipo', 'label' => 'Pendientes de anticipo', 'count' => $sum(self::VIEWS['pendientes-anticipo'])],
                ['key' => 'en-fabricacion', 'label' => 'En fabricación', 'count' => $sum(self::VIEWS['en-fabricacion'])],
                ['key' => 'en-instalacion', 'label' => 'En instalación', 'count' => $sum(self::VIEWS['en-instalacion'])],
                ['key' => 'todos', 'label' => 'Todos', 'count' => (int) collect($counts)->sum()],
            ],
            'options' => $this->options(),
            'can' => ['create' => Gate::allows('create', Project::class)],
        ]);
    }

    public function show(Project $project): Response
    {
        Gate::authorize('view', $project);
        /** @var User $user */
        $user = request()->user();

        $project->load([
            'customer', 'address', 'category:id,name', 'advisor:id,name', 'designer:id,name', 'operationsOwner:id,name', 'lead:id,uuid,folio',
            'measurements' => fn ($q) => $q->with(['items', 'technician:id,name', 'attachments.media'])->orderByDesc('version'),
            'designVersions' => fn ($q) => $q->with(['attachments.media', 'technicalApprover:id,name', 'creator:id,name'])->orderByDesc('number'),
            'quotes' => fn ($q) => $q->with('designVersion:id,number')->orderByDesc('version'),
            'contract.quote:id,uuid,folio',
            'attachments.media',
        ]);

        $events = $project->events()->with('user:id,name')->orderByDesc('occurred_at')->orderByDesc('id')->limit(200)->get();
        $contract = $project->contract;

        $customer = $project->customer;

        return Inertia::render('admin/proyectos/Show', [
            'project' => [
                ...P::summary($project),
                'operations_owner' => P::person($project->operationsOwner),
                'address' => P::address($project->address),
                'lead' => $project->lead ? ['uuid' => $project->lead->uuid, 'folio' => $project->lead->folio] : null,
                'contracted_at' => P::date($project->contracted_at),
                'completed_at' => P::date($project->completed_at),
                'cancelled_at' => P::date($project->cancelled_at),
                'cancel_reason' => $project->cancel_reason,
                'customer' => [
                    'uuid' => $customer->uuid,
                    'name' => $customer->full_name,
                    'first_name' => $customer->first_name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'has_portal' => $customer->user_id !== null,
                    'portal_active' => $customer->user_id !== null && User::query()->whereKey($customer->user_id)->whereNotNull('email_verified_at')->exists(),
                ],
                'address_id' => $project->address_id,
                'product_category_id' => $project->product_category_id,
                'advisor_id' => $project->advisor_id,
                'designer_id' => $project->designer_id,
                'operations_owner_id' => $project->operations_owner_id,
            ],
            'journey' => P::journey(),
            'transitions' => array_map(fn (ProjectStatus $s) => ['value' => $s->value, 'label' => $s->label(), 'tone' => $s->tone()], $project->status->allowedTransitions()),
            'events' => $events->map(fn ($e) => P::event($e))->values(),
            'visits' => $this->visits($project),
            'measurements' => $project->measurements->map(fn ($m) => P::measurement($m))->values(),
            'designs' => $project->designVersions->map(fn ($d) => P::design($d))->values(),
            'quotes' => $project->quotes->map(fn ($q) => P::quoteSummary($q))->values(),
            'contract' => $contract ? P::contract($contract, $this->contractExtras($contract)) : null,
            'operations' => $this->operations($project),
            'files' => $project->attachments->map(fn ($a) => P::file($a))->values(),
            'addresses' => $customer->addresses()->get()->map(fn ($a) => P::address($a))->values(),
            'options' => $this->options(),
            'paymentMethods' => [
                ['value' => 'transfer', 'label' => 'Transferencia'],
                ['value' => 'cash', 'label' => 'Efectivo'],
                ['value' => 'other', 'label' => 'Otro'],
            ],
            'can' => [
                'manage' => $user->hasPermission(Permission::ProjectsManage),
                'designs_manage' => $user->hasPermission(Permission::DesignsManage),
                'approve_technical' => $user->hasPermission(Permission::DesignsApproveTechnical),
                'quotes_manage' => $user->hasPermission(Permission::QuotesManage),
                'authorize_discount' => $user->hasPermission(Permission::QuotesAuthorizeDiscount),
                'record_payment' => $user->hasPermission(Permission::PaymentsRecord),
                'invite' => $user->hasPermission(Permission::CustomersManage),
                'view_operations' => $user->hasPermission(Permission::FabricationView) || $user->hasPermission(Permission::InstallationsView),
            ],
            'summary' => [
                'pending_client_design' => $project->designVersions->contains(fn ($d) => $d->status === DesignStatus::SentToClient),
                'pending_technical' => $project->designVersions->contains(fn ($d) => $d->status === DesignStatus::ClientApproved),
                'open_quote' => $project->quotes->contains(fn ($q) => $q->status === QuoteStatus::Sent),
                'deposit_pending' => $contract !== null && $contract->status === ContractStatus::PendingDeposit,
            ],
        ]);
    }

    /** Alta manual desde un cliente. */
    public function store(ProjectRequest $request, ProjectService $service): RedirectResponse
    {
        Gate::authorize('create', Project::class);
        /** @var Customer $customer */
        $customer = Customer::query()->where('uuid', $request->string('customer_uuid'))->firstOrFail();

        $project = $service->createForCustomer($customer, $request->safe()->except('customer_uuid'), $request->user());

        return to_route('admin.proyectos.show', $project)->with('success', "Proyecto {$project->folio} creado.");
    }

    /** Contrato crm → projects: crear (o abrir) el proyecto del prospecto. */
    public function storeFromLead(Request $request, Lead $lead, ProjectService $service): RedirectResponse
    {
        Gate::authorize('projects.manage');

        $existed = $lead->project()->exists();
        $project = $service->createFromLead($lead, $request->user());

        return to_route('admin.proyectos.show', $project)->with('success', $existed ? 'Este prospecto ya tenía proyecto; te llevamos a él.' : "Proyecto {$project->folio} creado.");
    }

    public function storeFromVisit(Request $request, SiteVisit $visit, ProjectService $service): RedirectResponse
    {
        Gate::authorize('projects.manage');

        $project = $service->createFromVisit($visit, $request->user());

        return to_route('admin.proyectos.show', $project)->with('success', "Proyecto {$project->folio} listo.");
    }

    public function update(ProjectRequest $request, Project $project, ProjectService $service): RedirectResponse
    {
        Gate::authorize('update', $project);
        $service->update($project, $request->validated(), $request->user());

        return back()->with('success', 'Proyecto actualizado.');
    }

    public function transition(Request $request, Project $project, ProjectWorkflow $workflow, CustomerNotifier $notifier): RedirectResponse
    {
        Gate::authorize('update', $project);
        $data = $request->validate([
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'note' => [Rule::requiredIf($request->input('status') === ProjectStatus::Cancelled->value), 'nullable', 'string', 'max:200'],
            'notify_client' => ['boolean'],
        ], ['note.required' => 'Indica el motivo de la cancelación.']);

        $to = ProjectStatus::from($data['status']);
        if (in_array($to, [ProjectStatus::Authorized, ProjectStatus::PendingDeposit], true)) {
            return back()->with('error', 'Esta etapa la fija el contrato y el pago del anticipo, no se cambia a mano.');
        }

        $notify = (bool) ($data['notify_client'] ?? true);
        // ProjectWorkflow notifica al cliente cuando la transición es visible.
        $workflow->transition($project, $to, $request->user(), $data['note'] ?? null, $notify);

        return back()->with('success', "Proyecto en «{$to->label()}».");
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        $users = fn (Permission $p) => User::query()->withPermission($p)->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values();

        return [
            'statuses' => array_map(fn (ProjectStatus $s) => ['value' => $s->value, 'label' => $s->label(), 'tone' => $s->tone()], ProjectStatus::cases()),
            'advisors' => $users(Permission::LeadsManage),
            'designers' => $users(Permission::DesignsManage),
            'operations' => $users(Permission::InstallationsManage),
            'categories' => DB::table('product_categories')->orderBy('sort')->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['id' => (int) $c->id, 'name' => (string) $c->name])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function contractExtras(Contract $contract): array
    {
        $remaining = $this->safe(fn () => Money::of($contract->deposit_due_amount)->minus(app(ContractService::class)->paidDeposit($contract))->max(Money::zero())->amount);

        return [
            'remaining' => $remaining,
            'paid' => $remaining === null ? null : Money::of($contract->deposit_due_amount)->minus($remaining)->amount,
            'payments' => $this->safe(fn () => $contract->payments()->latest('id')->get()->map(fn ($p) => P::payment($p))->values()->all()) ?? [],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function visits(Project $project): array
    {
        return $this->safe(fn () => SiteVisit::query()
            ->where('project_id', $project->id)
            ->with('technician:id,name')
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (SiteVisit $v) => [
                'uuid' => $v->uuid,
                'folio' => $v->folio,
                'starts_at' => P::date($v->starts_at),
                'status' => P::enum($v->status),
                'technician' => $v->technician?->name,
            ])->values()->all()) ?? [];
    }

    /**
     * Tarjetas de solo lectura de ops (fabricación, instalación, incidencias, garantías).
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function operations(Project $project): array
    {
        $map = fn (string $relation, callable $fn) => $this->safe(fn () => $project->{$relation}()->latest('id')->limit(20)->get()->map($fn)->values()->all()) ?? [];

        return [
            'fabrication' => $map('fabricationOrders', fn ($o) => [
                'id' => $o->id, 'folio' => $o->folio, 'status' => P::enum($o->status),
                'due_on' => P::day($o->due_on), 'ready_at' => P::date($o->ready_at),
            ]),
            'installations' => $map('installationOrders', fn ($o) => [
                'id' => $o->id, 'folio' => $o->folio, 'status' => P::enum($o->status),
                'scheduled_start' => P::date($o->scheduled_start), 'completed_at' => P::date($o->completed_at),
                'client_conformity' => P::enum($o->client_conformity),
            ]),
            'incidents' => $map('incidents', fn ($i) => [
                'id' => $i->id, 'folio' => $i->folio, 'title' => $i->title, 'status' => P::enum($i->status),
                'severity' => P::enum($i->severity), 'created_at' => P::date($i->created_at),
            ]),
            'warranties' => $map('warranties', fn ($w) => [
                'id' => $w->id, 'folio' => $w->folio, 'type' => P::enum($w->type), 'status' => P::enum($w->status),
                'starts_on' => P::day($w->starts_on), 'ends_on' => P::day($w->ends_on),
            ]),
        ];
    }

    /**
     * Lecturas de módulos paralelos: si aún no existen sus modelos, la
     * pestaña se muestra vacía en lugar de romper el detalle.
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T|null
     */
    private function safe(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
