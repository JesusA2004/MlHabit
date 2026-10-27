<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Enums\PreferredContact;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\AddressRequest;
use App\Models\Address;
use App\Models\Consultation;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CoverageService;
use App\Services\Crm\CustomerMatcher;
use App\Support\Crm\Options;
use App\Support\Crm\Present;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /admin/clientes: personas (prospectos o clientes) con sus domicilios,
 * solicitudes, asesorías y proyectos.
 */
class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Customer::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'tipo' => ['nullable', Rule::in(['todos', 'con_proyecto', 'portal'])],
        ]);

        $query = Customer::query()
            ->withCount(['leads', 'projects'])
            ->with(['leads' => fn ($q) => $q->latest('id')->limit(1)]);

        if ($term = trim((string) ($filters['q'] ?? ''))) {
            $digits = preg_replace('/\D/', '', $term);
            $query->where(function (Builder $q) use ($term, $digits) {
                $q->whereRaw("CONCAT(first_name, ' ', COALESCE(last_name, '')) LIKE ?", ["%{$term}%"])
                    ->orWhere('email', 'like', "%{$term}%");
                if ($digits !== null && strlen($digits) >= 4) {
                    $q->orWhere('phone', 'like', "%{$digits}%");
                }
            });
        }
        match ($filters['tipo'] ?? 'todos') {
            'con_proyecto' => $query->has('projects'),
            'portal' => $query->whereNotNull('user_id'),
            default => null,
        };

        $page = $query->orderByDesc('id')->paginate(25)->withQueryString()->through(fn (Customer $c) => [
            ...Present::customerMini($c),
            'leadsCount' => $c->leads_count,
            'projectsCount' => $c->projects_count,
            'lastLead' => $c->leads->first() ? [
                'folio' => $c->leads->first()->folio,
                'status' => Options::one($c->leads->first()->status),
            ] : null,
            'createdAt' => $c->created_at?->toIso8601String(),
        ]);

        return Inertia::render('admin/clientes/Index', [
            'customers' => $page,
            'filters' => ['tipo' => $filters['tipo'] ?? 'todos', 'q' => $filters['q'] ?? ''],
        ]);
    }

    public function show(Request $request, Customer $customer, CoverageService $coverage): Response
    {
        Gate::authorize('view', $customer);

        /** @var User $user */
        $user = $request->user();
        $customer->load(['addresses', 'leads.assignee', 'projects', 'user:id,email,last_login_at']);

        $consultations = Consultation::query()
            ->whereIn('lead_id', $customer->leads->pluck('id'))
            ->with('advisor')
            ->orderByDesc('starts_at')
            ->limit(20)
            ->get();

        return Inertia::render('admin/clientes/Show', [
            'customer' => [
                ...Present::customerMini($customer),
                'lastName' => $customer->last_name,
                'notes' => $customer->notes,
                'privacyAcceptedAt' => $customer->privacy_accepted_at?->toIso8601String(),
                'createdAt' => $customer->created_at?->toIso8601String(),
                'portal' => $customer->user ? ['email' => $customer->user->email, 'lastLoginAt' => $customer->user->last_login_at?->toIso8601String()] : null,
            ],
            'addresses' => $customer->addresses->map(fn (Address $a) => Present::address($a, $coverage))->values(),
            'leads' => $customer->leads->sortByDesc('id')->map(fn (Lead $l) => [
                'uuid' => $l->uuid,
                'folio' => $l->folio,
                'status' => Options::one($l->status),
                'assignee' => Present::user($l->assignee),
                'createdAt' => $l->created_at?->toIso8601String(),
                'url' => $user->can('view', $l) ? route('admin.prospectos.show', $l) : null,
            ])->values(),
            'projects' => $customer->projects->map(fn (Project $p) => [
                'uuid' => $p->uuid,
                'folio' => $p->folio,
                'name' => $p->name,
                'status' => Options::one($p->status),
                'url' => Route::has('admin.proyectos.show') ? route('admin.proyectos.show', $p) : null,
            ])->values(),
            'consultations' => $consultations->map(fn (Consultation $c) => Present::consultation($c))->values(),
            'options' => ['preferredContacts' => Options::of(PreferredContact::class)],
            'can' => [
                'update' => $user->can('update', $customer),
                'invite' => $user->can('invite', $customer) && $customer->user_id === null && $customer->email !== null,
            ],
            'links' => [
                'invite' => '/admin/clientes/'.$customer->uuid.'/invitacion',
                'coverage' => route('admin.cobertura.verificar'),
            ],
        ]);
    }

    public function update(Request $request, Customer $customer, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('update', $customer);

        $request->merge([
            'phone' => CustomerMatcher::normalizePhone($request->input('phone')),
            'email' => CustomerMatcher::normalizeEmail($request->input('email')),
        ]);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'digits:10'],
            'preferred_contact' => ['nullable', Rule::enum(PreferredContact::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $customer->fill($data);
        $logger->logModelChanges('cliente.actualizado', $customer, ['first_name', 'last_name', 'email', 'phone', 'preferred_contact']);
        $customer->save();

        return back()->with('success', 'Datos del cliente actualizados.');
    }

    public function storeAddress(AddressRequest $request, Customer $customer, CoverageService $coverage, ActivityLogger $logger): RedirectResponse
    {
        $address = $customer->addresses()->create([
            ...$request->validated(),
            'coverage_zone_id' => $coverage->zoneForPostalCode($request->validated('postal_code'))?->id,
        ]);
        $logger->log('cliente.domicilio_agregado', $customer, ['address_id' => $address->id]);

        return back()->with('success', 'Domicilio agregado.');
    }

    public function updateAddress(AddressRequest $request, Customer $customer, Address $address, CoverageService $coverage, ActivityLogger $logger): RedirectResponse
    {
        abort_unless($address->customer_id === $customer->id, 404);

        $address->fill([
            ...$request->validated(),
            'coverage_zone_id' => $coverage->zoneForPostalCode($request->validated('postal_code'))?->id,
        ]);
        $logger->logModelChanges('cliente.domicilio_actualizado', $address);
        $address->save();

        return back()->with('success', 'Domicilio actualizado.');
    }

    public function destroyAddress(Request $request, Customer $customer, Address $address, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('update', $customer);
        abort_unless($address->customer_id === $customer->id, 404);

        if ($this->addressInUse($address)) {
            return back()->withErrors(['address' => 'Este domicilio ya se usa en una visita o proyecto; no se puede eliminar.']);
        }

        $logger->log('cliente.domicilio_eliminado', $customer, ['before' => ['address' => $address->one_line]]);
        $address->delete();

        return back()->with('success', 'Domicilio eliminado.');
    }

    private function addressInUse(Address $address): bool
    {
        if (Project::query()->where('address_id', $address->id)->exists()) {
            return true;
        }

        return Schema::hasTable('site_visits')
            && DB::table('site_visits')->where('address_id', $address->id)->exists();
    }
}
