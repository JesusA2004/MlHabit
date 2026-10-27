<?php

namespace App\Services\Platform;

use App\Enums\ConsultationStatus;
use App\Enums\DesignStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\InstallationStatus;
use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Enums\PrequalificationStatus;
use App\Enums\ProjectStatus;
use App\Enums\RefundStatus;
use App\Enums\VisitStatus;
use App\Enums\WarrantyStatus;
use App\Support\Money;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Métricas reales del tablero (/admin) con consultas agregadas sobre las
 * tablas (query builder, sin depender de los modelos de otros módulos).
 * Todo el dinero se suma en SQL sobre DECIMAL y viaja como string.
 */
class DashboardMetrics
{
    private CarbonImmutable $now;

    public function __construct(?CarbonImmutable $now = null)
    {
        $this->now = $now ?? CarbonImmutable::now();
    }

    /**
     * @return array<string, mixed>
     */
    public function commercial(): array
    {
        $now = $this->now;
        $windows = [];

        foreach ([7, 30] as $days) {
            $current = $this->leads()->where('created_at', '>=', $now->subDays($days))->count();
            $previous = $this->leads()
                ->where('created_at', '>=', $now->subDays($days * 2))
                ->where('created_at', '<', $now->subDays($days))
                ->count();

            $windows[(string) $days] = [
                'current' => $current,
                'previous' => $previous,
                'delta' => $current - $previous,
            ];
        }

        $followUpHours = max(1, (int) Settings::get('leads.follow_up_hours', 24));
        $uncontacted = $this->uncontactedLeads()->count();

        $leads30 = $windows['30']['current'];
        $contracted30 = DB::table('projects')
            ->whereNull('deleted_at')
            ->whereNotNull('contracted_at')
            ->where('contracted_at', '>=', $now->subDays(30))
            ->count();

        return [
            'newLeads' => $windows,
            'uncontacted' => $uncontacted,
            'followUpHours' => $followUpHours,
            'conversion' => [
                'contracted' => $contracted30,
                'leads' => $leads30,
                // Porcentaje con 1 decimal como string (sin float).
                'rate' => $leads30 > 0 ? bcdiv(bcmul((string) $contracted30, '100', 4), (string) $leads30, 1) : null,
            ],
            'trend' => $this->leadTrend(30),
        ];
    }

    public function consultationsToday(): int
    {
        return DB::table('consultations')
            ->whereBetween('starts_at', [$this->now->startOfDay(), $this->now->endOfDay()])
            ->whereIn('status', [ConsultationStatus::Scheduled->value, ConsultationStatus::Confirmed->value, ConsultationStatus::Completed->value])
            ->count();
    }

