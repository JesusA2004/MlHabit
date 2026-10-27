<?php

use App\Enums\ClientConformity;
use App\Enums\IncidentOrigin;
use App\Enums\IncidentStatus;
use App\Enums\InstallationItemStatus;
use App\Enums\InstallationStatus;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\InstallationOrder;
use App\Notifications\Operations\InstallationQualityResult;
use App\Services\Operations\InstallationService;
use Database\Factories\OperationsFixtures;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Operations\OpsSetup;

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
    $this->lead = OpsSetup::installer();
    $this->client = OperationsFixtures::client();
    $this->project = OperationsFixtures::project(ProjectStatus::Installing, $this->client);
    $this->order = OpsSetup::forceStatus(OpsSetup::order(project: $this->project, lead: $this->lead), InstallationStatus::ClientReview);
    $this->order->items()->update(['status' => InstallationItemStatus::Done->value]);
    $this->quality = OpsSetup::staff(UserRole::Quality);
});

function reviewAllItems(InstallationOrder $order, $reviewer, string $result = 'approved'): void
{
    foreach ($order->items as $item) {
        test()->actingAs($reviewer)->post(route('admin.calidad.partidas.revisar', [$order, $item]), [
            'result' => $result,
            'observations' => $result === 'approved' ? null : 'Puerta desalineada',
            'checklist' => OpsSetup::checklist(),
        ])->assertSessionHasNoErrors();
    }
}

