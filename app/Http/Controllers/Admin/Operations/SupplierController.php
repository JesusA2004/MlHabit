<?php

namespace App\Http\Controllers\Admin\Operations;

use App\Enums\FabricationStatus;
use App\Enums\InstallationStatus;
use App\Enums\SupplierStatus;
use App\Enums\SupplierType;
use App\Http\Controllers\Controller;
use App\Http\Presenters\Operations\OpsPresenter;
use App\Http\Requests\Operations\SupplierRequest;
use App\Models\CoverageZone;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\SupplierEvaluation;
use App\Services\ActivityLogger;
use App\Services\Operations\Attachments;
use App\Services\Operations\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** /admin/proveedores — directorio de proveedores, contactos, evaluaciones y documentos. */
class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Supplier::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', Rule::enum(SupplierType::class)],
            'estado' => ['nullable', Rule::enum(SupplierStatus::class)],
        ]);

        $suppliers = Supplier::query()
            ->with('coverageZones:id,name')
            ->withCount([
                'fabricationOrders as active_fabrication_count' => fn ($q) => $q->whereNotIn('status', [FabricationStatus::Delivered->value, FabricationStatus::Cancelled->value]),
                'installationOrders as active_installation_count' => fn ($q) => $q->whereNotIn('status', [InstallationStatus::Paid->value, InstallationStatus::Cancelled->value, InstallationStatus::Approved->value]),
                'technicians',
            ])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('legal_name', 'like', "%{$term}%")->orWhere('rfc', 'like', "%{$term}%")))
            ->when($filters['tipo'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['estado'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByRaw("status = 'active' desc")
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Supplier $s) => [
                ...OpsPresenter::supplier($s),
                'zones' => $s->coverageZones->pluck('name')->all(),
                'activeOrders' => (int) $s->active_fabrication_count + (int) $s->active_installation_count,
                'techniciansCount' => (int) $s->technicians_count,
            ]);

        return Inertia::render('admin/proveedores/Index', [
            'suppliers' => $suppliers,
            'filters' => $filters,
            'options' => $this->options(),
        ]);
    }

    public function store(SupplierRequest $request, SupplierService $service): RedirectResponse
    {
        $supplier = $service->save(null, $request->supplierData(), $request->zoneIds());

        return to_route('admin.proveedores.show', $supplier)->with('success', 'Proveedor registrado.');
    }

    public function show(Supplier $supplier): Response
    {
        Gate::authorize('view', $supplier);

        $supplier->load([
            'contacts', 'coverageZones:id,name',
            'evaluations' => fn ($q) => $q->with(['evaluator:id,name', 'project:id,folio,name'])->limit(30),
            'technicians.user:id,name,email,is_active',
        ]);

        $fabrication = $supplier->fabricationOrders()->with('project:id,uuid,folio,name,status')->latest()->limit(15)->get()
            ->map(fn ($o) => ['id' => $o->id, 'kind' => 'fabrication', 'folio' => $o->folio, 'status' => $o->status->toArray(), 'project' => $o->project->folio.' · '.$o->project->name, 'amount' => $o->cost, 'date' => $o->created_at?->toIso8601String()]);
        $installation = $supplier->installationOrders()->with('project:id,uuid,folio,name,status')->latest()->limit(15)->get()
            ->map(fn ($o) => ['id' => $o->id, 'kind' => 'installation', 'folio' => $o->folio, 'status' => $o->status->toArray(), 'project' => $o->project->folio.' · '.$o->project->name, 'amount' => $o->total, 'date' => $o->created_at?->toIso8601String()]);

        return Inertia::render('admin/proveedores/Show', [
            'supplier' => [
                ...OpsPresenter::supplier($supplier),
                'legalName' => $supplier->legal_name,
                'rfc' => $supplier->rfc,
                'capabilities' => $supplier->capabilities,
                'notes' => $supplier->notes,
                'zones' => $supplier->coverageZones->map(fn ($z) => ['id' => $z->id, 'name' => $z->name])->all(),
                'contacts' => $supplier->contacts->map(fn (SupplierContact $c) => $c->only(['id', 'name', 'position', 'email', 'phone', 'is_primary']))->all(),
                'evaluations' => $supplier->evaluations->map(fn (SupplierEvaluation $e) => [
                    'id' => $e->id,
                    'quality' => $e->quality_score,
                    'timeliness' => $e->timeliness_score,
                    'communication' => $e->communication_score,
                    'comments' => $e->comments,
                    'evaluator' => $e->evaluator?->name,
                    'project' => $e->project ? $e->project->folio.' · '.$e->project->name : null,
                    'createdAt' => $e->created_at?->toIso8601String(),
                ])->all(),
                'technicians' => $supplier->technicians->map(fn ($t) => ['id' => $t->id, 'name' => $t->user->name, 'email' => $t->user->email, 'status' => $t->status->toArray(), 'active' => $t->user->is_active])->all(),
                'documents' => Attachments::presentMany($supplier->mediaWithRole('document')->get()),
            ],
            'history' => $fabrication->concat($installation)->sortByDesc('date')->values()->all(),
            'options' => $this->options(),
        ]);
    }

    public function update(SupplierRequest $request, Supplier $supplier, SupplierService $service): RedirectResponse
    {
        $service->save($supplier, $request->supplierData(), $request->zoneIds());

        return back()->with('success', 'Proveedor actualizado.');
    }

    public function destroy(Supplier $supplier, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('delete', $supplier);

        if ($supplier->fabricationOrders()->exists() || $supplier->installationOrders()->exists()) {
            return back()->with('error', 'El proveedor tiene órdenes; márcalo como inactivo en lugar de eliminarlo.');
        }

        $logger->log('proveedor.eliminado', $supplier, ['name' => $supplier->name]);
        $supplier->delete();

        return to_route('admin.proveedores.index')->with('success', 'Proveedor eliminado.');
    }

    // --------------------------------------------------------------- Contactos

    public function storeContact(Request $request, Supplier $supplier, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('update', $supplier);
        $data = $this->validateContact($request);

        if ($data['is_primary'] ?? false) {
            $supplier->contacts()->update(['is_primary' => false]);
        }
        $contact = $supplier->contacts()->create($data);
        $logger->log('proveedor.contacto_agregado', $supplier, ['contact' => $contact->id]);

        return back()->with('success', 'Contacto agregado.');
    }

    public function updateContact(Request $request, Supplier $supplier, SupplierContact $contact, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('update', $supplier);
        abort_unless($contact->supplier_id === $supplier->id, 404);
        $data = $this->validateContact($request);

        if ($data['is_primary'] ?? false) {
            $supplier->contacts()->whereKeyNot($contact->id)->update(['is_primary' => false]);
        }
        $contact->fill($data);
        $logger->logModelChanges('proveedor.contacto_actualizado', $contact);
        $contact->save();

        return back()->with('success', 'Contacto actualizado.');
    }

    public function destroyContact(Supplier $supplier, SupplierContact $contact, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('update', $supplier);
        abort_unless($contact->supplier_id === $supplier->id, 404);
        $logger->log('proveedor.contacto_eliminado', $supplier, ['contact' => $contact->only(['name', 'email'])]);
        $contact->delete();

        return back()->with('success', 'Contacto eliminado.');
    }

    // --------------------------------------------------------------- Evaluaciones y documentos

    public function storeEvaluation(Request $request, Supplier $supplier, SupplierService $service): RedirectResponse
    {
        Gate::authorize('update', $supplier);

        $data = $request->validate([
            'quality_score' => ['required', 'integer', 'between:1,5'],
            'timeliness_score' => ['required', 'integer', 'between:1,5'],
            'communication_score' => ['required', 'integer', 'between:1,5'],
            'comments' => ['nullable', 'string', 'max:2000'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ]);

        $service->evaluate($supplier, $data, $request->user());

        return back()->with('success', 'Evaluación registrada.');
    }

    public function attachDocuments(Request $request, Supplier $supplier, Attachments $attachments): RedirectResponse
    {
        Gate::authorize('update', $supplier);
        $data = $request->validate(['media_ids' => ['required', 'array', 'min:1', 'max:20'], 'media_ids.*' => ['integer']]);
        $attachments->attach($supplier, $data['media_ids'], 'document', $request->user());

        return back()->with('success', 'Documentos agregados.');
    }

    public function detachDocument(Supplier $supplier, int $media, Attachments $attachments): RedirectResponse
    {
        Gate::authorize('update', $supplier);
        $attachments->detach($supplier, $media, 'document');

        return back()->with('success', 'Documento quitado.');
    }

    /** @return array<string, mixed> */
    private function validateContact(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'position' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_primary' => ['boolean'],
        ]);
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'types' => SupplierType::options(),
            'statuses' => SupplierStatus::options(),
            'zones' => CoverageZone::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($z) => ['value' => $z->id, 'label' => $z->name])->all(),
        ];
    }
}
