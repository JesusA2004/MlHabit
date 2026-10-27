<?php

use App\Enums\LeadStatus;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Measurement;
use App\Models\Project;
use App\Models\ProjectEvent;
use App\Services\Projects\ProjectService;
use App\Services\Projects\ProjectWorkflow;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Projects\Support\Fixtures;

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
});

it('crea el proyecto desde un prospecto de forma idempotente', function () {
    $admin = Fixtures::staff();
    $lead = Lead::factory()->status(LeadStatus::VisitDone)->create(['assigned_to' => $admin->id]);

    $this->actingAs($admin)->post(route('admin.proyectos.desde-prospecto', $lead))->assertRedirect();
    $this->actingAs($admin)->post(route('admin.proyectos.desde-prospecto', $lead))->assertRedirect();

    expect(Project::query()->where('lead_id', $lead->id)->count())->toBe(1);

    $project = Project::query()->where('lead_id', $lead->id)->firstOrFail();
    expect($project->folio)->toStartWith('MLH-P-')
        ->and($project->customer_id)->toBe($lead->customer_id)
        ->and($project->advisor_id)->toBe($admin->id)
        ->and($project->status)->toBe(ProjectStatus::Design)
        ->and($lead->refresh()->status)->toBe(LeadStatus::Design);
});

it('el servicio devuelve el mismo proyecto si se llama dos veces', function () {
    $admin = Fixtures::staff();
    $lead = Lead::factory()->create();

    $a = app(ProjectService::class)->createFromLead($lead, $admin);
    $b = app(ProjectService::class)->createFromLead($lead, $admin);

    expect($a->id)->toBe($b->id);
});

it('vincula la visita del prospecto y sus mediciones al crear el proyecto', function () {
    $admin = Fixtures::staff();
    $lead = Lead::factory()->create();
    $visit = Fixtures::visit($lead->customer, ['lead_id' => $lead->id, 'status' => 'completed']);
    $measurement = Measurement::factory()->create(['project_id' => null, 'site_visit_id' => $visit->id, 'version' => 1]);

    $project = app(ProjectService::class)->createFromLead($lead, $admin);

    expect($visit->refresh()->project_id)->toBe($project->id)
        ->and($measurement->refresh()->project_id)->toBe($project->id)
        ->and($project->address_id)->toBe($visit->address_id);
});

it('crea proyecto desde una visita sin prospecto', function () {
    $admin = Fixtures::staff();
    $customer = Customer::factory()->create();
    $visit = Fixtures::visit($customer, ['status' => 'completed']);

    $this->actingAs($admin)->post(route('admin.proyectos.desde-visita', $visit))->assertRedirect();

    expect($visit->refresh()->project_id)->not->toBeNull();
});

it('crea proyecto manual desde un cliente', function () {
    $admin = Fixtures::staff();
    $customer = Customer::factory()->create();

    $this->actingAs($admin)->post(route('admin.proyectos.store'), [
        'customer_uuid' => $customer->uuid,
        'name' => 'Clóset recámara principal',
    ])->assertRedirect();

    expect(Project::query()->where('customer_id', $customer->id)->value('name'))->toBe('Clóset recámara principal');
});

it('rechaza una transición inválida del flujo', function () {
    $project = Fixtures::project(status: ProjectStatus::Design);

    expect(fn () => app(ProjectWorkflow::class)->transition($project, ProjectStatus::Fabrication))
        ->toThrow(ValidationException::class);

    expect($project->refresh()->status)->toBe(ProjectStatus::Design);
});

it('el equipo no puede forzar autorizado a mano', function () {
    $admin = Fixtures::staff();
    $project = Fixtures::project(status: ProjectStatus::PendingDeposit);

    $this->actingAs($admin)->post(route('admin.proyectos.estado', $project), ['status' => 'authorized'])->assertSessionHas('error');

    expect($project->refresh()->status)->toBe(ProjectStatus::PendingDeposit);
});

it('cambia de etapa y registra el evento visible al cliente', function () {
    $admin = Fixtures::staff();
    $project = Fixtures::project(status: ProjectStatus::Design);

    $this->actingAs($admin)->post(route('admin.proyectos.estado', $project), ['status' => 'quoting'])->assertSessionHas('success');

    expect($project->refresh()->status)->toBe(ProjectStatus::Quoting)
        ->and(ProjectEvent::query()->where('project_id', $project->id)->where('to_status', 'quoting')->value('visibility'))->toBe('client');
});

it('muestra el listado y el detalle al equipo y los niega a un cliente', function () {
    $admin = Fixtures::staff(UserRole::Sales);
    $project = Fixtures::project();
    [$client] = Fixtures::client();

    $this->actingAs($admin)->get(route('admin.proyectos.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.proyectos.show', $project))->assertOk();
    $this->actingAs($client)->get(route('admin.proyectos.show', $project))->assertForbidden();
});

it('agrega notas internas y actualizaciones para el cliente', function () {
    $admin = Fixtures::staff();
    $project = Fixtures::project();

    $this->actingAs($admin)->post(route('admin.proyectos.notas.store', $project), [
        'title' => 'Cliente pidió herrajes cierre lento', 'visibility' => 'internal',
    ])->assertSessionHas('success');
    $this->actingAs($admin)->post(route('admin.proyectos.notas.store', $project), [
        'title' => 'Ya elegimos tus acabados', 'visibility' => 'client',
    ])->assertSessionHas('success');

    expect(ProjectEvent::query()->where('project_id', $project->id)->where('type', 'note')->value('visibility'))->toBe('internal')
        ->and(ProjectEvent::query()->where('project_id', $project->id)->where('type', 'client_update')->value('visibility'))->toBe('client');
});
