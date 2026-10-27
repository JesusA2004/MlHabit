<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Enums\PrequalificationStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\Prequalification;
use App\Models\User;
use App\Notifications\Crm\CustomerNotifier;
use App\Notifications\Crm\PrequalificationRequested;
use App\Services\ActivityLogger;
use App\Services\Crm\PrequalificationService;
use App\Support\Crm\CrmLinks;
use App\Support\Crm\Options;
use App\Support\Crm\Present;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /admin/precalificaciones: bandeja y expediente de revisión manual.
 * Los documentos solo se listan con prequalification.view + sensitive.view.
 */
class PrequalificationController extends Controller
{
    private const ACTION_LABELS = [
        'precalificacion.iniciada' => 'Precalificación iniciada',
        'precalificacion.enviada' => 'El cliente envió su información',
        'precalificacion.estado' => 'Cambio de estado',
        'precalificacion.enlace_reenviado' => 'Enlace enviado al cliente',
    ];

    public function __construct(private readonly PrequalificationService $service) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Prequalification::class);

        $filters = $request->validate([
            'estado' => ['nullable', Rule::enum(PrequalificationStatus::class)],
            'q' => ['nullable', 'string', 'max:120'],
            'bandeja' => ['nullable', Rule::in(['por_revisar', 'en_revision', 'todas'])],
        ]);
        $tray = $filters['bandeja'] ?? 'por_revisar';

        $query = Prequalification::query()->with(['customer', 'lead', 'reviewer']);
        match ($tray) {
            'por_revisar' => $query->where('status', PrequalificationStatus::Submitted->value)->orderBy('submitted_at'),
            'en_revision' => $query->whereIn('status', [PrequalificationStatus::InReview->value, PrequalificationStatus::InfoRequested->value])->orderByDesc('updated_at'),
            default => $query->orderByDesc('created_at'),
        };
        $query->when($filters['estado'] ?? null, fn ($q, $v) => $q->where('status', $v));
        if ($term = trim((string) ($filters['q'] ?? ''))) {
            $query->where(fn (Builder $q) => $q->where('folio', 'like', "%{$term}%")
                ->orWhereHas('customer', fn (Builder $c) => $c->whereRaw("CONCAT(first_name, ' ', COALESCE(last_name, '')) LIKE ?", ["%{$term}%"])));
        }

        return Inertia::render('admin/precalificaciones/Index', [
            'prequalifications' => $query->paginate(25)->withQueryString()->through(fn (Prequalification $p) => Present::prequalificationRow($p)),
            'filters' => ['bandeja' => $tray, ...array_filter($filters, fn ($v) => $v !== null && $v !== '')],
            'counts' => [
                'por_revisar' => Prequalification::query()->where('status', PrequalificationStatus::Submitted->value)->count(),
                'en_revision' => Prequalification::query()->whereIn('status', [PrequalificationStatus::InReview->value, PrequalificationStatus::InfoRequested->value])->count(),
            ],
            'options' => ['statuses' => Options::of(PrequalificationStatus::class)],
        ]);
    }

    public function show(Request $request, Prequalification $prequalification): Response
    {
        Gate::authorize('viewSummary', $prequalification);

        /** @var User $user */
        $user = $request->user();
        $prequalification->load(['customer', 'lead', 'reviewer']);
        $canDocs = $user->can('viewDocuments', $prequalification);

        $audit = ActivityLog::query()
            ->where('subject_type', Prequalification::class)
            ->where('subject_id', $prequalification->id)
            ->with('user:id,name')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'title' => self::ACTION_LABELS[$log->action] ?? $log->action,
                'description' => isset($log->changes['after']['status'])
                    ? (PrequalificationStatus::tryFrom((string) $log->changes['after']['status'])?->label())
                    : null,
                'actor' => $log->user !== null ? $log->user->name : 'Cliente / sistema',
                'date' => $log->created_at?->toIso8601String(),
            ])->values();

        return Inertia::render('admin/precalificaciones/Show', [
            'prequalification' => [
                ...Present::prequalificationRow($prequalification),
                'requestedAmount' => $prequalification->requested_amount,
                'preferredTermMonths' => $prequalification->preferred_term_months,
                'incomeRange' => Options::maybe($prequalification->income_range),
                'employmentType' => Options::maybe($prequalification->employment_type),
                'applicantNotes' => $prequalification->applicant_notes,
                'decisionNotes' => $prequalification->decision_notes,
                'customerPhone' => $prequalification->customer->phone,
                'customerEmail' => $prequalification->customer->email,
            ],
            'documents' => $canDocs
                ? $prequalification->mediaWithRole(Prequalification::DOCUMENT_ROLE)->get()->map(fn ($m) => Present::media($m))->values()
                : null,
            'documentsCount' => $prequalification->attachments()->where('role', Prequalification::DOCUMENT_ROLE)->count(),
            'audit' => $audit,
            'transitions' => Options::of($prequalification->status->allowedTransitions()),
            'customerLink' => CrmLinks::prequalification($prequalification->lead),
            'leadUrl' => $user->can('view', $prequalification->lead) ? route('admin.prospectos.show', $prequalification->lead) : null,
            'can' => [
                'review' => $user->can('review', $prequalification),
                'viewDocuments' => $canDocs,
            ],
        ]);
    }

    public function transition(Request $request, Prequalification $prequalification): RedirectResponse
    {
        Gate::authorize('review', $prequalification);
        $data = $request->validate([
            'status' => ['required', Rule::enum(PrequalificationStatus::class)],
            'notes' => ['nullable', 'string', 'max:4000'],
            'notify' => ['boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $to = PrequalificationStatus::from($data['status']);
        $this->service->transition($prequalification, $to, $user, $data['notes'] ?? null, (bool) ($data['notify'] ?? true));

        return back()->with('success', "Precalificación: {$to->label()}.");
    }

    public function start(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('startPrequalification', $lead);
        $data = $request->validate([
            'send_link' => ['boolean'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $p = $this->service->start($lead, $user, (bool) ($data['send_link'] ?? true), $data['message'] ?? null);

        return $user->can('viewSummary', $p)
            ? redirect()->route('admin.precalificaciones.show', $p)->with('success', "Precalificación {$p->folio} iniciada.")
            : back()->with('success', "Precalificación {$p->folio} iniciada. Enviamos el enlace al cliente.");
    }

    public function resendLink(Request $request, Prequalification $prequalification, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('viewSummary', $prequalification);
        abort_unless($prequalification->status->acceptsCustomerInput(), 422, 'La solicitud no está esperando información del cliente.');

        $prequalification->loadMissing('customer');
        CustomerNotifier::send($prequalification->customer, new PrequalificationRequested($prequalification));
        $logger->log('precalificacion.enlace_reenviado', $prequalification);

        return back()->with('success', 'Enviamos el enlace al cliente.');
    }
}
