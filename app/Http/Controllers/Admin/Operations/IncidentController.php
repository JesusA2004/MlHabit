<?php

namespace App\Http\Controllers\Admin\Operations;

use App\Enums\IncidentCategory;
use App\Enums\IncidentOrigin;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\ResponsibleParty;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Presenters\Operations\OpsPresenter;
use App\Http\Requests\Operations\IncidentRequest;
use App\Models\Incident;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Operations\Attachments;
use App\Services\Operations\IncidentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** /admin/incidencias — registro, plan de acción, responsables y resolución. */
class IncidentController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Incident::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'string', 'in:abiertas,'.implode(',', array_column(IncidentStatus::cases(), 'value'))],
            'severidad' => ['nullable', Rule::enum(IncidentSeverity::class)],
            'categoria' => ['nullable', Rule::enum(IncidentCategory::class)],
            'proyecto' => ['nullable', 'integer'],
            'mias' => ['nullable', 'boolean'],
        ]);
        $status = $filters['estado'] ?? 'abiertas';

        $incidents = Incident::query()
            ->with(['project.customer', 'assignee:id,name'])
            ->when($status === 'abiertas', fn ($q) => $q->open(), fn ($q) => $q->where('status', $status))
            ->when($filters['severidad'] ?? null, fn ($q, $s) => $q->where('severity', $s))
            ->when($filters['categoria'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->when($filters['proyecto'] ?? null, fn ($q, $id) => $q->where('project_id', $id))
            ->when($filters['mias'] ?? false, fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('folio', 'like', "%{$term}%")->orWhere('title', 'like', "%{$term}%")
                ->orWhereHas('project', fn ($p) => $p->where('folio', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%"))))
            ->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")
            ->orderByRaw('due_on is null, due_on')
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Incident $i) => OpsPresenter::incident($i));

        $counts = Incident::query()->open()->select('severity', DB::raw('count(*) as total'))->groupBy('severity')->pluck('total', 'severity');

        return Inertia::render('admin/incidencias/Index', [
            'incidents' => $incidents,
            'filters' => [...$filters, 'estado' => $status],
            'openBySeverity' => collect(IncidentSeverity::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($counts[$s->value] ?? 0)]),
            'options' => $this->options($request->user()->can('incidents.manage')),
        ]);
    }

    public function store(IncidentRequest $request, IncidentService $service, Attachments $attachments): RedirectResponse
    {
        $project = Project::query()->whereKey($request->integer('project_id'))->firstOrFail();
        $service->ensureProjectAccepts($project);

        $incident = DB::transaction(function () use ($request, $service, $attachments) {
            $incident = $service->create($request->safe()->except('media_ids'), $request->user(), IncidentOrigin::Internal);
            $attachments->attach($incident, $request->validated('media_ids', []), 'evidence', $request->user());

            return $incident;
        });

        return to_route('admin.incidencias.show', $incident)->with('success', "Incidencia {$incident->folio} registrada.");
    }

    public function show(Request $request, Incident $incident): Response
    {
        Gate::authorize('view', $incident);
        $incident->load(['project.customer', 'assignee:id,name']);

        $timeline = DB::table('activity_logs')
            ->leftJoin('users', 'users.id', '=', 'activity_logs.user_id')
            ->where('subject_type', Incident::class)
            ->where('subject_id', $incident->id)
            ->orderByDesc('activity_logs.id')
            ->limit(30)
            ->get(['activity_logs.id', 'activity_logs.action', 'activity_logs.created_at', 'users.name as actor']);

        return Inertia::render('admin/incidencias/Show', [
            'incident' => OpsPresenter::incident($incident, detail: true),
            'activity' => $timeline->map(fn ($row) => ['id' => $row->id, 'action' => $row->action, 'actor' => $row->actor, 'date' => $row->created_at])->all(),
            'options' => $this->options($request->user()->can('incidents.manage'), $incident->project_id),
            'canManage' => $request->user()->can('incidents.manage'),
        ]);
    }

    public function update(IncidentRequest $request, Incident $incident, IncidentService $service): RedirectResponse
    {
        $service->update($incident, $request->safe()->except('media_ids'));

        return back()->with('success', 'Incidencia actualizada.');
    }

    public function transition(Request $request, Incident $incident, IncidentService $service): RedirectResponse
    {
        Gate::authorize('update', $incident);
        $data = $request->validate([
            'status' => ['required', Rule::enum(IncidentStatus::class)],
            'resolution' => ['nullable', 'string', 'max:5000'],
        ]);

        $to = IncidentStatus::from($data['status']);
        $service->transition($incident, $to, $request->user(), $data['resolution'] ?? null);

        return back()->with('success', "Incidencia {$incident->folio}: {$to->label()}.");
    }

    public function attachEvidence(Request $request, Incident $incident, Attachments $attachments): RedirectResponse
    {
        Gate::authorize('update', $incident);
        $data = $request->validate(['media_ids' => ['required', 'array', 'min:1', 'max:20'], 'media_ids.*' => ['integer']]);
        $attachments->attach($incident, $data['media_ids'], 'evidence', $request->user());

        return back()->with('success', 'Evidencia agregada.');
    }

    public function detachEvidence(Incident $incident, int $media, Attachments $attachments): RedirectResponse
    {
        Gate::authorize('update', $incident);
        $attachments->detach($incident, $media, 'evidence');

        return back()->with('success', 'Evidencia quitada.');
    }

    /** @return array<string, mixed> */
    private function options(bool $manage, ?int $projectId = null): array
    {
        $base = [
            'categories' => IncidentCategory::options(),
            'severities' => IncidentSeverity::options(),
            'statuses' => IncidentStatus::options(),
            'origins' => IncidentOrigin::options(),
            'parties' => ResponsibleParty::options(),
        ];

        if (! $manage) {
            return $base;
        }

        return [
            ...$base,
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->all(),
            'assignees' => User::query()->where('is_active', true)
                ->whereNotIn('role', [UserRole::Customer->value])
                ->orderBy('name')->get(['id', 'name', 'role'])
                ->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name, 'hint' => $u->role->label()])->all(),
            'projectOrders' => $projectId ? [
                'installation' => DB::table('installation_orders')->where('project_id', $projectId)->get(['id', 'folio'])->map(fn ($o) => ['value' => $o->id, 'label' => $o->folio])->all(),
                'fabrication' => DB::table('fabrication_orders')->where('project_id', $projectId)->get(['id', 'folio'])->map(fn ($o) => ['value' => $o->id, 'label' => $o->folio])->all(),
            ] : null,
        ];
    }
}
