<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ConsultationStatus;
use App\Enums\ContractStatus;
use App\Enums\DesignStatus;
use App\Enums\QuoteStatus;
use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResolvesPortalCustomer;
use App\Http\Presenters\Projects\ProjectPresenter as P;
use App\Models\Consultation;
use App\Models\Customer;
use App\Models\Incident;
use App\Models\InstallationOrder;
use App\Models\Project;
use App\Models\SiteVisit;
use App\Models\Warranty;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    use ResolvesPortalCustomer;

    public function home(Request $request): Response
    {
        $customer = $this->customer($request);
        $projects = $this->projects($customer);
        $project = $this->currentProject($request, $projects);

        return Inertia::render('portal/Inicio', [
            'firstName' => $customer->first_name ?? explode(' ', (string) $request->user()?->name)[0],
            'projects' => $this->projectOptions($projects),
            'project' => $project ? $this->projectCard($project) : null,
            'journey' => P::journey(),
            'events' => $project ? $project->events()
                ->where('visibility', 'client')
                ->orderByDesc('occurred_at')->orderByDesc('id')
                ->limit(30)->get()
                ->map(fn ($e) => P::event($e, forClient: true))->values() : [],
            'actions' => $project ? $this->pendingActions($project) : [],
            'nextAppointment' => $customer ? ($this->appointmentsOf($customer)['upcoming'][0] ?? null) : null,
            'advisor' => $this->advisor($project),
        ]);
    }

    /** Selector de proyecto (solo entre los del propio cliente). */
    public function select(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->belongsToUser($request->user()), 404);
        $request->session()->put(self::SESSION_PROJECT, $project->uuid);

        return to_route('portal.inicio');
    }

    public function appointments(Request $request): Response
    {
        $customer = $this->customer($request);

        return Inertia::render('portal/Citas', $customer ? $this->appointmentsOf($customer) : ['upcoming' => [], 'past' => []]);
    }

    public function documents(Request $request): Response
    {
        $customer = $this->customer($request);
        $projects = $this->projects($customer);

        $groups = $projects->map(function (Project $project) {
            $files = $project->attachments()->where('role', 'client_document')->with('media')->get()->map(fn ($a) => P::file($a))->values();
            $quotes = $project->quotes()
                ->whereIn('status', [QuoteStatus::Sent->value, QuoteStatus::Accepted->value])
                ->orderByDesc('version')->get()
                ->map(fn ($q) => ['uuid' => $q->uuid, 'folio' => $q->folio, 'title' => $q->title, 'status' => $q->status->clientLabel(), 'print_url' => "/cotizaciones/{$q->uuid}/imprimir"])
                ->values();

            return [
                'project' => ['uuid' => $project->uuid, 'folio' => $project->folio, 'name' => $project->name],
                'files' => $files,
                'quotes' => $quotes,
            ];
        })->values();

        return Inertia::render('portal/Documentos', ['groups' => $groups]);
    }

    /** Incidencias y garantías (lectura de modelos de ops). */
    public function support(Request $request): Response
    {
        $customer = $this->customer($request);
        $projects = $this->projects($customer);
        $ids = $projects->pluck('id')->all();

        $incidents = $this->attempt(fn () => Incident::query()
            ->whereIn('project_id', $ids)
            ->where('visible_to_client', true)
            ->latest('id')->limit(50)->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'folio' => $i->folio,
                'title' => $i->title,
                'status' => P::enum($i->status),
                'created_at' => P::date($i->created_at),
                'resolved_at' => P::date($i->resolved_at),
                'resolution' => $i->resolution,
            ])->values()->all(), []);

        $warranties = $this->attempt(fn () => Warranty::query()
            ->whereIn('project_id', $ids)
            ->latest('starts_on')->get()
            ->map(fn ($w) => [
                'id' => $w->id,
                'folio' => $w->folio,
                'type' => P::enum($w->type),
                'status' => P::enum($w->status),
                'starts_on' => P::day($w->starts_on),
                'ends_on' => P::day($w->ends_on),
                'scope' => $w->scope,
                'exclusions' => $w->exclusions,
            ])->values()->all(), []);

        return Inertia::render('portal/Garantias', [
            'incidents' => $incidents,
            'warranties' => $warranties,
            'reportUrl' => $this->routeUrl(['portal.incidencias.create', 'portal.incidencias.nueva']),
            'hasProjects' => $projects->isNotEmpty(),
        ]);
    }

    public function help(Request $request): Response
    {
        $customer = $this->customer($request);
        $project = $this->currentProject($request, $this->projects($customer));
        $contact = Settings::group('contact');

        return Inertia::render('portal/Ayuda', [
            'contact' => [
                'phone' => $contact['phone'] ?? null,
                'whatsapp' => $contact['whatsapp'] ?? null,
                'email' => $contact['email'] ?? null,
                'address' => $contact['address'] ?? null,
                'hours' => $contact['hours'] ?? null,
            ],
            'advisor' => $this->advisor($project),
            'project' => $project ? ['folio' => $project->folio, 'name' => $project->name] : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function projectCard(Project $project): array
    {
        $project->loadMissing(['address']);

        return [
            ...P::summary($project),
            'address' => $project->address?->one_line,
            'cancelled' => $project->status->value === 'cancelled',
            'completed' => in_array($project->status->value, ['completed', 'warranty_service'], true),
        ];
    }

    /** @return array{name: string, email: string|null, phone: string|null}|null */
    private function advisor(?Project $project): ?array
    {
        $advisor = $project?->advisor;

        return $advisor ? ['name' => $advisor->name, 'email' => $advisor->email, 'phone' => $advisor->phone] : null;
    }

    /**
     * Acciones pendientes del cliente, en orden de urgencia.
     *
     * @return list<array{key: string, title: string, description: string, href: string, cta: string, tone: string}>
     */
    private function pendingActions(Project $project): array
    {
        $actions = [];

        $contract = $project->contract()->first();
        if ($contract && in_array($contract->status, [ContractStatus::PendingDeposit, ContractStatus::DepositRefunded], true)) {
            $actions[] = [
                'key' => 'deposit',
                'title' => 'Paga tu anticipo',
                'description' => 'Con el anticipo apartamos la fabricación de tu proyecto.',
                'href' => "/portal/pagos/anticipo/{$contract->uuid}",
                'cta' => 'Pagar anticipo',
                'tone' => 'warning',
                'amount' => $contract->deposit_due_amount,
            ];
        }

        foreach ($project->quotes()->where('status', QuoteStatus::Sent->value)->orderByDesc('version')->get() as $quote) {
            if ($quote->isPastValidity()) {
                continue;
            }
            $actions[] = [
                'key' => "quote-{$quote->uuid}",
                'title' => 'Revisa tu cotización',
                'description' => "{$quote->title} · vigente hasta el ".$quote->valid_until?->format('d/m/Y'),
                'href' => "/portal/cotizaciones/{$quote->uuid}",
                'cta' => 'Ver cotización',
                'tone' => 'info',
                'amount' => $quote->total,
            ];
        }

        $design = $project->designVersions()->where('status', DesignStatus::SentToClient->value)->orderByDesc('number')->first();
        if ($design) {
            $actions[] = [
                'key' => "design-{$design->id}",
                'title' => "Revisa tu diseño v{$design->number}",
                'description' => 'Apruébalo o cuéntanos qué te gustaría cambiar.',
                'href' => '/portal/disenos',
                'cta' => 'Revisar diseño',
                'tone' => 'brand',
                'amount' => null,
            ];
        }

        $orders = $this->attempt(fn () => InstallationOrder::query()
            ->where('project_id', $project->id)
            ->whereNotNull('evidence_submitted_at')
            ->whereNull('client_signed_at')
            ->get(['id', 'folio']), collect());
        foreach ($orders as $order) {
            $actions[] = [
                'key' => "installation-{$order->id}",
                'title' => 'Confirma tu instalación',
                'description' => 'Revisa el trabajo terminado y danos tu conformidad.',
                'href' => "/portal/instalaciones/{$order->id}",
                'cta' => 'Confirmar instalación',
                'tone' => 'success',
                'amount' => null,
            ];
        }

        return $actions;
    }

    /**
     * Citas del cliente: asesorías (vía sus prospectos) y visitas técnicas.
     *
     * @return array{upcoming: array<int, array<string, mixed>>, past: array<int, array<string, mixed>>}
     */
    private function appointmentsOf(Customer $customer): array
    {
        $items = collect();

        $items = $items->merge($this->attempt(fn () => Consultation::query()
            ->whereHas('lead', fn ($q) => $q->where('customer_id', $customer->id))
            ->whereNotIn('status', [ConsultationStatus::Rescheduled->value])
            ->with('advisor:id,name')
            ->latest('starts_at')->limit(30)->get()
            ->map(fn (Consultation $c) => [
                'key' => "a-{$c->uuid}",
                'kind' => 'consultation',
                'title' => 'Asesoría',
                'starts_at' => P::date($c->starts_at),
                'ends_at' => P::date($c->ends_at),
                'status' => P::enum($c->status),
                'with' => $c->advisor->name,
                'channel' => $c->channel->label(),
                'meeting_url' => $c->meeting_url,
                'location' => null,
                'active' => in_array($c->status, [ConsultationStatus::Scheduled, ConsultationStatus::Confirmed], true),
                'reschedule_url' => null,
            ]), collect()));

        $items = $items->merge($this->attempt(fn () => SiteVisit::query()
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', [VisitStatus::PendingPayment->value, VisitStatus::PaymentProcessing->value, VisitStatus::Expired->value])
            ->with(['technician:id,name', 'address'])
            ->latest('starts_at')->limit(30)->get()
            ->map(fn (SiteVisit $v) => [
                'key' => "v-{$v->uuid}",
                'kind' => 'visit',
                'title' => 'Visita técnica de medición',
                'starts_at' => P::date($v->starts_at),
                'ends_at' => P::date($v->ends_at),
                'status' => P::enum($v->status),
                'with' => $v->technician?->name,
                'channel' => null,
                'meeting_url' => null,
                'location' => $v->address->one_line,
                'active' => $v->status->isUpcoming(),
                'reschedule_url' => null,
            ]), collect()));

        $now = now();
        $upcoming = $items->filter(fn ($i) => $i['active'] && $i['ends_at'] && $now->lt($i['ends_at']))->sortBy('starts_at')->values()->all();
        $past = $items->reject(fn ($i) => $i['active'] && $i['ends_at'] && $now->lt($i['ends_at']))->sortByDesc('starts_at')->values()->all();

        return ['upcoming' => $upcoming, 'past' => $past];
    }

    /** @param  list<string>  $names */
    private function routeUrl(array $names): ?string
    {
        foreach ($names as $name) {
            if (Route::has($name)) {
                return route($name);
            }
        }

        return null;
    }
}
