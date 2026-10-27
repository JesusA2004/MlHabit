<?php

namespace App\Http\Controllers\Admin\Operations;

use App\Enums\InstallationStatus;
use App\Enums\TechnicianStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Presenters\Operations\OpsPresenter;
use App\Http\Requests\Operations\TechnicianRequest;
use App\Models\CoverageZone;
use App\Models\InstallationOrder;
use App\Models\QualityInspection;
use App\Models\Supplier;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Services\Operations\Attachments;
use App\Services\Operations\TechnicianService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** /admin/tecnicos — alta por invitación, zonas, documentos y carga de trabajo. */
class TechnicianController extends Controller
{
    /** Estados de instalación que cuentan como carga activa. */
    private const ACTIVE_INSTALLATION = [InstallationStatus::Scheduled, InstallationStatus::InProgress];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TechnicianProfile::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::enum(TechnicianStatus::class)],
            'proveedor' => ['nullable', 'integer'],
            'zona' => ['nullable', 'integer'],
        ]);

        $profiles = TechnicianProfile::query()
            ->with(['user:id,name,email,phone,is_active', 'user.coverageZones:id,name', 'supplier:id,name'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->when($filters['estado'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['proveedor'] ?? null, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($filters['zona'] ?? null, fn ($q, $id) => $q->whereHas('user.coverageZones', fn ($z) => $z->whereKey($id)))
            ->join('users', 'users.id', '=', 'technician_profiles.user_id')
            ->orderByDesc('users.is_active')
            ->orderBy('users.name')
            ->select('technician_profiles.*')
            ->paginate(24)
            ->withQueryString();

        $userIds = $profiles->getCollection()->pluck('user_id');
        $workload = InstallationOrder::query()
            ->whereIn('lead_installer_id', $userIds)
            ->whereIn('status', array_map(fn ($s) => $s->value, self::ACTIVE_INSTALLATION))
            ->select('lead_installer_id', DB::raw('count(*) as total'))
            ->groupBy('lead_installer_id')
            ->pluck('total', 'lead_installer_id');

        return Inertia::render('admin/tecnicos/Index', [
            'technicians' => $profiles->through(fn (TechnicianProfile $p) => [
                ...$this->summary($p),
                'activeInstallations' => (int) ($workload[$p->user_id] ?? 0),
            ]),
            'filters' => $filters,
            'options' => $this->options(),
        ]);
    }

    public function store(TechnicianRequest $request, TechnicianService $service): RedirectResponse
    {
        $profile = $service->create($request->safe()->except('zone_ids', 'is_active'), $request->zoneIds(), $request->user());

        return to_route('admin.tecnicos.show', $profile)
            ->with('success', "Enviamos a {$profile->user->email} una invitación para crear su contraseña.");
    }

    public function show(TechnicianProfile $technicianProfile): Response
    {
        Gate::authorize('view', $technicianProfile);
        $profile = $technicianProfile->load(['user.coverageZones:id,name', 'supplier:id,name']);

        $upcoming = InstallationOrder::query()
            ->with(['project:id,uuid,folio,name,status'])
            ->where('lead_installer_id', $profile->user_id)
            ->whereIn('status', array_map(fn ($s) => $s->value, [...self::ACTIVE_INSTALLATION, InstallationStatus::Draft]))
            ->orderByRaw('scheduled_start is null, scheduled_start')
            ->limit(20)
            ->get()
            ->map(fn (InstallationOrder $o) => OpsPresenter::installationRow($o));

        // Visitas (tabla de visits): solo se cuentan, el detalle vive en su módulo.
        $visits = DB::table('site_visits')
            ->where('technician_id', $profile->user_id)
            ->where('starts_at', '>=', now()->startOfDay())
            ->whereNotIn('status', ['cancelled', 'expired', 'pending_payment'])
            ->count();

        $checks = QualityInspection::query()
            ->where('stage', 'quality')
            ->whereNotNull('installation_order_item_id')
            ->whereHas('order', fn ($q) => $q->where('lead_installer_id', $profile->user_id));

        return Inertia::render('admin/tecnicos/Show', [
            'technician' => [
                ...$this->summary($profile),
                'skills' => $profile->skills,
                'notes' => $profile->notes,
                'zoneIds' => $profile->user->coverageZones->pluck('id')->all(),
                'documents' => Attachments::presentMany($profile->mediaWithRole('document')->get()),
                'lastLoginAt' => $profile->user->last_login_at?->toIso8601String(),
            ],
            'workload' => [
                'installations' => $upcoming,
                'upcomingVisits' => $visits,
            ],
            'evaluation' => [
                'reviewed' => (clone $checks)->count(),
                'approved' => (clone $checks)->where('result', 'approved')->count(),
                'completedOrders' => InstallationOrder::query()->where('lead_installer_id', $profile->user_id)
                    ->whereIn('status', ['approved', 'payment_authorized', 'paid'])->count(),
            ],
            'options' => $this->options(),
        ]);
    }

    public function update(TechnicianRequest $request, TechnicianProfile $technicianProfile, TechnicianService $service): RedirectResponse
    {
        $service->update($technicianProfile, $request->safe()->except('zone_ids'), $request->zoneIds());

        return back()->with('success', 'Técnico actualizado.');
    }

    public function resendInvite(Request $request, TechnicianProfile $technicianProfile, TechnicianService $service): RedirectResponse
    {
        Gate::authorize('update', $technicianProfile);
        $service->invite($technicianProfile, $request->user());

        return back()->with('success', 'Enviamos de nuevo la invitación de acceso.');
    }

    public function attachDocuments(Request $request, TechnicianProfile $technicianProfile, Attachments $attachments): RedirectResponse
    {
        Gate::authorize('update', $technicianProfile);
        $data = $request->validate(['media_ids' => ['required', 'array', 'min:1', 'max:20'], 'media_ids.*' => ['integer']]);
        $attachments->attach($technicianProfile, $data['media_ids'], 'document', $request->user());

        return back()->with('success', 'Documentos agregados.');
    }

    public function detachDocument(TechnicianProfile $technicianProfile, int $media, Attachments $attachments): RedirectResponse
    {
        Gate::authorize('update', $technicianProfile);
        $attachments->detach($technicianProfile, $media, 'document');

        return back()->with('success', 'Documento quitado.');
    }

    /** @return array<string, mixed> */
    private function summary(TechnicianProfile $p): array
    {
        /** @var User $user */
        $user = $p->user;

        return [
            'id' => $p->id,
            'userId' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'active' => $user->is_active,
            'status' => $p->status->toArray(),
            'doesVisits' => $p->does_visits,
            'doesInstallations' => $p->does_installations,
            'supplier' => $p->supplier ? ['id' => $p->supplier->id, 'name' => $p->supplier->name] : null,
            'zones' => $user->coverageZones->pluck('name')->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'statuses' => TechnicianStatus::options(),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->all(),
            'zones' => CoverageZone::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($z) => ['value' => $z->id, 'label' => $z->name])->all(),
            'role' => UserRole::Technician->label(),
        ];
    }
}
