<?php

namespace App\Http\Controllers\Portal\Support;

use App\Enums\ConsultationChannel;
use App\Enums\ConsultationStatus;
use App\Enums\ContractStatus;
use App\Enums\LeadStatus;
use App\Enums\PaymentConcept;
use App\Enums\PaymentStatus;
use App\Enums\VisitStatus;
use App\Http\Presenters\Projects\ProjectPresenter as P;
use App\Models\Consultation;
use App\Models\Contract;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\SiteVisit;
use App\Services\Visits\VisitCheckoutUrls;
use App\Support\Crm\CrmLinks;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

/**
 * Datos del portal que cruzan módulos (solicitudes, citas y pagos). Todo se
 * consulta a partir de los IDs de cliente que ya resolvió el controlador
 * (ResolvesPortalCustomer::customerIds), nunca de IDs del navegador.
 */
final class PortalFeed
{
    /** Etapas de una solicitud vistas por el cliente. */
    public const REQUEST_STAGES = [
        ['key' => 'received', 'title' => 'Solicitud recibida'],
        ['key' => 'consultation', 'title' => 'Asesoría'],
        ['key' => 'visit', 'title' => 'Visita y medición'],
        ['key' => 'proposal', 'title' => 'Diseño y cotización'],
        ['key' => 'project', 'title' => 'Proyecto en marcha'],
    ];

    /** @var array<string, bool> */
    private static array $columns = [];

    // ── Solicitudes ────────────────────────────────────────────────────

    /**
     * @param  list<int>  $customerIds
     * @return list<array<string, mixed>>
     */
    public static function requests(array $customerIds, ?int $limit = null): array
    {
        if ($customerIds === []) {
            return [];
        }

        $leads = Lead::query()
            ->whereIn('customer_id', $customerIds)
            ->with([
                'consultations' => fn ($q) => $q->with('advisor:id,name')->orderByDesc('starts_at'),
                'project:id,uuid,lead_id,folio,name,status',
            ])
            ->latest('created_at')->latest('id')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();

        $visits = self::attempt(fn () => SiteVisit::query()
            ->whereIn('lead_id', $leads->pluck('id'))
            ->whereNotIn('status', [VisitStatus::Expired->value])
            ->orderByDesc('starts_at')
            ->get(['id', 'uuid', 'lead_id', 'starts_at', 'status'])
            ->groupBy('lead_id'), collect());

        return array_values($leads->map(function (Lead $lead) use ($visits) {
            $consultations = $lead->consultations
                ->reject(fn (Consultation $c) => $c->status === ConsultationStatus::Rescheduled)
                ->values();
            $active = $consultations->first(fn (Consultation $c) => $c->status->isActive() && $c->ends_at->isFuture());
            /** @var SiteVisit|null $visit */
            $visit = $visits->get($lead->id)?->first();
            $project = $lead->project;

            return [
                'uuid' => $lead->uuid,
                'folio' => $lead->folio,
                'created_at' => P::date($lead->created_at),
                'status' => self::requestStatus($lead->status),
                'stage' => self::requestStage($lead->status),
                'closed' => in_array($lead->status, [LeadStatus::Lost, LeadStatus::Cancelled], true),
                'categories' => array_map(fn ($c) => $c['name'], self::attempt(fn () => $lead->categorySummaries(), [])),
                'place' => collect([$lead->city, $lead->state])->filter()->implode(', ') ?: null,
                'comments' => $lead->comments ? Str::limit($lead->comments, 280) : null,
                'timeframe' => P::enum($lead->timeframe)['label'] ?? null,
                'budget' => P::enum($lead->budget_range)['label'] ?? null,
                'consultations' => $consultations->take(3)->map(fn (Consultation $c) => [
                    'uuid' => $c->uuid,
                    'starts_at' => P::date($c->starts_at),
                    'status' => P::enum($c->status),
                    'channel' => $c->channel->label(),
                    'with' => $c->advisor->name,
                ])->values()->all(),
                'visit' => $visit ? [
                    'starts_at' => P::date($visit->starts_at),
                    'status' => P::enum($visit->status),
                ] : null,
                'project' => $project ? [
                    'uuid' => $project->uuid,
                    'folio' => $project->folio,
                    'name' => $project->name,
                    'status' => $project->status->label(),
                ] : null,
                'next' => self::requestNext($lead, $active, $project !== null),
            ];
        })->all());
    }

