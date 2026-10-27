<?php

namespace App\Http\Controllers\Admin\Operations;

use App\Enums\InstallationItemStatus;
use App\Enums\InstallationStatus;
use App\Enums\RateCardStatus;
use App\Enums\TechnicianStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Presenters\Operations\OpsPresenter;
use App\Models\InstallationOrder;
use App\Models\InstallationOrderItem;
use App\Models\Project;
use App\Models\RateCard;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Operations\InstallationService;
use App\Services\Operations\PayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** /admin/instalaciones — planeación, partidas congeladas, extras, programación y cobro. */
class InstallationOrderController extends Controller
{
    private const QTY = 'regex:/^\d{1,9}(\.\d{1,3})?$/';

    private const MONEY = 'regex:/^\d{1,12}(\.\d{1,2})?$/';

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', InstallationOrder::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::enum(InstallationStatus::class)],
            'instalador' => ['nullable', 'integer'],
        ]);

        $orders = InstallationOrder::query()
            ->with(['project.customer', 'supplier', 'leadInstaller:id,name'])
            ->withCount([
                'items',
                'items as done_count' => fn ($q) => $q->where('status', InstallationItemStatus::Done->value),
                'items as pending_extras_count' => fn ($q) => $q->where('is_extra', true)->whereNull('extra_approved_at')->where('status', '!=', InstallationItemStatus::Rejected->value),
            ])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('folio', 'like', "%{$term}%")
                ->orWhereHas('project', fn ($p) => $p->where('folio', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%"))))
            ->when($filters['estado'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['instalador'] ?? null, fn ($q, $id) => $q->where('lead_installer_id', $id))
            ->orderByRaw("FIELD(status, 'in_progress', 'scheduled', 'client_review', 'evidence_submitted', 'quality_review', 'draft', 'approved', 'payment_authorized', 'paid', 'cancelled')")
            ->orderByRaw('scheduled_start is null, scheduled_start')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (InstallationOrder $o) => OpsPresenter::installationRow($o));

        $counts = InstallationOrder::query()->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');

        return Inertia::render('admin/instalaciones/Index', [
            'orders' => $orders,
            'filters' => $filters,
            'counts' => collect(InstallationStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($counts[$s->value] ?? 0)]),
            'pendingExtras' => InstallationOrderItem::query()->where('is_extra', true)->whereNull('extra_approved_at')->where('status', '!=', 'rejected')->count(),
            'options' => $this->options($request->user()->can('installations.manage')),
        ]);
    }

    public function store(Request $request, InstallationService $service): RedirectResponse
    {
        Gate::authorize('create', InstallationOrder::class);

        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'supplier_id' => ['required', 'integer'],
            'rate_card_id' => ['required', 'integer'],
            'lead_installer_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.rate_card_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'string', self::QTY, 'not_in:0,0.0,0.00,0.000'],
        ], $this->messages());

        $order = $service->create(Project::query()->whereKey($data['project_id'])->firstOrFail(), $data, $request->user());

        return to_route('admin.instalaciones.show', $order)->with('success', "Orden {$order->folio} creada. Precios congelados del tarifario vigente.");
    }

    public function show(Request $request, InstallationOrder $installationOrder): Response
    {
        Gate::authorize('view', $installationOrder);

        $rateItems = $installationOrder->status->isEditable() || $request->user()->can('installations.manage')
            ? $installationOrder->rateCard->items()->get()->map(fn ($i) => ['id' => $i->id, 'code' => $i->code, 'description' => $i->description, 'unit' => $i->unit, 'unitPrice' => $i->unit_price])->all()
            : [];

        return Inertia::render('admin/instalaciones/Show', [
            'order' => OpsPresenter::installation($installationOrder),
            'incidents' => $installationOrder->incidents()->latest()->get()->map(fn ($i) => [
                'id' => $i->id, 'folio' => $i->folio, 'title' => $i->title, 'status' => $i->status->toArray(), 'severity' => $i->severity->toArray(), 'origin' => $i->origin->toArray(),
            ])->all(),
            'rateItems' => $rateItems,
            'installers' => $this->installers(),
            'can' => [
                'manage' => $request->user()->can('installations.manage'),
                'decideExtras' => $request->user()->can('installations.manage') && ! $installationOrder->isLedBy($request->user()),
                'requestPayout' => $request->user()->can('installations.manage') && $installationOrder->status === InstallationStatus::Approved && $installationOrder->payout === null,
                'review' => $request->user()->can('quality.review'),
            ],
        ]);
    }

    public function update(Request $request, InstallationOrder $installationOrder, InstallationService $service): RedirectResponse
    {
        Gate::authorize('update', $installationOrder);
        $data = $request->validate([
            'lead_installer_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $service->update($installationOrder, $data);

        return back()->with('success', 'Orden actualizada.');
    }

    public function addItems(Request $request, InstallationOrder $installationOrder, InstallationService $service): RedirectResponse
    {
        Gate::authorize('update', $installationOrder);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.rate_card_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'string', self::QTY, 'not_in:0,0.0,0.00,0.000'],
        ], $this->messages());
        $service->addItems($installationOrder, $data['items']);

        return back()->with('success', 'Partidas agregadas.');
    }

    public function updateItem(Request $request, InstallationOrder $installationOrder, InstallationOrderItem $item, InstallationService $service): RedirectResponse
    {
        Gate::authorize('update', $installationOrder);
        abort_unless($item->installation_order_id === $installationOrder->id, 404);
        $data = $request->validate(['quantity' => ['required', 'string', self::QTY, 'not_in:0,0.0,0.00,0.000']], $this->messages());
        $service->updateQuantity($item, $data['quantity']);

        return back()->with('success', 'Cantidad actualizada.');
    }

    public function destroyItem(InstallationOrder $installationOrder, InstallationOrderItem $item, InstallationService $service): RedirectResponse
    {
        Gate::authorize('update', $installationOrder);
        abort_unless($item->installation_order_id === $installationOrder->id, 404);
        $service->removeItem($item);

        return back()->with('success', 'Partida eliminada.');
    }

    public function storeExtra(Request $request, InstallationOrder $installationOrder, InstallationService $service): RedirectResponse
    {
        Gate::authorize('update', $installationOrder);
        $service->addExtra($installationOrder, $this->validateExtra($request), $request->user());

        return back()->with('success', 'Extra registrado; queda pendiente de aprobación.');
    }

    public function approveExtra(Request $request, InstallationOrder $installationOrder, InstallationOrderItem $item, InstallationService $service): RedirectResponse
    {
        Gate::authorize('update', $installationOrder);
        abort_unless($item->installation_order_id === $installationOrder->id, 404);
        $service->decideExtra($item, $request->user(), true);

        return back()->with('success', 'Extra aprobado; ya suma al total.');
    }

    public function rejectExtra(Request $request, InstallationOrder $installationOrder, InstallationOrderItem $item, InstallationService $service): RedirectResponse
    {
        Gate::authorize('update', $installationOrder);
        abort_unless($item->installation_order_id === $installationOrder->id, 404);
        $service->decideExtra($item, $request->user(), false);

        return back()->with('success', 'Extra rechazado.');
    }

    public function schedule(Request $request, InstallationOrder $installationOrder, InstallationService $service): RedirectResponse
    {
        Gate::authorize('update', $installationOrder);
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
            'end_time' => ['required', 'date_format:H:i'],
        ]);

        $tz = 'America/Mexico_City';
        $start = Carbon::createFromFormat('Y-m-d H:i', "{$data['date']} {$data['start_time']}", $tz);
        $end = Carbon::createFromFormat('Y-m-d H:i', ($data['end_date'] ?? $data['date'])." {$data['end_time']}", $tz);

        if ($end->lessThanOrEqualTo($start)) {
            return back()->withErrors(['end_time' => 'La hora de término debe ser posterior al inicio.']);
        }

        $service->schedule($installationOrder, $start->utc(), $end->utc(), $request->user());

        return back()->with('success', 'Instalación programada; avisamos al cliente.');
    }

    public function cancel(Request $request, InstallationOrder $installationOrder, InstallationService $service): RedirectResponse
    {
        Gate::authorize('update', $installationOrder);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $service->cancel($installationOrder, $request->user(), $data['reason']);

        return back()->with('success', 'Orden cancelada.');
    }

    public function skipClientReview(Request $request, InstallationOrder $installationOrder, InstallationService $service): RedirectResponse
    {
        Gate::authorize('update', $installationOrder);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $service->skipClientReview($installationOrder, $request->user(), $data['reason']);

        return back()->with('success', 'Orden enviada a revisión de calidad.');
    }

    public function requestPayout(Request $request, InstallationOrder $installationOrder, PayoutService $payouts): RedirectResponse
    {
        Gate::authorize('update', $installationOrder);
        $payout = $payouts->requestForInstallation($installationOrder, $request->user());

        return back()->with('success', "Pago {$payout->folio} enviado a autorización.");
    }

    /** @return array<string, mixed> */
    private function validateExtra(Request $request): array
    {
        $request->merge(['unit_price' => is_string($request->input('unit_price')) ? str_replace([',', '$', ' '], '', $request->input('unit_price')) : $request->input('unit_price')]);

        return $request->validate([
            'rate_card_item_id' => ['nullable', 'integer'],
            'code' => ['nullable', 'string', 'max:40'],
            'description' => ['required', 'string', 'max:300'],
            'unit' => ['required', 'string', 'max:24'],
            'quantity' => ['required', 'string', self::QTY, 'not_in:0,0.0,0.00,0.000'],
            'unit_price' => ['nullable', 'required_without:rate_card_item_id', 'string', self::MONEY],
        ], $this->messages());
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'items.*.quantity.regex' => 'Cantidad inválida (hasta 3 decimales).',
            'items.*.quantity.not_in' => 'La cantidad debe ser mayor a cero.',
            'quantity.regex' => 'Cantidad inválida (hasta 3 decimales).',
            'quantity.not_in' => 'La cantidad debe ser mayor a cero.',
            'unit_price.regex' => 'Escribe un precio válido (máximo 2 decimales).',
            'unit_price.required_without' => 'Indica el precio unitario o elige un concepto del tarifario.',
        ];
    }

    /**
     * Opciones del formulario de alta (solo para quien gestiona).
     *
     * @return array<string, mixed>
     */
    private function options(bool $manage): array
    {
        $base = ['statuses' => InstallationStatus::options(), 'installers' => $this->installers()];

        if (! $manage) {
            return $base;
        }

        return [
            ...$base,
            'projects' => Project::query()->with('customer')
                ->whereIn('status', array_map(fn ($s) => $s->value, InstallationService::CREATABLE_PROJECT_STATUSES))
                ->latest()->limit(200)->get()
                ->map(fn (Project $p) => OpsPresenter::project($p))->all(),
            'suppliers' => Supplier::query()->active()->installers()->orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->all(),
            'rateCards' => RateCard::query()->where('status', RateCardStatus::Active->value)->with('items')->orderBy('name')->get()->map(fn (RateCard $c) => [
                'id' => $c->id,
                'label' => "{$c->name} · v{$c->version}",
                'supplierId' => $c->supplier_id,
                'mobilizationFee' => $c->mobilization_fee,
                'minimumCharge' => $c->minimum_charge,
                'items' => $c->items->map(fn ($i) => ['id' => $i->id, 'code' => $i->code, 'description' => $i->description, 'unit' => $i->unit, 'unitPrice' => $i->unit_price])->all(),
            ])->all(),
        ];
    }

    /** @return list<array{value: int, label: string}> */
    private function installers(): array
    {
        return array_values(User::query()
            ->where('role', UserRole::Technician->value)
            ->where('is_active', true)
            ->whereHas('technicianProfile', fn ($q) => $q->where('does_installations', true)->where('status', TechnicianStatus::Active->value))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name])->all());
    }
}
