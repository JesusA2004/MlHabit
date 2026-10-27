<?php

namespace App\Http\Controllers\Technician;

use App\Enums\MeasurementStatus;
use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\Projects\ProjectPresenter as P;
use App\Models\InstallationOrder;
use App\Models\Measurement;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\Projects\MeasurementService;
use App\Services\Visits\VisitWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * App de campo (/tecnico). Cada técnico ve SOLO sus visitas (IDOR: 403 si
 * la visita es de otro). Sin rastreo GPS por decisión de privacidad: la
 * ubicación es la dirección del cliente y el técnico abre su app de mapas.
 */
class VisitController extends Controller
{
    public function today(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $visits = SiteVisit::query()
            ->where('technician_id', $user->id)
            ->whereDate('starts_at', now()->toDateString())
            ->whereNotIn('status', [VisitStatus::Cancelled->value, VisitStatus::Expired->value, VisitStatus::PendingPayment->value, VisitStatus::PaymentProcessing->value])
            ->with(['customer:id,first_name', 'address'])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (SiteVisit $v) => $this->visitCard($v))
            ->values();

        return Inertia::render('tecnico/Hoy', [
            'today' => now()->toDateString(),
            'firstName' => explode(' ', trim($user->name))[0],
            'visits' => $visits,
            'installations' => $this->todayInstallations($user),
        ]);
    }

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $base = fn () => SiteVisit::query()
            ->where('technician_id', $user->id)
            ->whereNotIn('status', [VisitStatus::PendingPayment->value, VisitStatus::PaymentProcessing->value, VisitStatus::Expired->value])
            ->with(['customer:id,first_name', 'address']);

        return Inertia::render('tecnico/visitas/Index', [
            'upcoming' => $base()->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at')->limit(50)->get()->map(fn ($v) => $this->visitCard($v))->values(),
            'past' => $base()->where('starts_at', '<', now()->startOfDay())->where('starts_at', '>=', now()->subDays(30))->orderByDesc('starts_at')->limit(50)->get()->map(fn ($v) => $this->visitCard($v))->values(),
        ]);
    }

    public function show(Request $request, SiteVisit $visit): Response
    {
        $this->authorizeOwn($request, $visit);
        $visit->load(['customer', 'address']);

        $measurements = Measurement::query()
            ->where('site_visit_id', $visit->id)
            ->with('items')
            ->orderByDesc('version')
            ->get();

        $status = $visit->status;

        return Inertia::render('tecnico/visitas/Show', [
            'visit' => [
                ...$this->visitCard($visit),
                'phone' => $visit->customer->phone,
                'access_notes' => $visit->access_notes,
                'customer_notes' => $visit->customer_notes,
                'started_at' => P::date($visit->started_at),
                'completed_at' => P::date($visit->completed_at),
            ],
            'measurements' => $measurements->map(fn (Measurement $m) => [
                'id' => $m->id,
                'version' => $m->version,
                'status' => $m->status->toArray(),
                'items_count' => $m->items->count(),
                'areas' => $m->items->pluck('area')->unique()->values(),
                'submitted_at' => P::date($m->submitted_at),
                'updated_at' => P::date($m->updated_at),
            ])->values(),
            'can' => [
                'start' => in_array($status, [VisitStatus::Scheduled, VisitStatus::Confirmed], true),
                'measure' => in_array($status, [VisitStatus::InProgress, VisitStatus::Completed], true),
                'complete' => $status === VisitStatus::InProgress,
                'has_submitted' => $measurements->contains(fn (Measurement $m) => $m->status === MeasurementStatus::Submitted),
            ],
        ]);
    }

    public function start(Request $request, SiteVisit $visit, VisitWorkflow $workflow): RedirectResponse
    {
        $this->authorizeOwn($request, $visit);
        $workflow->start($visit, $request->user());

        return back()->with('success', 'Visita iniciada. Registra la medición cuando estés listo.');
    }

    public function complete(Request $request, SiteVisit $visit, VisitWorkflow $workflow): RedirectResponse
    {
        $this->authorizeOwn($request, $visit);

        $submitted = Measurement::query()->where('site_visit_id', $visit->id)->where('status', MeasurementStatus::Submitted->value)->exists();
        if (! $submitted) {
            return back()->with('error', 'Envía la medición antes de completar la visita.');
        }

        $workflow->complete($visit, $request->user());

        return to_route('tecnico.hoy')->with('success', 'Visita completada. ¡Gracias!');
    }

    /** Abre (o crea) el borrador de medición de la visita. */
    public function measure(Request $request, SiteVisit $visit, MeasurementService $service): RedirectResponse
    {
        $this->authorizeOwn($request, $visit);
        $status = $visit->status;
        if (! in_array($status, [VisitStatus::InProgress, VisitStatus::Completed], true)) {
            return back()->with('error', 'Inicia la visita para registrar la medición.');
        }

        $draft = $service->draftForVisit($visit, $request->user());

        return to_route('tecnico.mediciones.edit', $draft);
    }

    private function authorizeOwn(Request $request, SiteVisit $visit): void
    {
        abort_unless($visit->technician_id === $request->user()?->id, 403, 'Esta visita está asignada a otra persona.');
    }

    /** @return array<string, mixed> */
    private function visitCard(SiteVisit $visit): array
    {
        return [
            'uuid' => $visit->uuid,
            'folio' => $visit->folio,
            'status' => P::enum($visit->status),
            'starts_at' => P::date($visit->starts_at),
            'ends_at' => P::date($visit->ends_at),
            'customer_first_name' => $visit->customer->first_name,
            'address' => P::address($visit->address),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function todayInstallations(User $user): array
    {
        try {
            if (! class_exists(InstallationOrder::class)) {
                return [];
            }

            return InstallationOrder::query()
                ->where('lead_installer_id', $user->id)
                ->whereDate('scheduled_start', now()->toDateString())
                ->with(['project:id,uuid,folio,name,address_id,customer_id', 'project.address', 'project.customer:id,first_name'])
                ->orderBy('scheduled_start')
                ->get()
                ->map(fn ($o) => [
                    'id' => $o->id,
                    'folio' => $o->folio,
                    'status' => P::enum($o->status),
                    'scheduled_start' => P::date($o->scheduled_start),
                    'scheduled_end' => P::date($o->scheduled_end),
                    'project' => $o->project?->name,
                    'customer_first_name' => $o->project?->customer?->first_name,
                    'address' => P::address($o->project?->address),
                    'url' => '/tecnico/instalaciones/'.$o->id,
                ])->values()->all();
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }
}