    /** @return array{value: string, label: string, tone: string} */
    private static function requestStatus(LeadStatus $status): array
    {
        $label = match ($status) {
            LeadStatus::New => 'Recibida',
            LeadStatus::Contacted => 'Te contactamos',
            LeadStatus::ConsultationScheduled => 'Asesoría agendada',
            LeadStatus::Prequalification => 'En revisión',
            LeadStatus::VisitPending => 'Visita por agendar',
            LeadStatus::VisitPaid => 'Visita agendada',
            LeadStatus::VisitDone => 'Visita realizada',
            LeadStatus::Design => 'En diseño',
            LeadStatus::Quoting => 'Preparando tu cotización',
            LeadStatus::Negotiation => 'Cotización enviada',
            LeadStatus::Won => 'Proyecto en marcha',
            LeadStatus::Lost => 'Cerrada',
            LeadStatus::Cancelled => 'Cancelada',
        };

        return ['value' => $status->value, 'label' => $label, 'tone' => $status === LeadStatus::Lost ? 'neutral' : $status->tone()];
    }

    private static function requestStage(LeadStatus $status): int
    {
        return match ($status) {
            LeadStatus::New, LeadStatus::Contacted, LeadStatus::Lost, LeadStatus::Cancelled => 0,
            LeadStatus::ConsultationScheduled, LeadStatus::Prequalification => 1,
            LeadStatus::VisitPending, LeadStatus::VisitPaid, LeadStatus::VisitDone => 2,
            LeadStatus::Design, LeadStatus::Quoting, LeadStatus::Negotiation => 3,
            LeadStatus::Won => 4,
        };
    }

    /** @return array{label: string, href: string, external: bool}|null */
    private static function requestNext(Lead $lead, ?Consultation $active, bool $hasProject): ?array
    {
        if ($hasProject) {
            return ['label' => 'Ver mi proyecto', 'href' => '/portal', 'external' => false];
        }
        if ($lead->status->isClosed()) {
            return null;
        }
        if ($active && ($payUrl = self::consultationPayUrl($active))) {
            return ['label' => 'Pagar mi asesoría', 'href' => $payUrl, 'external' => true];
        }
        if ($active) {
            return ['label' => 'Ver mi asesoría', 'href' => CrmLinks::manageConsultation($active), 'external' => true];
        }
        if ($lead->status === LeadStatus::VisitPending && ($url = CrmLinks::bookVisit($lead))) {
            return ['label' => 'Agendar mi visita', 'href' => $url, 'external' => true];
        }
        if (in_array($lead->status, [LeadStatus::New, LeadStatus::Contacted], true)) {
            return ['label' => 'Agendar asesoría', 'href' => CrmLinks::bookConsultation($lead), 'external' => true];
        }

        return null;
    }

    // ── Pagos ──────────────────────────────────────────────────────────

