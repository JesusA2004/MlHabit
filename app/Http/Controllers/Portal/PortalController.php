<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ContractStatus;
use App\Enums\DesignStatus;
use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResolvesPortalCustomer;
use App\Http\Controllers\Portal\Support\PortalFeed;
use App\Http\Presenters\Projects\ProjectPresenter as P;
use App\Models\Incident;
use App\Models\InstallationOrder;
use App\Models\Project;
use App\Models\Quote;
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
        $customerIds = $this->customerIds($request);
        $projects = $this->projects($customer);
        $project = $this->currentProject($request, $projects);

        $appointments = PortalFeed::appointments($customerIds);
        $requests = PortalFeed::requests($customerIds);
        $payments = PortalFeed::payments($customerIds);
        $events = $project ? array_values($project->events()
            ->where('visibility', 'client')
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->limit(30)->get()
            ->map(fn ($e) => P::event($e, forClient: true))->all()) : [];

        $actions = $project ? $this->pendingActions($project) : [];
        // Pagos por completar fuera del proyecto (visita apartada, asesoría).
        foreach ($payments as $payment) {
            if ($payment['awaiting'] && $payment['pay_url'] && ! str_starts_with((string) $payment['pay_url'], '/portal/pagos/anticipo/')) {
                $actions[] = [
                    'key' => "payment-{$payment['uuid']}",
                    'title' => 'Completa tu pago',
                    'description' => "{$payment['subject']['label']} · {$payment['folio']}",
                    'href' => $payment['pay_url'],
                    'cta' => 'Pagar ahora',
                    'tone' => 'warning',
                    'amount' => $payment['amount'],
                    'external' => true,
                ];
            }
        }

        $quotesPending = $projects->isEmpty() ? 0 : Quote::query()
            ->whereIn('project_id', $projects->pluck('id'))
            ->where('status', QuoteStatus::Sent->value)
            ->get(['id', 'valid_until', 'status'])
            ->reject(fn (Quote $q) => $q->isPastValidity())
            ->count();

        return Inertia::render('portal/Inicio', [
            'firstName' => $customer->first_name ?? explode(' ', (string) $request->user()?->name)[0],
            'projects' => $this->projectOptions($projects),
            'project' => $project ? $this->projectCard($project) : null,
            'journey' => P::journey(),
            'events' => $events,
            'actions' => $actions,
            'nextAppointment' => $appointments['upcoming'][0] ?? null,
            'upcomingCount' => count($appointments['upcoming']),
            'advisor' => $this->advisor($project),
            'requests' => array_slice($requests, 0, 3),
            'requestStages' => PortalFeed::REQUEST_STAGES,
            'stats' => [
                'open_requests' => count(array_filter($requests, fn ($r) => ! $r['closed'] && $r['project'] === null)),
                'quotes_pending' => $quotesPending,
                'upcoming' => count($appointments['upcoming']),
                ...PortalFeed::paymentTotals($payments),
            ],
            'activity' => $this->activity($events, $requests, $payments, $appointments),
        ]);
    }

    /**
     * Actividad reciente combinada: novedades del proyecto, pagos,
     * solicitudes y citas, de la más nueva a la más vieja.
     *
     * @param  list<array<string, mixed>>  $events
     * @param  list<array<string, mixed>>  $requests
     * @param  list<array<string, mixed>>  $payments
     * @param  array{upcoming: list<array<string, mixed>>, past: list<array<string, mixed>>}  $appointments
     * @return list<array{key: string, kind: string, title: string, description: string|null, date: string|null}>
     */
    private function activity(array $events, array $requests, array $payments, array $appointments): array
    {
        $items = [];
        foreach ($events as $e) {
            $items[] = ['key' => "e-{$e['id']}", 'kind' => 'project', 'title' => (string) $e['title'], 'description' => $e['body'], 'date' => $e['occurred_at']];
        }
        foreach ($payments as $p) {
            if ($p['paid_at']) {
                $items[] = ['key' => "p-{$p['uuid']}", 'kind' => 'payment', 'title' => 'Pago recibido: '.$p['subject']['label'], 'description' => $p['folio'], 'date' => $p['paid_at'], 'amount' => $p['amount']];
            }
        }
        foreach ($requests as $r) {
            $items[] = ['key' => "r-{$r['uuid']}", 'kind' => 'request', 'title' => "Enviaste tu solicitud {$r['folio']}", 'description' => $r['categories'] ? implode(', ', $r['categories']) : null, 'date' => $r['created_at']];
        }
        foreach ($appointments['past'] as $a) {
            if (($a['status']['value'] ?? null) === 'completed') {
                $items[] = ['key' => "c-{$a['key']}", 'kind' => $a['kind'], 'title' => $a['title'].' realizada', 'description' => $a['with'] ? "Con {$a['with']}" : null, 'date' => $a['starts_at']];
            }
        }

        usort($items, fn ($a, $b) => strcmp((string) $b['date'], (string) $a['date']));

        return array_slice($items, 0, 12);
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
        return Inertia::render('portal/Citas', PortalFeed::appointments($this->customerIds($request)));
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
