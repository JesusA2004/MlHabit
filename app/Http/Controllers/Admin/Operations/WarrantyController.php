<?php

namespace App\Http\Controllers\Admin\Operations;

use App\Enums\ProjectStatus;
use App\Enums\WarrantyClaimStatus;
use App\Enums\WarrantyStatus;
use App\Enums\WarrantyType;
use App\Http\Controllers\Controller;
use App\Http\Presenters\Operations\OpsPresenter;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\Warranty;
use App\Models\WarrantyClaim;
use App\Services\Operations\Attachments;
use App\Services\Operations\WarrantyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** /admin/garantias — registro por tipo (sin términos fijos), reclamos y vencimientos. */
class WarrantyController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Warranty::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::enum(WarrantyStatus::class)],
            'tipo' => ['nullable', Rule::enum(WarrantyType::class)],
            'proyecto' => ['nullable', 'integer'],
        ]);

        $warranties = Warranty::query()
            ->with(['project.customer', 'supplier:id,name'])
            ->withCount(['claims as open_claims_count' => fn ($q) => $q->whereIn('status', WarrantyClaimStatus::openValues())])
            ->when($filters['estado'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['tipo'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($filters['proyecto'] ?? null, fn ($q, $id) => $q->where('project_id', $id))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('folio', 'like', "%{$term}%")
                ->orWhereHas('project', fn ($p) => $p->where('folio', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%"))))
            ->orderByDesc('open_claims_count')
            ->orderByRaw("status = 'active' desc")
            ->orderBy('ends_on')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Warranty $w) => OpsPresenter::warranty($w));

        $openClaims = WarrantyClaim::query()
            ->with(['warranty.project'])
            ->whereIn('status', WarrantyClaimStatus::openValues())
            ->oldest()
            ->limit(20)
            ->get()
            ->map(fn (WarrantyClaim $c) => [
                'id' => $c->id,
                'folio' => $c->folio,
                'warrantyId' => $c->warranty_id,
                'warrantyFolio' => $c->warranty->folio,
                'type' => $c->warranty->type->toArray(),
                'project' => $c->warranty->project->folio.' · '.$c->warranty->project->name,
                'status' => $c->status->toArray(),
                'createdAt' => $c->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/garantias/Index', [
            'warranties' => $warranties,
            'openClaims' => $openClaims,
            'filters' => $filters,
            'expiringSoon' => Warranty::query()->where('status', WarrantyStatus::Active->value)->whereBetween('ends_on', [now()->toDateString(), now()->addDays(30)->toDateString()])->count(),
            'options' => $this->options($request->user()->can('warranties.manage')),
        ]);
    }

    /** Registra una o varias garantías (una por tipo) para un proyecto completado. */
    public function store(Request $request, WarrantyService $service): RedirectResponse
    {
        Gate::authorize('create', Warranty::class);

        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'warranties' => ['required', 'array', 'min:1', 'max:3'],
            'warranties.*.type' => ['required', 'distinct', Rule::enum(WarrantyType::class)],
            'warranties.*.supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'warranties.*.starts_on' => ['required', 'date'],
            'warranties.*.ends_on' => ['required', 'date', 'after:warranties.*.starts_on'],
            'warranties.*.scope' => ['required', 'string', 'max:5000'],
            'warranties.*.exclusions' => ['nullable', 'string', 'max:5000'],
        ], [
            'warranties.*.type.distinct' => 'Registra cada tipo de garantía una sola vez.',
            'warranties.*.ends_on.after' => 'El fin de la vigencia debe ser posterior al inicio.',
            'warranties.*.scope.required' => 'Describe qué cubre la garantía.',
        ]);

        $project = Project::query()->whereKey($data['project_id'])->firstOrFail();
        $created = $service->register($project, $data['warranties'], $request->user());

        return to_route('admin.garantias.index', ['proyecto' => $project->id])
            ->with('success', count($created) === 1 ? 'Garantía registrada; avisamos al cliente.' : 'Garantías registradas; avisamos al cliente.');
    }

    public function show(Request $request, Warranty $warranty): Response
    {
        Gate::authorize('view', $warranty);
        $warranty->load(['project.customer', 'supplier:id,name', 'claims.incident', 'claims.reporter:id,name']);

        return Inertia::render('admin/garantias/Show', [
            'warranty' => OpsPresenter::warranty($warranty, detail: true),
            'claimStatuses' => WarrantyClaimStatus::options(),
            'projectIncidents' => DB::table('incidents')->where('project_id', $warranty->project_id)->orderByDesc('id')->limit(50)->get(['id', 'folio', 'title'])
                ->map(fn ($i) => ['value' => $i->id, 'label' => "{$i->folio} · {$i->title}"])->all(),
            'options' => $this->options($request->user()->can('warranties.manage')),
            'canManage' => $request->user()->can('warranties.manage'),
        ]);
    }

    public function update(Request $request, Warranty $warranty, WarrantyService $service): RedirectResponse
    {
        Gate::authorize('update', $warranty);
        $data = $request->validate([
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'scope' => ['required', 'string', 'max:5000'],
            'exclusions' => ['nullable', 'string', 'max:5000'],
        ]);
        $service->update($warranty, $data);

        return back()->with('success', 'Garantía actualizada.');
    }

    public function void(Request $request, Warranty $warranty, WarrantyService $service): RedirectResponse
    {
        Gate::authorize('update', $warranty);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $service->void($warranty, $data['reason']);

        return back()->with('success', 'Garantía anulada.');
    }

    public function storeClaim(Request $request, Warranty $warranty, WarrantyService $service, Attachments $attachments): RedirectResponse
    {
        Gate::authorize('update', $warranty);
        $data = $request->validate([
            'description' => ['required', 'string', 'max:5000'],
            'incident_id' => ['nullable', 'integer', Rule::exists('incidents', 'id')->where('project_id', $warranty->project_id)],
            'media_ids' => ['array', 'max:20'],
            'media_ids.*' => ['integer'],
        ]);

        $claim = DB::transaction(function () use ($warranty, $data, $service, $attachments, $request) {
            $claim = $service->openClaim($warranty, $data, $request->user());
            $attachments->attach($claim, $data['media_ids'] ?? [], 'evidence', $request->user());

            return $claim;
        });

        return back()->with('success', "Reclamo {$claim->folio} abierto; el proyecto pasó a atención en garantía.");
    }

    public function updateClaim(Request $request, Warranty $warranty, WarrantyClaim $claim, WarrantyService $service): RedirectResponse
    {
        Gate::authorize('update', $warranty);
        abort_unless($claim->warranty_id === $warranty->id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::enum(WarrantyClaimStatus::class)],
            'resolution' => ['nullable', 'string', 'max:5000'],
        ]);
        $service->updateClaim($claim, WarrantyClaimStatus::from($data['status']), $data['resolution'] ?? null, $request->user());

        return back()->with('success', "Reclamo {$claim->folio} actualizado.");
    }

    public function attachClaimEvidence(Request $request, Warranty $warranty, WarrantyClaim $claim, Attachments $attachments): RedirectResponse
    {
        Gate::authorize('update', $warranty);
        abort_unless($claim->warranty_id === $warranty->id, 404);
        $data = $request->validate(['media_ids' => ['required', 'array', 'min:1', 'max:20'], 'media_ids.*' => ['integer']]);
        $attachments->attach($claim, $data['media_ids'], 'evidence', $request->user());

        return back()->with('success', 'Evidencia agregada.');
    }

    /** @return array<string, mixed> */
    private function options(bool $manage): array
    {
        $base = ['types' => WarrantyType::options(), 'statuses' => WarrantyStatus::options()];

        if (! $manage) {
            return $base;
        }

        return [
            ...$base,
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->all(),
            'projects' => Project::query()->with('customer')
                ->whereIn('status', [ProjectStatus::Completed->value, ProjectStatus::WarrantyService->value])
                ->latest('completed_at')->limit(200)->get()
                ->map(fn (Project $p) => [...OpsPresenter::project($p), 'completedAt' => $p->completed_at?->toDateString()])->values()->all(),
        ];
    }
}
