<?php

namespace App\Http\Controllers\Admin\Payments;

use App\Enums\PaymentConcept;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\RecordManualPaymentRequest;
use App\Http\Requests\Payments\RefundPaymentRequest;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Project;
use App\Models\SiteVisit;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Payments\PaymentException;
use App\Payments\PaymentPresenter;
use App\Payments\PaymentService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** /admin/pagos — finanzas: listado, detalle, reembolsos, pagos manuales y conciliación. */
class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Payment::class);

        $filters = $request->only(['estado', 'concepto', 'metodo', 'desde', 'hasta', 'q']);

        $base = Payment::query()->tap(fn (Builder $q) => $this->applyFilters($q, $filters));

        $totals = (clone $base)
            ->toBase()
            ->selectRaw('status, COUNT(*) as total_count, COALESCE(SUM(amount), 0) as total_amount, COALESCE(SUM(refunded_amount), 0) as total_refunded')
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => PaymentStatus::from((string) $row->status)->toArray(),
                'count' => (int) $row->total_count,
                'amount' => Money::of((string) $row->total_amount)->amount,
                'refunded' => Money::of((string) $row->total_refunded)->amount,
            ])
            ->values();

        $payments = $base->with('customer')
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Payment $p) => PaymentPresenter::summary($p));

        return Inertia::render('admin/pagos/Index', [
            'payments' => $payments,
            'totals' => $totals,
            'filters' => $filters,
            'options' => [
                'statuses' => PaymentStatus::options(),
                'concepts' => PaymentConcept::options(),
                'methods' => PaymentMethod::options(),
            ],
        ]);
    }

    public function show(Request $request, Payment $payment): Response
    {
        Gate::authorize('view', $payment);

        /** @var User $user */
        $user = $request->user();

        $payment->load(['customer', 'attempts', 'events.user', 'refunds.requester', 'recorder']);

        $transactionIds = $payment->attempts->pluck('provider_transaction_id')->push($payment->provider_transaction_id)->filter()->unique()->values()->all();

        $webhooks = $transactionIds === [] ? collect() : WebhookEvent::query()
            ->whereIn('transaction_id', $transactionIds)
            ->latest('id')
            ->limit(50)
            ->get();

        $visit = $payment->site_visit_id ? SiteVisit::query()->find($payment->site_visit_id) : null;

        return Inertia::render('admin/pagos/Show', [
            'payment' => [
                ...PaymentPresenter::summary($payment),
                'customer' => [
                    'name' => $payment->customer->full_name,
                    'email' => $payment->customer->email,
                    'phone' => $payment->customer->phone,
                ],
                'feeAmount' => $payment->fee_amount,
                'netAmount' => $payment->net_amount,
                'transactionId' => $payment->provider_transaction_id,
                'errorCode' => $payment->error_code,
                'failedAt' => $payment->failed_at?->toIso8601String(),
                'reconciledAt' => $payment->reconciled_at?->toIso8601String(),
                'recordedBy' => $payment->recorder?->name,
            ],
            'attempts' => $payment->attempts->map(fn (PaymentAttempt $a) => PaymentPresenter::attempt($a))->values(),
            'events' => $payment->events->map(fn ($e) => PaymentPresenter::event($e))->values(),
            'refunds' => $payment->refunds->map(fn ($r) => PaymentPresenter::refund($r))->values(),
            'webhooks' => $webhooks->map(fn (WebhookEvent $w) => PaymentPresenter::webhook($w))->values(),
            'links' => [
                'visit' => $visit ? ['folio' => $visit->folio, 'url' => route('admin.visitas.show', $visit)] : null,
                'project' => $payment->project_id ? ['url' => url('/admin/proyectos/'.(Project::query()->whereKey($payment->project_id)->value('uuid') ?? ''))] : null,
                'receipt' => $payment->status->isSettled() ? URL::signedRoute('pagos.comprobante', ['payment' => $payment->uuid]) : null,
            ],
            'can' => [
                'refund' => $user->can('refund', $payment) && $payment->status->isRefundable(),
                'record' => $user->can('record', $payment) && in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Failed], true),
                'reconcile' => $user->can('reconcile', $payment) && $payment->provider->value === 'openpay',
            ],
            'manualMethods' => array_map(fn (PaymentMethod $m) => $m->toArray(), PaymentMethod::manualCases()),
        ]);
    }

    public function refund(RefundPaymentRequest $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        Gate::authorize('refund', $payment);

        /** @var User $user */
        $user = $request->user();

        try {
            $refund = $payments->refund($payment, Money::of((string) $request->validated('amount')), (string) $request->validated('reason'), (string) $request->validated('idempotency_key'), $user);
        } catch (PaymentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return match ($refund->status->value) {
            'completed' => back()->with('success', 'Reembolso de '.Money::of($refund->amount)->format().' completado.'),
            'pending' => back()->with('success', 'Reembolso solicitado; queda por confirmar con Openpay.'),
            default => back()->with('error', $refund->error_message ?: 'Openpay rechazó el reembolso.'),
        };
    }

    public function recordManual(RecordManualPaymentRequest $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        Gate::authorize('record', $payment);

        /** @var User $user */
        $user = $request->user();

        try {
            $payments->recordManual($payment, (string) $request->validated('method'), (string) $request->validated('reference'), $user);
        } catch (PaymentException $e) {
            throw ValidationException::withMessages(['reference' => $e->getMessage()]);
        }

        return back()->with('success', 'Pago registrado.');
    }

    public function reconcile(Payment $payment, PaymentService $payments): RedirectResponse
    {
        Gate::authorize('reconcile', $payment);

        $before = $payment->status;
        $after = $payments->reconcile($payment)->status;

        return back()->with('success', $before === $after
            ? 'Conciliado con Openpay: sin cambios ('.$after->label().').'
            : 'Conciliado con Openpay: '.$before->label().' → '.$after->label().'.');
    }

    /**
     * @param  Builder<Payment>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when(PaymentStatus::tryFrom((string) ($filters['estado'] ?? '')), fn ($q, $s) => $q->where('status', $s->value))
            ->when(PaymentConcept::tryFrom((string) ($filters['concepto'] ?? '')), fn ($q, $c) => $q->where('concept', $c->value))
            ->when(PaymentMethod::tryFrom((string) ($filters['metodo'] ?? '')), fn ($q, $m) => $q->where('method', $m->value))
            ->when($this->date($filters['desde'] ?? null), fn ($q, $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
            ->when($this->date($filters['hasta'] ?? null), fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->when(trim((string) ($filters['q'] ?? '')), function ($q, $term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $term).'%';
                $q->where(fn ($w) => $w->where('folio', 'like', $like)
                    ->orWhere('order_id', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhereHas('customer', fn ($c) => $c->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('email', 'like', $like)));
            });
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }
}
