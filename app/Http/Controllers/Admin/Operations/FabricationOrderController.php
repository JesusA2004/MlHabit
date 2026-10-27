<?php

namespace App\Http\Controllers\Admin\Operations;

use App\Enums\FabricationStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\Operations\OpsPresenter;
use App\Models\FabricationOrder;
use App\Models\Project;
use App\Models\Supplier;
use App\Services\Operations\Attachments;
use App\Services\Operations\FabricationService;
use App\Services\Operations\PayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** /admin/fabricacion — órdenes de fabricación a proveedores. */
class FabricationOrderController extends Controller
{
    private const MONEY = 'regex:/^\d{1,12}(\.\d{1,2})?$/';

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', FabricationOrder::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::enum(FabricationStatus::class)],
            'proveedor' => ['nullable', 'integer'],
        ]);

        $orders = FabricationOrder::query()
            ->with(['project.customer', 'supplier'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('folio', 'like', "%{$term}%")
                ->orWhereHas('project', fn ($p) => $p->where('folio', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%"))))
            ->when($filters['estado'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['proveedor'] ?? null, fn ($q, $id) => $q->where('supplier_id', $id))
            ->orderByRaw("FIELD(status, 'in_production', 'issued', 'draft', 'ready', 'delivered', 'cancelled')")
            ->orderByRaw('due_on is null, due_on')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (FabricationOrder $o) => OpsPresenter::fabrication($o));

        $counts = FabricationOrder::query()->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');

        return Inertia::render('admin/fabricacion/Index', [
            'orders' => $orders,
            'filters' => $filters,
            'counts' => collect(FabricationStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($counts[$s->value] ?? 0)]),
            'overdue' => FabricationOrder::query()->whereNotIn('status', ['ready', 'delivered', 'cancelled'])->whereDate('due_on', '<', now()->toDateString())->count(),
            'options' => $this->options(),
            'eligibleProjects' => $request->user()->can('fabrication.manage') ? $this->eligibleProjects() : [],
        ]);
    }

    public function store(Request $request, FabricationService $service): RedirectResponse
    {
        Gate::authorize('create', FabricationOrder::class);

        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'design_version_id' => ['required', 'integer'],
            'supplier_id' => ['required', 'integer'],
            'cost' => ['nullable', 'string', self::MONEY],
            'due_on' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], ['cost.regex' => 'Escribe un importe válido (máximo 2 decimales).']);

        $project = Project::query()->whereKey($data['project_id'])->firstOrFail();
        $order = $service->create($project, $data, $request->user());

        return to_route('admin.fabricacion.show', $order)->with('success', "Orden {$order->folio} creada como borrador.");
    }

    public function show(FabricationOrder $fabricationOrder): Response
    {
        Gate::authorize('view', $fabricationOrder);
        $order = $fabricationOrder->load(['project.customer', 'supplier', 'creator:id,name', 'payout', 'incidents']);

        $design = DB::table('design_versions')->where('id', $order->design_version_id)
            ->first(['id', 'number', 'title', 'technical_approved_at', 'technical_notes']);

        return Inertia::render('admin/fabricacion/Show', [
            'order' => [
                ...OpsPresenter::fabrication($order),
                'createdBy' => $order->creator?->name,
                'design' => $design ? [
                    'id' => $design->id,
                    'number' => $design->number,
                    'title' => $design->title,
                    'technicalApprovedAt' => $design->technical_approved_at,
                    'technicalNotes' => $design->technical_notes,
                ] : null,
                'documents' => Attachments::presentMany($order->mediaWithRole('document')->get()),
                'incidents' => $order->incidents->map(fn ($i) => ['id' => $i->id, 'folio' => $i->folio, 'title' => $i->title, 'status' => $i->status->toArray(), 'severity' => $i->severity->toArray()])->all(),
                'payout' => $order->payout ? OpsPresenter::payoutRow($order->payout) : null,
            ],
            'options' => $this->options(),
        ]);
    }

    public function update(Request $request, FabricationOrder $fabricationOrder, FabricationService $service): RedirectResponse
    {
        Gate::authorize('update', $fabricationOrder);

        $data = $request->validate([
            'supplier_id' => ['required', 'integer'],
            'cost' => ['nullable', 'string', self::MONEY],
            'due_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], ['cost.regex' => 'Escribe un importe válido (máximo 2 decimales).']);

        $service->update($fabricationOrder, $data);

        return back()->with('success', 'Orden actualizada.');
    }

    public function transition(Request $request, FabricationOrder $fabricationOrder, FabricationService $service): RedirectResponse
    {
        Gate::authorize('update', $fabricationOrder);

        $data = $request->validate([
            'status' => ['required', Rule::enum(FabricationStatus::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $to = FabricationStatus::from($data['status']);
        $service->transition($fabricationOrder, $to, $request->user(), $data['note'] ?? null);

        return back()->with('success', "Orden {$fabricationOrder->folio}: {$to->label()}.");
    }

    public function attachDocuments(Request $request, FabricationOrder $fabricationOrder, Attachments $attachments): RedirectResponse
    {
        Gate::authorize('update', $fabricationOrder);
        $data = $request->validate(['media_ids' => ['required', 'array', 'min:1', 'max:20'], 'media_ids.*' => ['integer']]);
        $attachments->attach($fabricationOrder, $data['media_ids'], 'document', $request->user());

        return back()->with('success', 'Documentos agregados.');
    }

    public function detachDocument(FabricationOrder $fabricationOrder, int $media, Attachments $attachments): RedirectResponse
    {
        Gate::authorize('update', $fabricationOrder);
        $attachments->detach($fabricationOrder, $media, 'document');

        return back()->with('success', 'Documento quitado.');
    }

    public function requestPayout(Request $request, FabricationOrder $fabricationOrder, PayoutService $payouts): RedirectResponse
    {
        Gate::authorize('update', $fabricationOrder);
        $payout = $payouts->requestForFabrication($fabricationOrder, $request->user());

        return back()->with('success', "Pago {$payout->folio} enviado a autorización.");
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'statuses' => FabricationStatus::options(),
            'suppliers' => Supplier::query()->active()->fabricators()->orderBy('name')->get(['id', 'name', 'lead_time_days'])
                ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name, 'leadTimeDays' => $s->lead_time_days])->all(),
        ];
    }

    /**
     * Proyectos autorizados o en fabricación con sus versiones de diseño
     * con aprobación técnica (la regla se valida de nuevo en el servicio).
     *
     * @return list<array<string, mixed>>
     */
    private function eligibleProjects(): array
    {
        $projects = Project::query()
            ->with('customer')
            ->whereIn('status', array_map(fn ($s) => $s->value, FabricationService::PROJECT_STATUSES))
            ->latest()
            ->limit(200)
            ->get();

        $designs = DB::table('design_versions')
            ->whereIn('project_id', $projects->pluck('id'))
            ->whereNotNull('technical_approved_at')
            ->orderByDesc('number')
            ->get(['id', 'project_id', 'number', 'title', 'technical_approved_at'])
            ->groupBy('project_id');

        return array_values($projects->map(fn (Project $p) => [
            ...OpsPresenter::project($p),
            'designs' => ($designs[$p->id] ?? collect())->map(fn ($d) => [
                'id' => $d->id,
                'label' => "Versión {$d->number} · {$d->title}",
                'approvedAt' => $d->technical_approved_at,
            ])->values()->all(),
        ])->all());
    }
}
