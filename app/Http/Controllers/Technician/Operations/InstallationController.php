<?php

namespace App\Http\Controllers\Technician\Operations;

use App\Enums\CheckResult;
use App\Enums\InstallationItemStatus;
use App\Enums\InstallationStatus;
use App\Enums\QualityCriterion;
use App\Http\Controllers\Controller;
use App\Http\Presenters\Operations\OpsPresenter;
use App\Models\InstallationOrder;
use App\Models\InstallationOrderItem;
use App\Services\Operations\Attachments;
use App\Services\Operations\InstallationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /tecnico/instalaciones — app de campo del instalador líder. Solo ve y
 * opera SUS órdenes (InstallationOrderPolicy::execute, protección IDOR).
 */
class InstallationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('installations.execute'), 403);

        $orders = InstallationOrder::query()
            ->with(['project.customer', 'project.address'])
            ->withCount([
                'items' => fn ($q) => $q->where('status', '!=', InstallationItemStatus::Rejected->value),
                'items as done_count' => fn ($q) => $q->where('status', InstallationItemStatus::Done->value),
            ])
            ->where('lead_installer_id', $request->user()->id)
            ->whereNotIn('status', [InstallationStatus::Draft->value, InstallationStatus::Cancelled->value])
            ->orderByRaw("FIELD(status, 'in_progress', 'scheduled', 'client_review', 'evidence_submitted', 'quality_review', 'approved', 'payment_authorized', 'paid')")
            ->orderBy('scheduled_start')
            ->limit(100)
            ->get();

        $row = fn (InstallationOrder $o) => [
            ...OpsPresenter::installationRow($o),
            'total' => null,
            'address' => $o->project->address ? "{$o->project->address->street} {$o->project->address->exterior_number}, {$o->project->address->neighborhood}" : null,
        ];

        $active = [InstallationStatus::Scheduled, InstallationStatus::InProgress];

        return Inertia::render('tecnico/instalaciones/Index', [
            'active' => $orders->filter(fn ($o) => in_array($o->status, $active, true))->map($row)->values(),
            'history' => $orders->reject(fn ($o) => in_array($o->status, $active, true))->take(30)->map($row)->values(),
        ]);
    }

    public function show(Request $request, InstallationOrder $installationOrder): Response
    {
        Gate::authorize('execute', $installationOrder);

        return Inertia::render('tecnico/instalaciones/Show', [
            'order' => OpsPresenter::installation($installationOrder, 'installer'),
            'criteria' => collect(QualityCriterion::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])->all(),
            'checkResults' => CheckResult::options(),
            'rateItems' => $installationOrder->rateCard->items()->get()->map(fn ($i) => ['id' => $i->id, 'code' => $i->code, 'description' => $i->description, 'unit' => $i->unit])->all(),
            'maxEvidenceBytes' => Attachments::EVIDENCE_MAX_BYTES,
            'openIncidents' => $installationOrder->incidents()->open()->get()->map(fn ($i) => ['id' => $i->id, 'folio' => $i->folio, 'title' => $i->title, 'description' => $i->description, 'severity' => $i->severity->toArray()])->all(),
        ]);
    }

    public function start(Request $request, InstallationOrder $installationOrder, InstallationService $service): RedirectResponse
    {
        Gate::authorize('execute', $installationOrder);
        $service->start($installationOrder, $request->user());

        return back()->with('success', 'Instalación iniciada. ¡Éxito!');
    }

    public function completeItem(Request $request, InstallationOrder $installationOrder, InstallationOrderItem $item, InstallationService $service): RedirectResponse
    {
        Gate::authorize('execute', $installationOrder);
        abort_unless($item->installation_order_id === $installationOrder->id, 404);

        $data = $request->validate([
            'media_ids' => ['array', 'max:20'],
            'media_ids.*' => ['integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'checklist' => ['required', 'array'],
            'checklist.*.criterion' => ['required', Rule::enum(QualityCriterion::class)],
            'checklist.*.result' => ['required', Rule::enum(CheckResult::class)],
            'checklist.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $service->completeItem($item, $request->user(), $data['media_ids'] ?? [], $data['checklist'], $data['notes'] ?? null);

        return back()->with('success', "Partida {$item->code} terminada.");
    }

    /** El instalador propone un extra en sitio; lo aprueba alguien más. */
    public function storeExtra(Request $request, InstallationOrder $installationOrder, InstallationService $service): RedirectResponse
    {
        Gate::authorize('execute', $installationOrder);

        $data = $request->validate([
            'rate_card_item_id' => ['nullable', 'integer'],
            'description' => ['required', 'string', 'max:300'],
            'unit' => ['required', 'string', 'max:24'],
            'quantity' => ['required', 'string', 'regex:/^\d{1,9}(\.\d{1,3})?$/', 'not_in:0,0.0,0.00,0.000'],
            'unit_price' => ['nullable', 'required_without:rate_card_item_id', 'string', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
        ], [
            'unit_price.required_without' => 'Elige un concepto del tarifario o indica el precio.',
            'quantity.not_in' => 'La cantidad debe ser mayor a cero.',
        ]);

        $service->addExtra($installationOrder, $data, $request->user());

        return back()->with('success', 'Extra enviado a aprobación de la oficina.');
    }

    public function submit(Request $request, InstallationOrder $installationOrder, InstallationService $service): RedirectResponse
    {
        Gate::authorize('execute', $installationOrder);
        $service->submitEvidence($installationOrder, $request->user());

        return back()->with('success', 'Evidencia enviada. Avisamos al cliente para que la revise.');
    }
}
