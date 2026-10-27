<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\SiteVisit;
use App\Models\User;
use App\Notifications\Visits\VisitText;
use App\Services\PublicSiteDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * GET /pagos/{payment:uuid}/comprobante — comprobante imprimible.
 * Acceso: enlace firmado (correo de confirmación) o usuario autenticado con
 * permiso (personal de finanzas o el cliente dueño: PaymentPolicy::view).
 * Sin datos sensibles: marca y últimos 4 dígitos, autorización, folio.
 */
class ReceiptController extends Controller
{
    public function __invoke(Request $request, Payment $payment): Response|RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            /** @var User|null $user */
            $user = $request->user();

            if ($user === null) {
                return redirect()->guest(route('login'));
            }

            Gate::forUser($user)->authorize('view', $payment);
        }

        abort_unless($payment->status->isSettled(), 404);

        $payment->load('customer');
        $visit = $payment->site_visit_id ? SiteVisit::query()->with('address')->find($payment->site_visit_id) : null;

        return Inertia::render('public/pagos/Comprobante', [
            ...$this->publicBase(),
            'receipt' => [
                'folio' => $payment->folio,
                'concept' => $payment->concept->label(),
                'description' => $payment->description,
                'amount' => $payment->amount,
                'refundedAmount' => $payment->refunded_amount,
                'currency' => $payment->currency,
                'status' => $payment->status->toArray(),
                'method' => $payment->method->label(),
                'cardBrand' => $payment->card_brand,
                'cardLast4' => $payment->card_last4,
                'authorization' => $payment->provider_authorization,
                'reference' => $payment->reference,
                'paidAt' => $payment->paid_at?->toIso8601String(),
                'customerName' => $payment->customer->full_name,
                'visit' => $visit ? [
                    'folio' => $visit->folio,
                    'when' => VisitText::when($visit),
                    'address' => $visit->address->one_line,
                ] : null,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function publicBase(): array
    {
        try {
            return app(PublicSiteDataService::class)->base();
        } catch (Throwable) {
            return ['site' => ['name' => 'ML Habitat']];
        }
    }
}
