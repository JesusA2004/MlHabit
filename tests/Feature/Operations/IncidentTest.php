<?php

use App\Enums\IncidentOrigin;
use App\Enums\IncidentStatus;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\Supplier;
use App\Notifications\Operations\IncidentNotification;
use Database\Factories\OperationsFixtures;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Operations\OpsSetup;

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
    $this->ops = OpsSetup::staff(UserRole::Operations);
    $this->client = OperationsFixtures::client();
    $this->project = OperationsFixtures::project(ProjectStatus::Installing, $this->client);
});

it('creates, updates and resolves an incident with notifications', function () {
    $assignee = OpsSetup::staff(UserRole::Quality);
    $supplier = Supplier::factory()->create();
    $photo = OpsSetup::evidence($this->ops);

    $this->actingAs($this->ops)->post(route('admin.incidencias.store'), [
        'project_id' => $this->project->id,
        'category' => 'damage',
        'severity' => 'high',
        'title' => 'Cubierta rayada',
        'description' => 'Se rayó al subirla.',
        'responsible_party' => 'supplier',
        'responsible_supplier_id' => $supplier->id,
        'assigned_to' => $assignee->id,
        'cost' => '1,500.00',
        'due_on' => now()->addWeek()->toDateString(),
        'visible_to_client' => true,
        'media_ids' => [$photo->id],
    ])->assertRedirect();

    $incident = Incident::query()->sole();
    expect($incident->folio)->toStartWith('MLH-X-')
        ->and($incident->origin)->toBe(IncidentOrigin::Internal)
        ->and($incident->cost)->toBe('1500.00')
        ->and($incident->attachments()->count())->toBe(1);

    Notification::assertSentTo($assignee, IncidentNotification::class);
    Notification::assertSentTo($this->client, IncidentNotification::class);

    $this->actingAs($this->ops)->put(route('admin.incidencias.update', $incident), [
        'category' => 'damage',
        'severity' => 'critical',
        'title' => 'Cubierta rayada',
        'description' => 'Se rayó al subirla.',
        'action_plan' => 'Reponer cubierta',
        'visible_to_client' => false,
    ])->assertSessionHasNoErrors();
    expect($incident->refresh()->severity->value)->toBe('critical')->and($incident->visible_to_client)->toBeFalse();

    $this->actingAs($this->ops)->post(route('admin.incidencias.estado', $incident), ['status' => 'resolved'])
        ->assertSessionHasErrors('resolution');

    $this->actingAs($this->ops)->post(route('admin.incidencias.estado', $incident), ['status' => 'in_progress'])->assertSessionHasNoErrors();
    $this->actingAs($this->ops)->post(route('admin.incidencias.estado', $incident), ['status' => 'resolved', 'resolution' => 'Se cambió la cubierta.'])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->ops)->post(route('admin.incidencias.estado', $incident), ['status' => 'closed'])->assertSessionHasNoErrors();

    expect($incident->refresh()->status)->toBe(IncidentStatus::Closed)
        ->and($incident->resolved_at)->not->toBeNull();

    $this->actingAs($this->ops)->post(route('admin.incidencias.estado', $incident), ['status' => 'open'])->assertSessionHasErrors('status');
});

it('lists and shows incidents to staff with incidents.view only', function () {
    $incident = Incident::factory()->create(['project_id' => $this->project->id]);

    $this->actingAs($this->ops)->get(route('admin.incidencias.index'))->assertOk();
    $this->actingAs($this->ops)->get(route('admin.incidencias.show', $incident))->assertOk();

    $sales = OpsSetup::staff(UserRole::Sales);
    $this->actingAs($sales)->get(route('admin.incidencias.index'))->assertForbidden();
    $this->actingAs($sales)->get(route('admin.incidencias.show', $incident))->assertForbidden();

    $design = OpsSetup::staff(UserRole::Design);
    $this->actingAs($design)->post(route('admin.incidencias.store'), [
        'project_id' => $this->project->id, 'category' => 'damage', 'severity' => 'low', 'title' => 'x', 'description' => 'y',
    ])->assertForbidden();
    $this->actingAs($design)->post(route('admin.incidencias.estado', $incident), ['status' => 'in_progress'])->assertForbidden();
});

it('lets a client report an incident only for their own project', function () {
    $this->actingAs($this->client)->get(route('portal.incidencias.create'))->assertOk();

    $foreign = OperationsFixtures::project(ProjectStatus::Completed);
    $this->actingAs($this->client)->post(route('portal.incidencias.store'), [
        'project_id' => $foreign->id, 'category' => 'damage', 'title' => 'Rayón', 'description' => 'Hay un rayón.',
    ])->assertSessionHasErrors('project_id');

    $this->actingAs($this->client)->post(route('portal.incidencias.store'), [
        'project_id' => $this->project->id, 'category' => 'site_condition', 'title' => 'x', 'description' => 'y',
    ])->assertSessionHasErrors('category');

    $this->actingAs($this->client)->post(route('portal.incidencias.store'), [
        'project_id' => $this->project->id,
        'category' => 'damage',
        'title' => 'Rayón en puerta',
        'description' => 'La puerta del alto tiene un rayón.',
        'media_ids' => [OpsSetup::evidence($this->client)->id],
    ])->assertRedirect('/portal');

    $incident = Incident::query()->sole();
    expect($incident->origin)->toBe(IncidentOrigin::Client)
        ->and($incident->project_id)->toBe($this->project->id)
        ->and($incident->reported_by)->toBe($this->client->id)
        ->and($incident->visible_to_client)->toBeTrue();

    // Staff y técnicos no usan el formulario del portal.
    $this->actingAs($this->ops)->get(route('portal.incidencias.create'))->assertForbidden();
});

it('stores notifications in the platform notification-center shape', function () {
    $incident = Incident::factory()->create(['project_id' => $this->project->id]);
    $data = (new IncidentNotification($incident, 'created'))->toArray($this->ops);

    expect($data)->toHaveKeys(['title', 'body', 'url', 'tone', 'icon'])
        ->and($data['url'])->toBe('/admin/incidencias/'.$incident->id)
        ->and((new IncidentNotification($incident, 'created'))->toArray($this->client)['url'])->toBe('/portal');
});