    /**
     * Todos los pagos del cliente, sin importar a qué estén ligados
     * (visita, asesoría, anticipo, abono…). Los intentos abandonados que
     * ya no se pueden pagar se omiten para no ensuciar el historial.
     *
     * @param  list<int>  $customerIds
     * @return list<array<string, mixed>>
     */
    public static function payments(array $customerIds, ?int $limit = null): array
    {
        if ($customerIds === []) {
            return [];
        }

        $with = ['siteVisit:id,uuid,starts_at,status,hold_expires_at,lead_id', 'project:id,uuid,folio,name', 'contract:id,uuid,status,project_id'];
        // Pagos de asesoría (payments.consultation_id): se cargan si la columna ya existe.
        if (self::hasColumn('payments', 'consultation_id')) {
            $with[] = 'consultation';
        }

        $payments = self::attempt(fn () => Payment::query()
            ->whereIn('customer_id', $customerIds)
            ->with($with)
            ->latest('created_at')->latest('id')
            ->limit(200)
            ->get(), collect());

        $items = [];
        foreach ($payments as $payment) {
            $payUrl = self::payUrl($payment);
            $status = $payment->status;
            $live = $status->isSettled() || $status === PaymentStatus::Processing;
            if (! $live && $payUrl === null) {
                continue;
            }

            $items[] = [
                ...P::payment($payment, forClient: true),
                'subject' => self::subject($payment),
                'pay_url' => $payUrl,
                'receipt_url' => $status->isSettled() ? self::receiptUrl($payment) : null,
                'awaiting' => ! $live,
            ];

            if ($limit !== null && count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    /**
     * Totales para las tarjetas de resumen.
     *
     * @param  list<array<string, mixed>>  $payments
     * @return array{paid: string, pending: string, count: int}
     */
    public static function paymentTotals(array $payments, float $extraPending = 0.0): array
    {
        $paid = 0.0;
        $pending = $extraPending;
        $count = 0;
        foreach ($payments as $p) {
            if ($p['awaiting']) {
                $pending += (float) $p['amount'];

                continue;
            }
            if ($p['paid_at']) {
                $paid += (float) $p['amount'] - (float) $p['refunded_amount'];
                $count++;
            }
        }

        return ['paid' => number_format(max(0, $paid), 2, '.', ''), 'pending' => number_format(max(0, $pending), 2, '.', ''), 'count' => $count];
    }

    /** @return array{kind: string, label: string, detail: string|null, date: string|null} */
    private static function subject(Payment $payment): array
    {
        $consultation = self::consultationOf($payment);
        if ($consultation) {
            return ['kind' => 'consultation', 'label' => 'Asesoría', 'detail' => $consultation->channel->label(), 'date' => P::date($consultation->starts_at)];
        }
        if ($payment->site_visit_id && $payment->siteVisit) {
            return ['kind' => 'visit', 'label' => 'Visita técnica', 'detail' => null, 'date' => P::date($payment->siteVisit->starts_at)];
        }
        if ($payment->project) {
            return ['kind' => 'project', 'label' => $payment->project->name, 'detail' => $payment->project->folio, 'date' => null];
        }

        return [
            'kind' => $payment->concept === PaymentConcept::VisitFee ? 'visit' : 'other',
            'label' => $payment->concept->label(),
            'detail' => null,
            'date' => null,
        ];
    }

    private static function consultationOf(Payment $payment): ?Consultation
    {
        $related = $payment->relationLoaded('consultation') ? $payment->getRelation('consultation') : null;

        return $related instanceof Consultation ? $related : null;
    }

    private static function payUrl(Payment $payment): ?string
    {
        if (! $payment->status->acceptsAttempts()) {
            return null;
        }

        return self::attempt(function () use ($payment) {
            if ($payment->concept === PaymentConcept::Deposit && $payment->contract_id) {
                $contract = $payment->contract;

                return $contract instanceof Contract && in_array($contract->status, [ContractStatus::PendingDeposit, ContractStatus::DepositRefunded], true)
                    ? "/portal/pagos/anticipo/{$contract->uuid}"
                    : null;
            }

            $visit = $payment->siteVisit;
            if ($visit && $visit->status === VisitStatus::PendingPayment && $visit->holdIsActive() && Route::has('public.visita.pago')) {
                return VisitCheckoutUrls::pay($payment);
            }

            $consultation = self::consultationOf($payment);
            if ($consultation && $consultation->ends_at->isFuture()) {
                return self::consultationPayUrl($consultation)
                    ?? ($consultation->status->isActive() ? CrmLinks::manageConsultation($consultation) : null);
            }

            return null;
        }, null);
    }

    /**
     * Pago pendiente de una asesoría apartada (flujo firmado del CRM). Se
     * compara por valor para no depender de los casos nuevos del enum.
     */
    private static function consultationPayUrl(Consultation $consultation): ?string
    {
        $hold = $consultation->getAttribute('hold_expires_at');
        $holdActive = $hold === null || now()->lt($hold);

        if ($consultation->status->value !== 'pending_payment' || ! $holdActive || ! Route::has('public.asesoria.pago')) {
            return null;
        }

        return URL::temporarySignedRoute('public.asesoria.pago', now()->addDays(7), ['consultation' => $consultation->uuid]);
    }

    private static function receiptUrl(Payment $payment): ?string
    {
        return Route::has('pagos.comprobante')
            ? URL::signedRoute('pagos.comprobante', ['payment' => $payment->uuid])
            : null;
    }

    // ── Citas ──────────────────────────────────────────────────────────

    /**
     * Asesorías (vía las solicitudes del cliente) y visitas técnicas.
     *
     * @param  list<int>  $customerIds
     * @return array{upcoming: list<array<string, mixed>>, past: list<array<string, mixed>>}
     */
    public static function appointments(array $customerIds): array
    {
        if ($customerIds === []) {
            return ['upcoming' => [], 'past' => []];
        }

        $items = collect();

        $items = $items->merge(self::attempt(fn () => Consultation::query()
            ->whereHas('lead', fn ($q) => $q->whereIn('customer_id', $customerIds))
            ->where('status', '!=', ConsultationStatus::Rescheduled->value)
            ->with(['advisor:id,name', 'lead:id,folio'])
            ->latest('starts_at')->limit(40)->get()
            ->map(function (Consultation $c) {
                $active = $c->status->isActive();
                $payUrl = null;
                if ($c->status->value === 'pending_payment') {
                    $payUrl = self::consultationPayUrl($c);
                    if ($payUrl === null) {
                        return null; // Apartado vencido: ya no es una cita.
                    }
                }
                $booked = $active && $payUrl === null;

                return [
                    'key' => "a-{$c->uuid}",
                    'kind' => 'consultation',
                    'title' => 'Asesoría',
                    'folio' => $c->folio,
                    'request_folio' => $c->lead->folio,
                    'starts_at' => P::date($c->starts_at),
                    'ends_at' => P::date($c->ends_at),
                    'status' => P::enum($c->status),
                    'with' => $c->advisor->name,
                    'channel' => $c->channel->label(),
                    'is_video' => $c->channel === ConsultationChannel::Video,
                    'meeting_url' => $booked ? $c->meeting_url : null,
                    'location' => null,
                    'active' => $active,
                    'reschedule_url' => $active && $c->ends_at->isFuture() ? CrmLinks::manageConsultation($c) : null,
                    'pay_url' => $payUrl,
                    'notes' => $c->customer_notes,
                ];
            })->filter(), collect()));

        $items = $items->merge(self::attempt(fn () => SiteVisit::query()
            ->whereIn('customer_id', $customerIds)
            ->whereNotIn('status', [VisitStatus::PaymentProcessing->value, VisitStatus::Expired->value])
            ->with(['technician:id,name', 'address', 'lead:id,folio', 'payment'])
            ->latest('starts_at')->limit(40)->get()
            ->map(function (SiteVisit $v) {
                $awaiting = $v->status === VisitStatus::PendingPayment;
                $payUrl = null;
                if ($awaiting) {
                    if (! $v->holdIsActive()) {
                        return null;
                    }
                    $payment = $v->payment;
                    $payUrl = $payment && $payment->status->acceptsAttempts() && Route::has('public.visita.pago') ? VisitCheckoutUrls::pay($payment) : null;
                }

                return [
                    'key' => "v-{$v->uuid}",
                    'kind' => 'visit',
                    'title' => 'Visita técnica de medición',
                    'folio' => $v->folio,
                    'request_folio' => $v->lead?->folio,
                    'starts_at' => P::date($v->starts_at),
                    'ends_at' => P::date($v->ends_at),
                    'status' => P::enum($v->status),
                    'with' => $v->technician?->name,
                    'channel' => null,
                    'is_video' => false,
                    'meeting_url' => null,
                    'location' => $v->address->one_line,
                    'active' => $v->status->isUpcoming() || $awaiting || $v->status === VisitStatus::InProgress,
                    'reschedule_url' => null,
                    'pay_url' => $payUrl,
                    'notes' => null,
                ];
            })->filter(), collect()));

        $now = now();
        $isUpcoming = fn (array $i) => $i['active'] && $i['ends_at'] && $now->lt($i['ends_at']);

        return [
            'upcoming' => array_values($items->filter($isUpcoming)->sortBy('starts_at')->all()),
            'past' => array_values($items->reject($isUpcoming)->sortByDesc('starts_at')->all()),
        ];
    }

    // ── Utilidades ─────────────────────────────────────────────────────

    private static function hasColumn(string $table, string $column): bool
    {
        return self::$columns["{$table}.{$column}"] ??= self::attempt(fn () => Schema::hasColumn($table, $column), false);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $fn
     * @param  T  $fallback
     * @return T
     */
    private static function attempt(callable $fn, mixed $fallback): mixed
    {
        try {
            return $fn();
        } catch (Throwable $e) {
            report($e);

            return $fallback;
        }
    }
}