it('shows the installation to its owner with the rights notice and hides prices', function () {
    $this->actingAs($this->client)->get(route('portal.instalaciones.show', $this->order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('portal/instalaciones/Show', false)
            ->where('canRespond', true)
            ->where('rightsNotice', 'Tu conformidad no limita tus garantías ni tus derechos como consumidor.')
            ->where('order.total', null)
            ->where('order.items.0.unitPrice', null));
});

it('only lets the owner client respond (IDOR)', function () {
    $stranger = OperationsFixtures::client();

    $this->actingAs($stranger)->get(route('portal.instalaciones.show', $this->order))->assertForbidden();
    $this->actingAs($stranger)->post(route('portal.instalaciones.conformidad', $this->order), ['conformity' => 'conforming'])->assertForbidden();
    $this->actingAs($this->lead)->post(route('portal.instalaciones.conformidad', $this->order), ['conformity' => 'conforming'])->assertForbidden();
    $this->actingAs(OpsSetup::staff())->post(route('portal.instalaciones.conformidad', $this->order), ['conformity' => 'conforming'])->assertForbidden();

    expect($this->order->refresh()->status)->toBe(InstallationStatus::ClientReview);
});

it('records "todo conforme" and sends the order and project to quality review', function () {
    $this->actingAs($this->client)->post(route('portal.instalaciones.conformidad', $this->order), ['conformity' => 'conforming'])
        ->assertSessionHasNoErrors();

    $this->order->refresh();
    expect($this->order->status)->toBe(InstallationStatus::QualityReview)
        ->and($this->order->client_conformity)->toBe(ClientConformity::Conforming)
        ->and($this->order->client_signed_by)->toBe($this->client->id)
        ->and($this->project->refresh()->status)->toBe(ProjectStatus::QualityReview);

    // Ya no puede volver a responder.
    $this->actingAs($this->client)->post(route('portal.instalaciones.conformidad', $this->order), ['conformity' => 'pending_items', 'comment' => 'x'])
        ->assertForbidden();
});

it('creates client incidents when there are pending items', function () {
    $item = $this->order->items()->first();
    $photo = OpsSetup::evidence($this->client);

    $this->actingAs($this->client)->post(route('portal.instalaciones.conformidad', $this->order), ['conformity' => 'pending_items'])
        ->assertSessionHasErrors('comment');

    $this->actingAs($this->client)->post(route('portal.instalaciones.conformidad', $this->order), [
        'conformity' => 'pending_items',
        'comment' => 'La puerta del alto roza.',
        'pending_item_ids' => [$item->id],
        'media_ids' => [$photo->id],
    ])->assertSessionHasNoErrors();

    $incident = Incident::query()->sole();
    expect($incident->origin)->toBe(IncidentOrigin::Client)
        ->and($incident->installation_order_id)->toBe($this->order->id)
        ->and($incident->visible_to_client)->toBeTrue()
        ->and($incident->folio)->toStartWith('MLH-X-')
        ->and($incident->attachments()->count())->toBe(1)
        ->and($this->order->refresh()->status)->toBe(InstallationStatus::QualityReview);
});

it('approves quality and completes the project when everything is approved and no incidents are open', function () {
    app(InstallationService::class)->clientConformity($this->order, $this->client, ClientConformity::Conforming, null, [], []);

    // Sin revisar partidas no se puede aprobar.
    $this->actingAs($this->quality)->post(route('admin.calidad.aprobar', $this->order))->assertSessionHasErrors('items');

    reviewAllItems($this->order, $this->quality);

    $this->actingAs($this->quality)->post(route('admin.calidad.aprobar', $this->order), ['client_contacted' => true])
        ->assertSessionHasNoErrors();

    expect($this->order->refresh()->status)->toBe(InstallationStatus::Approved)
        ->and($this->project->refresh()->status)->toBe(ProjectStatus::Completed)
        ->and($this->project->completed_at)->not->toBeNull();

    Notification::assertSentTo($this->lead, InstallationQualityResult::class);
});

it('keeps the project open while incidents are open, and closes it when they are resolved', function () {
    app(InstallationService::class)->clientConformity($this->order, $this->client, ClientConformity::Pending, 'Falta jaladera', [], []);
    $incident = Incident::query()->sole();

    reviewAllItems($this->order, $this->quality);
    $this->actingAs($this->quality)->post(route('admin.calidad.aprobar', $this->order))->assertSessionHasNoErrors();

    expect($this->order->refresh()->status)->toBe(InstallationStatus::Approved)
        ->and($this->project->refresh()->status)->toBe(ProjectStatus::QualityReview);

    $this->actingAs($this->quality)->post(route('admin.incidencias.estado', $incident), ['status' => 'resolved', 'resolution' => 'Se colocó la jaladera.'])
        ->assertSessionHasNoErrors();

    expect($incident->refresh()->status)->toBe(IncidentStatus::Resolved)
        ->and($this->project->refresh()->status)->toBe(ProjectStatus::Completed);
});

it('requires fixes: punch list, reopened items and quality incidents', function () {
    app(InstallationService::class)->clientConformity($this->order, $this->client, ClientConformity::Conforming, null, [], []);
    $item = $this->order->items()->first();

    $this->actingAs($this->quality)->post(route('admin.calidad.correcciones', $this->order), [
        'observations' => 'Ajustar bisagras',
        'issues' => [[
            'title' => 'Bisagra floja',
            'description' => 'La puerta derecha cae.',
            'category' => 'installation_error',
            'severity' => 'medium',
            'item_id' => $item->id,
        ]],
    ])->assertSessionHasNoErrors();

    expect($this->order->refresh()->status)->toBe(InstallationStatus::InProgress)
        ->and($item->refresh()->status)->toBe(InstallationItemStatus::Pending)
        ->and($this->project->refresh()->status)->toBe(ProjectStatus::PunchList);

    $incident = Incident::query()->sole();
    expect($incident->origin)->toBe(IncidentOrigin::Quality)
        ->and($incident->assigned_to)->toBe($this->lead->id);

    // El instalador corrige y reenvía: el cliente debe volver a revisar.
    $this->actingAs($this->lead)->post(route('tecnico.instalaciones.partidas.completar', [$this->order, $item]), [
        'media_ids' => [OpsSetup::evidence($this->lead)->id], 'checklist' => OpsSetup::checklist(),
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->lead)->post(route('tecnico.instalaciones.enviar', $this->order))->assertSessionHasNoErrors();

    expect($this->order->refresh()->status)->toBe(InstallationStatus::ClientReview)
        ->and($this->order->client_conformity)->toBeNull()
        ->and($this->order->client_signed_at)->toBeNull();
});

it('forbids quality review without quality.review', function () {
    app(InstallationService::class)->clientConformity($this->order, $this->client, ClientConformity::Conforming, null, [], []);
    $ops = OpsSetup::staff(UserRole::Operations); // quality.view sin quality.review

    $this->actingAs($ops)->get(route('admin.calidad.index'))->assertOk();
    $this->actingAs($ops)->post(route('admin.calidad.aprobar', $this->order))->assertForbidden();
});
