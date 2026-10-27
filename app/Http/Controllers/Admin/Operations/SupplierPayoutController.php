<?php

namespace App\Http\Controllers\Admin\Operations;

use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\Operations\OpsPresenter;
use App\Models\SupplierPayout;
use App\Services\Operations\PayoutService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** /admin/pagos-proveedores — autorizar (separación de funciones), pagar, rechazar. */
class SupplierPayoutController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', SupplierPayout::class);

        $filters = $request->validate([
            'estado' => ['nullable', Rule::enum(PayoutStatus::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $user = $request->user();

        $payouts = SupplierPayout::query()
            ->with(['supplier:id,name', 'installationOrder:id,folio,lead_installer_id,project_id', 'fabricationOrder:id,folio,project_id', 'requester:id,name', 'authorizer:id,name', 'payer:id,name'])
            ->when($filters['estado'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('folio', 'like', "%{$term}%")->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$term}%"))))
            ->orderByRaw("FIELD(status, 'pending_authorization', 'authorized', 'rejected', 'paid')")
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (SupplierPayout $p) => [
                ...OpsPresenter::payoutRow($p),
                'can' => [
                    'authorize' => $p->status === PayoutStatus::PendingAuthorization && $user->can('approve', $p),
                    'pay' => $p->status === PayoutStatus::Authorized && $user->can('pay', $p),
                    'reject' => in_array($p->status, [PayoutStatus::PendingAuthorization, PayoutStatus::Authorized], true) && $user->can('approve', $p),
                    'resubmit' => $p->status === PayoutStatus::Rejected && ($user->can('installations.manage') || $user->can('fabrication.manage')),
                    'blockedReason' => $p->status === PayoutStatus::PendingAuthorization && $user->can('payouts.authorize') && ! $user->can('approve', $p)
                        ? 'Tú solicitaste o ejecutaste este trabajo; otra persona debe autorizarlo.'
                        : null,
                ],
            ]);

        $totals = SupplierPayout::query()->whereIn('status', [PayoutStatus::PendingAuthorization->value, PayoutStatus::Authorized->value])
            ->get(['status', 'amount'])
            ->groupBy(fn ($p) => $p->status->value)
            ->map(fn ($rows) => ['count' => $rows->count(), 'amount' => $rows->reduce(fn (Money $sum, $p) => $sum->plus($p->amount), Money::zero())->amount]);

        return Inertia::render('admin/pagos-proveedores/Index', [
            'payouts' => $payouts,
            'filters' => $filters,
            'statuses' => PayoutStatus::options(),
            'summary' => [
                'pending' => $totals['pending_authorization'] ?? ['count' => 0, 'amount' => '0.00'],
                'authorized' => $totals['authorized'] ?? ['count' => 0, 'amount' => '0.00'],
            ],
        ]);
    }

    public function authorizePayout(Request $request, SupplierPayout $payout, PayoutService $service): RedirectResponse
    {
        Gate::authorize('approve', $payout);
        $service->authorize($payout, $request->user());

        return back()->with('success', "Pago {$payout->folio} autorizado.");
    }

    public function pay(Request $request, SupplierPayout $payout, PayoutService $service): RedirectResponse
    {
        Gate::authorize('pay', $payout);
        $data = $request->validate(['reference' => ['required', 'string', 'max:120']], ['reference.required' => 'Captura la referencia de la transferencia o del pago.']);
        $service->pay($payout, $request->user(), $data['reference']);

        return back()->with('success', "Pago {$payout->folio} registrado.");
    }

    public function reject(Request $request, SupplierPayout $payout, PayoutService $service): RedirectResponse
    {
        Gate::authorize('approve', $payout);
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);
        $service->reject($payout, $request->user(), $data['reason']);

        return back()->with('success', "Pago {$payout->folio} rechazado.");
    }

    public function resubmit(Request $request, SupplierPayout $payout, PayoutService $service): RedirectResponse
    {
        abort_unless($request->user()->can('installations.manage') || $request->user()->can('fabrication.manage'), 403);
        $service->resubmit($payout, $request->user());

        return back()->with('success', "Pago {$payout->folio} enviado de nuevo a autorización.");
    }
}
