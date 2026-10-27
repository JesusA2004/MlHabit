<?php

namespace App\Services\Projects;

use App\Enums\LeadStatus;
use App\Enums\ProjectStatus;
use App\Models\Address;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Measurement;
use App\Models\Project;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Crm\LeadWorkflow;
use App\Support\Folio;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nacimiento del proyecto (desde prospecto, visita o cliente) y datos
 * de gestión (responsables, fechas). El estado lo cambia ProjectWorkflow.
 */
class ProjectService
{
    public function __construct(
        private readonly ProjectWorkflow $workflow,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * Crea el proyecto de un prospecto. Idempotente: projects.lead_id es
     * UNIQUE, así que si ya existe (o se crea en paralelo) devuelve el
     * existente. Mueve el prospecto a "En diseño" (transición de sistema).
     */
    public function createFromLead(Lead $lead, User $actor, ?SiteVisit $visit = null): Project
    {
        $existing = Project::query()->where('lead_id', $lead->id)->first();
        if ($existing) {
            return $existing;
        }

        try {
            $project = DB::transaction(function () use ($lead, $actor, $visit) {
                /** @var Lead $locked */
                $locked = Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();

                $existing = Project::query()->where('lead_id', $locked->id)->first();
                if ($existing) {
                    return $existing;
                }

                $visit ??= SiteVisit::query()
                    ->where('lead_id', $locked->id)
                    ->whereNull('project_id')
                    ->latest('starts_at')
                    ->first();

                $categoryId = $locked->categoryIds()[0] ?? null;
                $addressId = $visit->address_id ?? $this->latestAddressId($locked->customer_id);

                $project = $this->newProject([
                    'customer_id' => $locked->customer_id,
                    'address_id' => $addressId,
                    'lead_id' => $locked->id,
                    'product_category_id' => $categoryId,
                    'name' => $this->defaultName($locked->customer_id, $categoryId),
                    'advisor_id' => $locked->assigned_to ?? ($actor->isStaff() ? $actor->id : null),
                ], $actor, "Creado desde el prospecto {$locked->folio}");

                $this->linkVisitsOf($project, $locked->id, $visit);

                return $project;
            });
        } catch (QueryException $e) {
            // Carrera: otra petición creó el proyecto (lead_id UNIQUE).
            $existing = Project::query()->where('lead_id', $lead->id)->first();
            if ($existing) {
                return $existing;
            }
            throw $e;
        }

        if ($project->wasRecentlyCreated) {
            app(LeadWorkflow::class)->transition($lead, LeadStatus::Design, $actor, "Proyecto {$project->folio} creado", force: true);
        }

        return $project;
    }

    /** Proyecto desde una visita realizada que aún no tiene proyecto. */
    public function createFromVisit(SiteVisit $visit, User $actor): Project
    {
        if ($visit->project_id) {
            return Project::query()->findOrFail($visit->project_id);
        }

        if ($visit->lead_id) {
            /** @var Lead $lead */
            $lead = Lead::query()->findOrFail($visit->lead_id);

            return $this->createFromLead($lead, $actor, $visit);
        }

        return DB::transaction(function () use ($visit, $actor) {
            /** @var SiteVisit $locked */
            $locked = SiteVisit::query()->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            if ($locked->project_id) {
                return Project::query()->findOrFail($locked->project_id);
            }

            $project = $this->newProject([
                'customer_id' => $locked->customer_id,
                'address_id' => $locked->address_id,
                'name' => $this->defaultName($locked->customer_id, null),
                'advisor_id' => $actor->isStaff() ? $actor->id : null,
            ], $actor, "Creado desde la visita {$locked->folio}");

            $this->linkVisit($project, $locked);

            return $project;
        });
    }

    /**
     * Proyecto manual para un cliente existente (p. ej. recompra).
     *
     * @param  array<string, mixed>  $data  name, address_id, product_category_id, advisor_id, designer_id
     */
    public function createForCustomer(Customer $customer, array $data, User $actor): Project
    {
        $addressId = $data['address_id'] ?? null;
        if ($addressId !== null && ! Address::query()->whereKey($addressId)->where('customer_id', $customer->id)->exists()) {
            throw ValidationException::withMessages(['address_id' => 'El domicilio no pertenece a este cliente.']);
        }

        return DB::transaction(fn () => $this->newProject([
            'customer_id' => $customer->id,
            'address_id' => $addressId ?? $this->latestAddressId($customer->id),
            'product_category_id' => $data['product_category_id'] ?? null,
            'name' => $data['name'],
            'advisor_id' => $data['advisor_id'] ?? ($actor->isStaff() ? $actor->id : null),
            'designer_id' => $data['designer_id'] ?? null,
        ], $actor, 'Creado manualmente'));
    }

    /**
     * Responsables, nombre y fecha objetivo.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Project $project, array $data, User $actor): Project
    {
        $project->fill(array_intersect_key($data, array_flip([
            'name', 'address_id', 'product_category_id', 'advisor_id', 'designer_id', 'operations_owner_id', 'target_install_date',
        ])));

        if ($project->isDirty('address_id') && $project->address_id !== null
            && ! Address::query()->whereKey($project->address_id)->where('customer_id', $project->customer_id)->exists()) {
            throw ValidationException::withMessages(['address_id' => 'El domicilio no pertenece a este cliente.']);
        }

        $this->logger->logModelChanges('proyecto.actualizado', $project);
        $dirty = array_keys($project->getDirty());
        $project->save();

        $owners = array_intersect($dirty, ['advisor_id', 'designer_id', 'operations_owner_id']);
        if ($owners !== []) {
            $this->workflow->record($project, 'owners_changed', 'Responsables actualizados', null, $actor);
        }
        if (in_array('target_install_date', $dirty, true)) {
            $this->workflow->record($project, 'date_changed', 'Fecha objetivo de instalación actualizada', $project->target_install_date?->format('d/m/Y'), $actor);
        }

        return $project;
    }

    /** Vincula una visita (y sus mediciones) al proyecto, renumerando versiones. */
    public function linkVisit(Project $project, SiteVisit $visit): void
    {
        SiteVisit::query()->whereKey($visit->id)->whereNull('project_id')->update(['project_id' => $project->id]);

        $orphans = Measurement::query()
            ->where('site_visit_id', $visit->id)
            ->whereNull('project_id')
            ->orderBy('version')
            ->orderBy('id')
            ->get();

        $next = (int) Measurement::query()->where('project_id', $project->id)->max('version');
        foreach ($orphans as $measurement) {
            $measurement->update(['project_id' => $project->id, 'version' => ++$next]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function newProject(array $attributes, User $actor, string $origin): Project
    {
        /** @var Project $project */
        $project = Project::query()->create([
            ...$attributes,
            'folio' => Folio::next('P'),
            'status' => ProjectStatus::Preparation,
        ]);

        $this->workflow->record($project, 'created', 'Proyecto creado', $origin, $actor);
        $this->workflow->record($project, 'welcome', 'Comenzamos tu proyecto', 'Te avisaremos aquí de cada avance.', $actor, 'client');
        $this->logger->log('proyecto.creado', $project, ['folio' => $project->folio, 'origin' => $origin]);

        // El proyecto nace al pasar a diseño: si ya hubo visita de medición, va directo a diseño.
        $this->workflow->transition($project, ProjectStatus::Design, $actor, null, false);

        return $project->refresh();
    }

    private function linkVisitsOf(Project $project, int $leadId, ?SiteVisit $preferred): void
    {
        $visits = SiteVisit::query()->where('lead_id', $leadId)->whereNull('project_id')->orderBy('starts_at')->get();
        if ($preferred && ! $visits->contains('id', $preferred->id)) {
            $visits->push($preferred);
        }
        foreach ($visits as $visit) {
            $this->linkVisit($project, $visit);
        }
    }

    private function latestAddressId(int $customerId): ?int
    {
        $id = Address::query()->where('customer_id', $customerId)->latest('id')->value('id');

        return $id === null ? null : (int) $id;
    }

    private function defaultName(int $customerId, ?int $categoryId): string
    {
        $customer = Customer::query()->find($customerId);
        $category = $categoryId ? DB::table('product_categories')->where('id', $categoryId)->value('name') : null;
        $base = $category ? (string) $category : 'Proyecto';

        return mb_substr(trim($base.' · '.($customer->full_name ?? '')), 0, 160);
    }
}
