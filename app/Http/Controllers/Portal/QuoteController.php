<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ContractStatus;
use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResolvesPortalCustomer;
use App\Http\Presenters\Projects\ProjectPresenter as P;
use App\Models\Quote;
use App\Services\Projects\ContractService;
use App\Services\Projects\QuoteService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    use ResolvesPortalCustomer;

    public function index(Request $request): Response
    {
        $projects = $this->projects($this->customer($request));

        $quotes = Quote::query()
            ->whereIn('project_id', $projects->pluck('id'))
            ->where('status', '!=', QuoteStatus::Draft->value)
            ->with('project:id,uuid,folio,name')
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Quote $q) => [
                ...P::quoteSummary($q),
                'project' => ['folio' => $q->project->folio, 'name' => $q->project->name],
                'actionable' => $q->status === QuoteStatus::Sent && ! $q->isPastValidity(),
            ])->values();

        return Inertia::render('portal/Cotizaciones', ['quotes' => $quotes]);
    }

    public function show(Quote $quote): Response
    {
        $this->authorizeOwner($quote);
        $quote->load(['items', 'project:id,uuid,folio,name', 'designVersion:id,number,title', 'contract']);
        $contract = $quote->contract;

        return Inertia::render('portal/Cotizacion', [
            'quote' => [
                ...P::quote($quote, forClient: true),
                'project' => ['folio' => $quote->project->folio, 'name' => $quote->project->name],
                'design' => $quote->designVersion ? ['number' => $quote->designVersion->number, 'title' => $quote->designVersion->title] : null,
                'print_url' => "/cotizaciones/{$quote->uuid}/imprimir",
            ],
            'contract' => $contract ? P::contract($contract, [
                'pay_url' => in_array($contract->status, [ContractStatus::PendingDeposit, ContractStatus::DepositRefunded], true) ? "/portal/pagos/anticipo/{$contract->uuid}" : null,
            ]) : null,
            'can' => [
                'respond' => $quote->status === QuoteStatus::Sent && ! $quote->isPastValidity(),
            ],
        ]);
    }

    public function accept(Request $request, Quote $quote, ContractService $contracts): RedirectResponse
    {
        $this->authorizeOwner($quote);
        Gate::authorize('respond', $quote);
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Confirma que leíste y aceptas la cotización y sus condiciones.']);

        $contract = $contracts->acceptQuote($quote, $request->user(), null, $request->ip());

        if (Money::of($contract->deposit_due_amount)->isPositive() && $contract->status !== ContractStatus::Active) {
            return to_route('portal.pagos.anticipo', $contract)->with('success', '¡Gracias! Tu contrato está listo. El último paso es el anticipo.');
        }

        return to_route('portal.inicio')->with('success', '¡Gracias! Tu proyecto quedó autorizado.');
    }

    public function reject(Request $request, Quote $quote, QuoteService $service): RedirectResponse
    {
        $this->authorizeOwner($quote);
        Gate::authorize('respond', $quote);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']], [
            'reason.required' => 'Cuéntanos el motivo; nos ayuda a preparar una mejor propuesta.',
        ]);

        $service->reject($quote, $request->user(), $data['reason']);

        return back()->with('success', 'Registramos tu respuesta. Tu asesor se pondrá en contacto contigo.');
    }

    /** IDOR: 404 si no es del cliente o si aún es borrador. */
    private function authorizeOwner(Quote $quote): void
    {
        abort_unless(Gate::allows('view', $quote), 404);
    }
}
