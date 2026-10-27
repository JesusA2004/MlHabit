<?php

namespace App\Http\Controllers\Admin\Operations;

use App\Enums\RateCardStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\Operations\OpsPresenter;
use App\Http\Requests\Operations\RateCardRequest;
use App\Models\InstallationOrder;
use App\Models\RateCard;
use App\Models\Supplier;
use App\Services\Operations\RateCardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** /admin/tarifarios — tarifarios versionados (borrador → vigente → retirado). */
class RateCardController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', RateCard::class);

        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $cards = RateCard::query()
            ->with('supplier:id,name,type,status,rating,lead_time_days,email,phone')
            ->withCount('items')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->orderBy('name')
            ->orderByDesc('version')
            ->get();

        // Agrupa por nombre: la versión vigente arriba, borrador e historial debajo.
        $groups = $cards->groupBy('name')->map(fn ($versions, $name) => [
            'name' => $name,
            'active' => ($a = $versions->firstWhere('status', RateCardStatus::Active)) ? OpsPresenter::rateCard($a) : null,
            'draft' => ($d = $versions->firstWhere('status', RateCardStatus::Draft)) ? OpsPresenter::rateCard($d) : null,
            'versions' => $versions->map(fn (RateCard $c) => OpsPresenter::rateCard($c))->values()->all(),
        ])->values()->all();

        return Inertia::render('admin/tarifarios/Index', [
            'groups' => $groups,
            'filters' => $filters,
            'options' => $this->options(),
        ]);
    }

    public function store(RateCardRequest $request, RateCardService $service): RedirectResponse
    {
        $card = $service->createDraft($request->validated(), $request->user());

        return to_route('admin.tarifarios.show', $card)->with('success', 'Borrador creado. Actívalo cuando los precios estén listos.');
    }

    public function show(RateCard $rateCard): Response
    {
        Gate::authorize('view', $rateCard);
        $rateCard->load(['items', 'supplier', 'creator:id,name']);

        $versions = RateCard::query()->where('name', $rateCard->name)->orderByDesc('version')->get();

        return Inertia::render('admin/tarifarios/Show', [
            'rateCard' => [
                ...OpsPresenter::rateCard($rateCard),
                'createdBy' => $rateCard->creator?->name,
                'ordersCount' => InstallationOrder::query()->where('rate_card_id', $rateCard->id)->count(),
            ],
            'versions' => $versions->map(fn (RateCard $c) => [
                'id' => $c->id,
                'version' => $c->version,
                'status' => $c->status->toArray(),
                'activatedAt' => $c->activated_at?->toIso8601String(),
            ])->all(),
            'hasDraft' => $versions->contains(fn (RateCard $c) => $c->status === RateCardStatus::Draft),
            'options' => $this->options(),
        ]);
    }

    public function update(RateCardRequest $request, RateCard $rateCard, RateCardService $service): RedirectResponse
    {
        $service->updateDraft($rateCard, $request->validated());

        return back()->with('success', 'Borrador guardado.');
    }

    public function destroy(RateCard $rateCard, RateCardService $service): RedirectResponse
    {
        Gate::authorize('update', $rateCard);
        if ($error = $this->attempt(fn () => $service->deleteDraft($rateCard))) {
            return $error;
        }

        return to_route('admin.tarifarios.index')->with('success', 'Borrador eliminado.');
    }

    public function activate(Request $request, RateCard $rateCard, RateCardService $service): RedirectResponse
    {
        Gate::authorize('update', $rateCard);
        if ($error = $this->attempt(fn () => $service->activate($rateCard, $request->user()))) {
            return $error;
        }

        return back()->with('success', "Versión {$rateCard->version} vigente. Las órdenes nuevas usarán estos precios.");
    }

    public function newVersion(Request $request, RateCard $rateCard, RateCardService $service): RedirectResponse
    {
        Gate::authorize('update', $rateCard);
        $draft = null;
        if ($error = $this->attempt(function () use ($service, $rateCard, $request, &$draft) {
            $draft = $service->newVersion($rateCard, $request->user());
        })) {
            return $error;
        }
        /** @var RateCard $draft */

        return to_route('admin.tarifarios.show', $draft)->with('success', "Borrador de la versión {$draft->version} listo para editar.");
    }

    /**
     * Ejecuta una acción sin formulario: si el servicio rechaza la operación,
     * conserva los errores de validación y además los muestra como aviso.
     */
    private function attempt(callable $action): ?RedirectResponse
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->with('error', collect($e->errors())->flatten()->first());
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'suppliers' => Supplier::query()->installers()->orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->all(),
            'statuses' => RateCardStatus::options(),
        ];
    }
}
