<?php

namespace App\Http\Controllers\Admin\Projects;

use App\Enums\DesignStatus;
use App\Enums\Permission;
use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\Projects\ProjectPresenter as P;
use App\Http\Requests\Projects\QuoteRequest;
use App\Models\Project;
use App\Models\Quote;
use App\Models\User;
use App\Services\Projects\ContractService;
use App\Services\Projects\QuoteService;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Quote::class);
        $filters = $request->validate([
            'estado' => ['nullable', Rule::enum(QuoteStatus::class)],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $quotes = Quote::query()
            ->with(['project:id,uuid,folio,name,customer_id', 'project.customer:id,first_name,last_name'])
            ->when($filters['estado'] ?? null, fn (Builder $q, string $s) => $q->where('status', $s), fn (Builder $q) => $q->where('status', '!=', QuoteStatus::Superseded->value))
            ->when($filters['q'] ?? null, fn (Builder $q, string $t) => $q->where(fn (Builder $w) => $w->where('folio', 'like', "%{$t}%")->orWhere('title', 'like', "%{$t}%")
                ->orWhereHas('project', fn (Builder $p) => $p->where('folio', 'like', "%{$t}%")->orWhere('name', 'like', "%{$t}%"))))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Quote $q) => [
                ...P::quoteSummary($q),
                'project' => ['uuid' => $q->project->uuid, 'folio' => $q->project->folio, 'name' => $q->project->name, 'customer' => $q->project->customer?->full_name],
            ]);

        return Inertia::render('admin/proyectos/Cotizaciones', [
            'quotes' => $quotes,
            'filters' => ['estado' => $filters['estado'] ?? null, 'q' => $filters['q'] ?? ''],
            'statuses' => array_map(fn (QuoteStatus $s) => $s->toArray(), QuoteStatus::cases()),
        ]);
    }

    public function create(Request $request, Project $project): Response
    {
        Gate::authorize('quotes.manage');

        return $this->builder($request->user(), $project, null);
    }

    public function store(QuoteRequest $request, Project $project, QuoteService $service): RedirectResponse
    {
        $quote = $service->create($project, $request->validated(), $request->user());

        return to_route('admin.cotizaciones.edit', $quote)->with('success', "Cotización {$quote->folio} guardada como borrador.");
    }

    public function edit(Request $request, Quote $quote): Response
    {
        Gate::authorize('view', $quote);

        return $this->builder($request->user(), $quote->project, $quote);
    }

    public function update(QuoteRequest $request, Quote $quote, QuoteService $service): RedirectResponse
    {
        $result = $service->update($quote, $request->validated(), $request->user());

        if ($result->id !== $quote->id) {
            return to_route('admin.cotizaciones.edit', $result)->with('success', "Creamos la versión {$result->version} ({$result->folio}): la anterior ya se había enviado.");
        }

        return back()->with('success', 'Cotización guardada. Totales recalculados.');
    }

    public function send(Request $request, Quote $quote, QuoteService $service): RedirectResponse
    {
        Gate::authorize('quotes.manage');
        $service->send($quote, $request->user());

        return back()->with('success', 'Cotización enviada. El cliente ya puede verla y aceptarla en su portal.');
    }

    /** Aceptación registrada por el equipo a nombre del cliente (con motivo). */
    public function accept(Request $request, Quote $quote, ContractService $contracts): RedirectResponse
    {
        Gate::authorize('quotes.manage');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']], [
            'reason.required' => 'Explica cómo aceptó el cliente (p. ej. «firmó en sucursal el 12/11»).',
        ]);

        $contract = $contracts->acceptQuote($quote, $request->user(), $data['reason'], $request->ip());

        return to_route('admin.proyectos.show', ['project' => $quote->project->uuid, 'tab' => 'contrato'])
            ->with('success', "Contrato {$contract->folio} creado.");
    }

    public function reject(Request $request, Quote $quote, QuoteService $service): RedirectResponse
    {
        Gate::authorize('quotes.manage');
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $service->reject($quote, $request->user(), $data['reason']);

        return back()->with('success', 'Cotización marcada como rechazada.');
    }

    private function builder(User $user, Project $project, ?Quote $quote): Response
    {
        $quote?->load(['items', 'discountAuthorizer:id,name', 'acceptedBy:id,name', 'designVersion:id,number']);

        return Inertia::render('admin/proyectos/CotizacionEditor', [
            'project' => [
                'uuid' => $project->uuid,
                'folio' => $project->folio,
                'name' => $project->name,
                'customer' => $project->customer?->full_name,
            ],
            'quote' => $quote ? P::quote($quote) : null,
            'designs' => $project->designVersions()
                ->where('status', '!=', DesignStatus::Superseded->value)
                ->orWhere(fn (Builder $q) => $q->where('project_id', $project->id)->whereKey($quote->design_version_id ?? 0))
                ->orderByDesc('number')
                ->get(['id', 'number', 'title', 'status'])
                ->map(fn ($d) => ['id' => $d->id, 'label' => "v{$d->number} · {$d->title}", 'status' => $d->status->label()])
                ->values(),
            'defaults' => [
                'tax_rate' => $quote->tax_rate ?? (string) Settings::get('commercial.tax_rate', '0.1600'),
                'prices_include_tax' => $quote->prices_include_tax ?? (bool) Settings::get('commercial.prices_include_tax', true),
                'validity_days' => (int) Settings::get('commercial.quote_validity_days', 15),
                'currency' => $quote->currency ?? (string) Settings::get('commercial.currency', 'MXN'),
            ],
            'units' => ['pza', 'm', 'm²', 'ml', 'jgo', 'lote', 'servicio'],
            'can' => [
                'manage' => $user->hasPermission(Permission::QuotesManage),
                'authorize_discount' => $user->hasPermission(Permission::QuotesAuthorizeDiscount),
            ],
        ]);
    }
}
