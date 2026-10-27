<?php

namespace App\Http\Controllers\Admin\Operations;

use App\Enums\CheckResult;
use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\InstallationStatus;
use App\Enums\QualityCriterion;
use App\Enums\QualityResult;
use App\Http\Controllers\Controller;
use App\Http\Presenters\Operations\OpsPresenter;
use App\Models\InstallationOrder;
use App\Models\InstallationOrderItem;
use App\Models\QualityInspection;
use App\Services\Operations\QualityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** /admin/calidad — cola de revisión, dictamen por partida y de la orden. */
class QualityController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('quality.view');

        $queue = InstallationOrder::query()
            ->with(['project.customer', 'supplier', 'leadInstaller:id,name'])
            ->withCount(['items', 'incidents as open_incidents_count' => fn ($q) => $q->open()])
            ->where('status', InstallationStatus::QualityReview->value)
            ->orderBy('client_signed_at')
            ->orderBy('evidence_submitted_at')
            ->get()
            ->map(fn (InstallationOrder $o) => [
                ...OpsPresenter::installationRow($o),
                'openIncidents' => (int) $o->open_incidents_count,
                'clientSignedAt' => $o->client_signed_at?->toIso8601String(),
                'waitingSince' => ($o->client_signed_at ?? $o->evidence_submitted_at)?->toIso8601String(),
            ]);

        $awaitingClient = InstallationOrder::query()
            ->with(['project.customer', 'leadInstaller:id,name'])
            ->where('status', InstallationStatus::ClientReview->value)
            ->orderBy('evidence_submitted_at')
            ->limit(30)
            ->get()
            ->map(fn (InstallationOrder $o) => OpsPresenter::installationRow($o));

        $recent = QualityInspection::query()
            ->with(['order.project', 'inspector:id,name'])
            ->whereNull('installation_order_item_id')
            ->where('stage', 'quality')
            ->where('result', '!=', QualityResult::Pending->value)
            ->latest('inspected_at')
            ->limit(15)
            ->get()
            ->map(fn (QualityInspection $i) => [
                'id' => $i->id,
                'orderId' => $i->installation_order_id,
                'folio' => $i->order->folio,
                'project' => $i->order->project->folio.' · '.$i->order->project->name,
                'result' => $i->result->toArray(),
                'inspector' => $i->inspector?->name,
                'inspectedAt' => $i->inspected_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/calidad/Index', [
            'queue' => $queue,
            'awaitingClient' => $awaitingClient,
            'recent' => $recent,
        ]);
    }

    public function show(Request $request, InstallationOrder $installationOrder): Response
    {
        Gate::authorize('quality.view');

        return Inertia::render('admin/calidad/Show', [
            'order' => OpsPresenter::installation($installationOrder),
            'incidents' => $installationOrder->incidents()->latest()->get()->map(fn ($i) => [
                'id' => $i->id, 'folio' => $i->folio, 'title' => $i->title, 'status' => $i->status->toArray(), 'severity' => $i->severity->toArray(), 'origin' => $i->origin->toArray(),
            ])->all(),
            'criteria' => collect(QualityCriterion::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])->all(),
            'checkResults' => CheckResult::options(),
            'categories' => IncidentCategory::options(),
            'severities' => IncidentSeverity::options(),
            'canReview' => $request->user()->can('quality.review') && $installationOrder->status === InstallationStatus::QualityReview,
        ]);
    }

    public function reviewItem(Request $request, InstallationOrder $installationOrder, InstallationOrderItem $item, QualityService $quality): RedirectResponse
    {
        Gate::authorize('review', $installationOrder);
        abort_unless($item->installation_order_id === $installationOrder->id, 404);

        $data = $request->validate([
            'result' => ['required', Rule::in([QualityResult::Approved->value, QualityResult::RequiresFixes->value])],
            'observations' => ['nullable', 'string', 'max:2000'],
            'client_contacted' => ['boolean'],
            'checklist' => ['required', 'array'],
            'checklist.*.criterion' => ['required', Rule::enum(QualityCriterion::class)],
            'checklist.*.result' => ['required', Rule::enum(CheckResult::class)],
            'checklist.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $quality->reviewItem(
            $installationOrder, $item, $request->user(), QualityResult::from($data['result']),
            $data['checklist'], $data['observations'] ?? null, (bool) ($data['client_contacted'] ?? false),
        );

        return back()->with('success', "Partida {$item->code} revisada.");
    }

    public function approve(Request $request, InstallationOrder $installationOrder, QualityService $quality): RedirectResponse
    {
        Gate::authorize('review', $installationOrder);
        $data = $request->validate([
            'observations' => ['nullable', 'string', 'max:2000'],
            'client_contacted' => ['boolean'],
        ]);

        $quality->approve($installationOrder, $request->user(), $data['observations'] ?? null, (bool) ($data['client_contacted'] ?? false));

        return to_route('admin.calidad.index')->with('success', "Instalación {$installationOrder->folio} aprobada.");
    }

    public function requireFixes(Request $request, InstallationOrder $installationOrder, QualityService $quality): RedirectResponse
    {
        Gate::authorize('review', $installationOrder);
        $data = $request->validate([
            'observations' => ['required', 'string', 'max:2000'],
            'client_contacted' => ['boolean'],
            'issues' => ['required', 'array', 'min:1', 'max:30'],
            'issues.*.title' => ['required', 'string', 'max:200'],
            'issues.*.description' => ['required', 'string', 'max:2000'],
            'issues.*.category' => ['required', Rule::enum(IncidentCategory::class)],
            'issues.*.severity' => ['required', Rule::enum(IncidentSeverity::class)],
            'issues.*.item_id' => ['nullable', 'integer', Rule::exists('installation_order_items', 'id')->where('installation_order_id', $installationOrder->id)],
            'issues.*.visible_to_client' => ['boolean'],
        ], ['issues.required' => 'Agrega al menos un pendiente para el instalador.']);

        $quality->requireFixes($installationOrder, $request->user(), $data['observations'], $data['issues'], (bool) ($data['client_contacted'] ?? false));

        return to_route('admin.calidad.index')->with('success', "Se enviaron correcciones para {$installationOrder->folio}.");
    }
}
