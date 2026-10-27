<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\CoverageZoneRequest;
use App\Models\CoveragePostalCode;
use App\Models\CoverageZone;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CoverageService;
use App\Services\Crm\CoverageZoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /admin/cobertura: zonas operativas, códigos postales y técnicos.
 */
class CoverageZoneController extends Controller
{
    public function __construct(private readonly CoverageZoneService $zones) {}

    public function index(Request $request): Response
    {
        Gate::authorize('coverage.view');

        $q = trim((string) $request->query('q', ''));
        $zones = CoverageZone::query()
            ->withCount('postalCodes')
            ->with(['technicians:id,name,is_active'])
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
                ->orWhere('municipality', 'like', "%{$q}%")
                ->orWhereHas('postalCodes', fn ($p) => $p->where('postal_code', 'like', "{$q}%"))))
            ->orderByDesc('is_active')
            ->orderBy('state')
            ->orderBy('name')
            ->get();

        $selectedId = (int) $request->query('zona', 0);
        $selected = $selectedId ? $zones->firstWhere('id', $selectedId) : null;

        return Inertia::render('admin/cobertura/Index', [
            'zones' => $zones->map(fn (CoverageZone $z) => $this->present($z))->values(),
            'selected' => $selected ? [
                ...$this->present($selected),
                'postalCodes' => CoveragePostalCode::query()->where('coverage_zone_id', $selected->id)->orderBy('postal_code')->pluck('postal_code'),
            ] : null,
            'technicians' => User::query()->role(UserRole::Technician)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name])->values(),
            'stats' => [
                'zones' => $zones->count(),
                'active' => $zones->where('is_active', true)->count(),
                'postalCodes' => (int) CoveragePostalCode::query()->count(),
                'withoutTechnicians' => $zones->filter(fn (CoverageZone $z) => $z->is_active && $z->visits_enabled && $z->technicians->where('is_active', true)->isEmpty())->count(),
            ],
            'filters' => ['q' => $q],
            'can' => ['manage' => $request->user()?->can('coverage.manage') ?? false],
        ]);
    }

    public function store(CoverageZoneRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $zone = CoverageZone::query()->create($request->validated());
        $logger->log('cobertura.zona_creada', $zone, ['after' => $request->validated()]);

        return redirect()->route('admin.cobertura.index', ['zona' => $zone->id])->with('success', "Zona «{$zone->name}» creada. Ahora agrega sus códigos postales.");
    }

    public function update(CoverageZoneRequest $request, CoverageZone $zone, ActivityLogger $logger): RedirectResponse
    {
        $zone->fill($request->validated());
        $logger->logModelChanges('cobertura.zona_actualizada', $zone);
        $zone->save();

        return back()->with('success', 'Zona actualizada.');
    }

    public function destroy(Request $request, CoverageZone $zone, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('coverage.manage');

        $inUse = DB::table('leads')->where('coverage_zone_id', $zone->id)->exists()
            || DB::table('addresses')->where('coverage_zone_id', $zone->id)->exists()
            || DB::table('site_visits')->where('coverage_zone_id', $zone->id)->exists();

        if ($inUse) {
            return back()->withErrors(['zone' => 'Esta zona ya tiene prospectos, domicilios o visitas. Desactívala en lugar de eliminarla.']);
        }

        $logger->log('cobertura.zona_eliminada', $zone, ['before' => ['name' => $zone->name]]);
        $zone->delete();

        return redirect()->route('admin.cobertura.index')->with('success', 'Zona eliminada.');
    }

    public function addPostalCodes(Request $request, CoverageZone $zone): RedirectResponse
    {
        Gate::authorize('coverage.manage');
        $data = $request->validate([
            'postal_codes' => ['required', 'string', 'max:20000'],
            'move' => ['boolean'],
        ], ['postal_codes.required' => 'Pega al menos un código postal.']);

        $result = $this->zones->addPostalCodes($zone, $data['postal_codes'], (bool) ($data['move'] ?? false));

        $parts = [];
        if ($result['added']) {
            $parts[] = count($result['added']).' agregados';
        }
        if ($result['moved']) {
            $parts[] = count($result['moved']).' movidos a esta zona';
        }
        if ($result['existing']) {
            $parts[] = count($result['existing']).' ya estaban';
        }

        // Resultado detallado (conflictos con otras zonas, inválidos) para la UI.
        Inertia::flash('coverageImport', $result);

        return $parts ? back()->with('success', 'Códigos postales: '.implode(', ', $parts).'.') : back();
    }

    public function removePostalCode(Request $request, CoverageZone $zone, string $postalCode): RedirectResponse
    {
        Gate::authorize('coverage.manage');
        $this->zones->removePostalCodes($zone, [$postalCode]);

        return back()->with('success', "C.P. {$postalCode} quitado de la zona.");
    }

    public function syncTechnicians(Request $request, CoverageZone $zone): RedirectResponse
    {
        Gate::authorize('coverage.manage');
        $data = $request->validate(['technician_ids' => ['present', 'array'], 'technician_ids.*' => ['integer']]);

        $this->zones->syncTechnicians($zone, array_values(array_map('intval', $data['technician_ids'])));

        return back()->with('success', 'Técnicos de la zona actualizados.');
    }

    /** Verificador interno: más detalle que el endpoint público. */
    public function check(Request $request, CoverageService $coverage): JsonResponse
    {
        abort_unless($request->user()?->can('coverage.view') || $request->user()?->can('customers.view') || $request->user()?->can('leads.view'), 403);
        $cp = preg_replace('/\D/', '', (string) $request->query('cp', '')) ?? '';

        if (strlen($cp) !== 5) {
            return response()->json(['valid' => false, 'message' => 'Escribe un código postal de 5 dígitos.'], 422);
        }

        $row = CoveragePostalCode::query()->where('postal_code', $cp)->with('zone.technicians:id,name,is_active')->first();
        $zone = $row?->zone;

        return response()->json([
            'valid' => true,
            'postalCode' => $cp,
            'registered' => $row !== null,
            'covered' => $coverage->zoneForPostalCode($cp) !== null,
            'acceptsVisits' => $coverage->acceptsVisits($cp),
            'zone' => $zone ? [
                'id' => $zone->id,
                'name' => $zone->name,
                'isActive' => $zone->is_active,
                'visitsEnabled' => $zone->visits_enabled,
                'technicians' => $zone->technicians->where('is_active', true)->pluck('name')->values(),
            ] : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function present(CoverageZone $zone): array
    {
        $activeTechs = $zone->technicians->where('is_active', true);

        return [
            'id' => $zone->id,
            'name' => $zone->name,
            'state' => $zone->state,
            'municipality' => $zone->municipality,
            'isActive' => $zone->is_active,
            'visitsEnabled' => $zone->visits_enabled,
            'centerLat' => $zone->center_lat,
            'centerLng' => $zone->center_lng,
            'radiusKm' => $zone->radius_km,
            'travelBufferMinutes' => $zone->travel_buffer_minutes,
            'maxVisitsPerDay' => $zone->max_visits_per_day,
            'notes' => $zone->notes,
            'postalCodesCount' => (int) ($zone->postal_codes_count ?? 0),
            'technicians' => $zone->technicians->map(fn ($u) => ['id' => (int) $u->getKey(), 'name' => (string) $u->getAttribute('name')])->values(),
            'acceptsVisits' => $zone->is_active && $zone->visits_enabled && $activeTechs->isNotEmpty(),
        ];
    }
}