    /**
     * @return array<string, int>
     */
    public function visits(): array
    {
        $now = $this->now;

        return [
            'today' => DB::table('site_visits')
                ->whereBetween('starts_at', [$now->startOfDay(), $now->endOfDay()])
                ->whereIn('status', self::values([VisitStatus::Scheduled, VisitStatus::Confirmed, VisitStatus::InProgress, VisitStatus::Completed]))
                ->count(),
            'pendingPayment' => DB::table('site_visits')
                ->whereIn('status', self::values([VisitStatus::PendingPayment, VisitStatus::PaymentProcessing]))
                ->where(fn (Builder $q) => $q->whereNull('hold_expires_at')->orWhere('hold_expires_at', '>', $now))
                ->count(),
            'paidNext7' => DB::table('site_visits')
                ->whereIn('status', self::values([VisitStatus::Scheduled, VisitStatus::Confirmed]))
                ->whereNotNull('paid_at')
                ->whereBetween('starts_at', [$now, $now->addDays(7)->endOfDay()])
                ->count(),
            'completedThisMonth' => DB::table('site_visits')
                ->where('status', VisitStatus::Completed->value)
                ->whereBetween('completed_at', [$now->startOfMonth(), $now->endOfMonth()])
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function projects(): array
    {
        $counts = DB::table('projects')
            ->whereNull('deleted_at')
            ->whereNotIn('status', [ProjectStatus::Completed->value, ProjectStatus::Cancelled->value])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $stages = [];
        foreach (ProjectStatus::cases() as $status) {
            $total = (int) ($counts[$status->value] ?? 0);
            if ($total > 0) {
                $stages[] = [
                    'status' => $status->value,
                    'label' => $status->label(),
                    'tone' => $status->tone(),
                    'step' => $status->journeyStep(),
                    'count' => $total,
                ];
            }
        }

        $sum = fn (array $statuses): int => array_sum(array_map(fn (ProjectStatus $s) => (int) ($counts[$s->value] ?? 0), $statuses));

        return [
            'active' => array_sum(array_column($stages, 'count')),
            'stages' => $stages,
            'pendingDeposit' => $sum([ProjectStatus::PendingDeposit]),
            'inFabrication' => $sum([ProjectStatus::Fabrication]),
            'inInstallation' => $sum([ProjectStatus::InstallationScheduled, ProjectStatus::Installing]),
            'pendingQuality' => $sum([ProjectStatus::QualityReview, ProjectStatus::PunchList]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function finance(): array
    {
        $now = $this->now;
        $month = [$now->startOfMonth(), $now->endOfMonth()];

        $collected = DB::table('payments')
            ->whereIn('status', self::values([PaymentStatus::Paid, PaymentStatus::PartiallyRefunded]))
            ->whereBetween('paid_at', $month)
            ->selectRaw('COALESCE(SUM(amount), 0) as gross, COALESCE(SUM(refunded_amount), 0) as refunded, COUNT(*) as total')
            ->first();

        $gross = Money::of((string) ($collected->gross ?? '0'));
        $refundedOnThese = Money::of((string) ($collected->refunded ?? '0'));

        $refunds = DB::table('refunds')
            ->where('status', RefundStatus::Completed->value)
            ->whereBetween('processed_at', $month)
            ->selectRaw('COALESCE(SUM(amount), 0) as amount, COUNT(*) as total')
            ->first();

        return [
            'collectedThisMonth' => [
                'net' => $gross->minus($refundedOnThese)->amount,
                'gross' => $gross->amount,
                'payments' => (int) ($collected->total ?? 0),
            ],
            'failedLast7' => DB::table('payments')
                ->where('status', PaymentStatus::Failed->value)
                ->where(fn (Builder $q) => $q->where('failed_at', '>=', $now->subDays(7))
                    ->orWhere(fn (Builder $w) => $w->whereNull('failed_at')->where('updated_at', '>=', $now->subDays(7))))
                ->count(),
            'refundsThisMonth' => [
                'amount' => Money::of((string) ($refunds->amount ?? '0'))->amount,
                'count' => (int) ($refunds->total ?? 0),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function incidents(): array
    {
        $counts = DB::table('incidents')
            ->whereIn('status', self::values([IncidentStatus::Open, IncidentStatus::InProgress]))
            ->selectRaw('severity, COUNT(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity');

        $bySeverity = [];
        foreach (array_reverse(IncidentSeverity::cases()) as $severity) {
            $bySeverity[] = [
                'severity' => $severity->value,
                'label' => $severity->label(),
                'tone' => $severity->tone(),
                'count' => (int) ($counts[$severity->value] ?? 0),
            ];
        }

        return [
            'open' => array_sum(array_column($bySeverity, 'count')),
            'bySeverity' => $bySeverity,
        ];
    }

    public function activeWarranties(): int
    {
        return DB::table('warranties')
            ->where('status', WarrantyStatus::Active->value)
            ->where('ends_on', '>=', $this->now->toDateString())
            ->count();
    }

    /**
     * Agenda de hoy: asesorías, visitas e instalaciones con hora.
     *
     * @param  array{consultations: bool, visits: bool, installations: bool}  $include
     * @return list<array<string, mixed>>
     */
    public function agendaToday(array $include): array
    {
        $day = [$this->now->startOfDay(), $this->now->endOfDay()];
        $items = [];

        if ($include['consultations']) {
            DB::table('consultations')
                ->join('leads', 'leads.id', '=', 'consultations.lead_id')
                ->join('customers', 'customers.id', '=', 'leads.customer_id')
                ->leftJoin('users', 'users.id', '=', 'consultations.advisor_id')
                ->whereBetween('consultations.starts_at', $day)
                ->whereIn('consultations.status', [ConsultationStatus::Scheduled->value, ConsultationStatus::Confirmed->value, ConsultationStatus::Completed->value])
                ->orderBy('consultations.starts_at')
                ->limit(30)
                ->get([
                    'consultations.uuid', 'consultations.folio', 'consultations.starts_at', 'consultations.ends_at',
                    'consultations.status', 'consultations.channel', 'customers.first_name', 'customers.last_name', 'users.name as owner',
                ])
                ->each(function ($row) use (&$items) {
                    $items[] = [
                        'kind' => 'consultation',
                        'kindLabel' => 'Asesoría',
                        'startsAt' => CarbonImmutable::parse($row->starts_at)->toIso8601String(),
                        'endsAt' => $row->ends_at ? CarbonImmutable::parse($row->ends_at)->toIso8601String() : null,
                        'folio' => $row->folio,
                        'title' => self::fullName($row->first_name, $row->last_name),
                        'owner' => $row->owner,
                        'status' => ConsultationStatus::tryFrom((string) $row->status)?->label() ?? (string) $row->status,
                        'statusTone' => ConsultationStatus::tryFrom((string) $row->status)?->tone() ?? 'neutral',
                        'href' => '/admin/asesorias/'.$row->uuid,
                    ];
                });
        }

        if ($include['visits']) {
            DB::table('site_visits')
                ->join('customers', 'customers.id', '=', 'site_visits.customer_id')
                ->leftJoin('users', 'users.id', '=', 'site_visits.technician_id')
                ->whereBetween('site_visits.starts_at', $day)
                ->whereIn('site_visits.status', self::values([VisitStatus::Scheduled, VisitStatus::Confirmed, VisitStatus::InProgress, VisitStatus::Completed]))
                ->orderBy('site_visits.starts_at')
                ->limit(30)
                ->get([
                    'site_visits.uuid', 'site_visits.folio', 'site_visits.starts_at', 'site_visits.ends_at', 'site_visits.status',
                    'customers.first_name', 'customers.last_name', 'users.name as owner',
                ])
                ->each(function ($row) use (&$items) {
                    $items[] = [
                        'kind' => 'visit',
                        'kindLabel' => 'Visita técnica',
                        'startsAt' => CarbonImmutable::parse($row->starts_at)->toIso8601String(),
                        'endsAt' => $row->ends_at ? CarbonImmutable::parse($row->ends_at)->toIso8601String() : null,
                        'folio' => $row->folio,
                        'title' => self::fullName($row->first_name, $row->last_name),
                        'owner' => $row->owner,
                        'status' => VisitStatus::tryFrom((string) $row->status)?->label() ?? (string) $row->status,
                        'statusTone' => VisitStatus::tryFrom((string) $row->status)?->tone() ?? 'neutral',
                        'href' => '/admin/visitas/'.$row->uuid,
                    ];
                });
        }

        if ($include['installations']) {
            DB::table('installation_orders')
                ->join('projects', 'projects.id', '=', 'installation_orders.project_id')
                ->leftJoin('users', 'users.id', '=', 'installation_orders.lead_installer_id')
                ->whereBetween('installation_orders.scheduled_start', $day)
                ->whereNotIn('installation_orders.status', [InstallationStatus::Draft->value, InstallationStatus::Cancelled->value])
                ->orderBy('installation_orders.scheduled_start')
                ->limit(30)
                ->get([
                    'installation_orders.id', 'installation_orders.folio', 'installation_orders.scheduled_start',
                    'installation_orders.scheduled_end', 'installation_orders.status', 'projects.name as project', 'users.name as owner',
                ])
                ->each(function ($row) use (&$items) {
                    $items[] = [
                        'kind' => 'installation',
                        'kindLabel' => 'Instalación',
                        'startsAt' => CarbonImmutable::parse($row->scheduled_start)->toIso8601String(),
                        'endsAt' => $row->scheduled_end ? CarbonImmutable::parse($row->scheduled_end)->toIso8601String() : null,
                        'folio' => $row->folio,
                        'title' => (string) $row->project,
                        'owner' => $row->owner,
                        'status' => InstallationStatus::tryFrom((string) $row->status)?->label() ?? (string) $row->status,
                        'statusTone' => InstallationStatus::tryFrom((string) $row->status)?->tone() ?? 'neutral',
                        'href' => '/admin/instalaciones/'.$row->id,
                    ];
                });
        }

        usort($items, fn (array $a, array $b) => strcmp($a['startsAt'], $b['startsAt']));

        return $items;
    }

    /**
     * Pendientes accionables según permisos (solo los que tienen conteo > 0).
     *
     * @param  callable(string): bool  $can
     * @return list<array{key: string, label: string, count: int, href: string, tone: string}>
     */
    public function attention(callable $can): array
    {
        $now = $this->now;
        $items = [];

        $add = function (string $key, string $label, int $count, string $href, string $tone) use (&$items): void {
            if ($count > 0) {
                $items[] = compact('key', 'label', 'count', 'href', 'tone');
            }
        };

        if ($can('leads.view')) {
            $add('leads_uncontacted', 'Prospectos sin primer contacto a tiempo', $this->uncontactedLeads()->count(), '/admin/prospectos?estado=new', 'warning');
        }

        if ($can('visits.assign')) {
            $add('visits_unassigned', 'Visitas pagadas sin técnico asignado', DB::table('site_visits')
                ->whereIn('status', self::values([VisitStatus::Scheduled, VisitStatus::Confirmed]))
                ->whereNull('technician_id')
                ->where('starts_at', '>=', $now->startOfDay())
                ->count(), '/admin/visitas', 'warning');
        }

        if ($can('prequalification.review')) {
            $add('prequalifications', 'Precalificaciones por revisar', DB::table('prequalifications')
                ->whereIn('status', self::values([PrequalificationStatus::Submitted, PrequalificationStatus::InReview]))
                ->count(), '/admin/precalificaciones', 'info');
        }

        if ($can('designs.approve_technical')) {
            $add('designs_technical', 'Diseños aprobados por el cliente sin aprobación técnica', DB::table('design_versions')
                ->where('status', DesignStatus::ClientApproved->value)
                ->whereNull('technical_approved_at')
                ->count(), '/admin/disenos', 'info');
        }

        if ($can('quality.review')) {
            $add('quality_review', 'Instalaciones esperando revisión de calidad', DB::table('installation_orders')
                ->where('status', InstallationStatus::QualityReview->value)
                ->count(), '/admin/calidad', 'info');
        }

        if ($can('payouts.authorize')) {
            $add('payouts', 'Pagos a proveedores por autorizar', DB::table('supplier_payouts')
                ->where('status', PayoutStatus::PendingAuthorization->value)
                ->count(), '/admin/pagos-proveedores', 'warning');
        }

        if ($can('payments.view')) {
            $add('payments_failed', 'Pagos fallidos en los últimos 7 días', DB::table('payments')
                ->where('status', PaymentStatus::Failed->value)
                ->where('updated_at', '>=', $now->subDays(7))
                ->count(), '/admin/pagos?estado=failed', 'danger');
        }

        if ($can('incidents.view')) {
            $add('incidents_high', 'Incidencias abiertas de severidad alta o crítica', DB::table('incidents')
                ->whereIn('status', self::values([IncidentStatus::Open, IncidentStatus::InProgress]))
                ->whereIn('severity', self::values([IncidentSeverity::High, IncidentSeverity::Critical]))
                ->count(), '/admin/incidencias', 'danger');
        }

        if ($can('projects.view')) {
            $add('projects_deposit', 'Proyectos con contrato esperando anticipo', DB::table('projects')
                ->whereNull('deleted_at')
                ->where('status', ProjectStatus::PendingDeposit->value)
                ->count(), '/admin/proyectos?estado=pending_deposit', 'warning');
        }

        return $items;
    }

    private function leads(): Builder
    {
        return DB::table('leads')->whereNull('deleted_at');
    }

    private function uncontactedLeads(): Builder
    {
        $hours = max(1, (int) Settings::get('leads.follow_up_hours', 24));

        return $this->leads()
            ->where('status', LeadStatus::New->value)
            ->whereNull('first_contacted_at')
            ->where('created_at', '<=', $this->now->subHours($hours));
    }

    /**
     * Prospectos nuevos por día (serie continua, incluye días en cero).
     *
     * @return list<array{date: string, count: int}>
     */
    private function leadTrend(int $days): array
    {
        $start = $this->now->subDays($days - 1)->startOfDay();

        $rows = $this->leads()
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->addDays($i)->toDateString();
            $series[] = ['date' => $date, 'count' => (int) ($rows[$date] ?? 0)];
        }

        return $series;
    }

    /**
     * @param  list<\BackedEnum>  $cases
     * @return list<string>
     */
    private static function values(array $cases): array
    {
        return array_map(fn (\BackedEnum $c) => (string) $c->value, $cases);
    }

    private static function fullName(?string $first, ?string $last): string
    {
        return trim(($first ?? '').' '.($last ?? '')) ?: 'Cliente';
    }
}
