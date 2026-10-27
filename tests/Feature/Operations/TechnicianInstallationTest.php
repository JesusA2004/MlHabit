<?php

use App\Enums\InstallationItemStatus;
use App\Enums\InstallationStatus;
use App\Enums\ProjectStatus;
use App\Enums\QualityStage;
use App\Models\Media;
use App\Models\QualityInspection;
use App\Notifications\Operations\InstallationEvidenceReady;
use Database\Factories\OperationsFixtures;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Operations\OpsSetup;

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
    $this->lead = OpsSetup::installer();
    $this->client = OperationsFixtures::client();
    $project = OperationsFixtures::project(ProjectStatus::InstallationScheduled, $this->client);
    $this->order = OpsSetup::forceStatus(OpsSetup::order(project: $project, lead: $this->lead), InstallationStatus::Scheduled);
});

it('only lets the lead installer see and operate their own orders (IDOR)', function () {
    $other = OpsSetup::installer();

    $this->actingAs($other)->get(route('tecnico.instalaciones.show', $this->order))->assertForbidden();
    $this->actingAs($other)->post(route('tecnico.instalaciones.iniciar', $this->order))->assertForbidden();
    $item = $this->order->items()->first();
    $this->actingAs($other)->post(route('tecnico.instalaciones.partidas.completar', [$this->order, $item]), [
        'checklist' => OpsSetup::checklist(),
    ])->assertForbidden();
    $this->actingAs($other)->post(route('tecnico.instalaciones.enviar', $this->order))->assertForbidden();

    expect($this->order->refresh()->status)->toBe(InstallationStatus::Scheduled);

    // El listado solo trae sus órdenes.
    $this->actingAs($other)->get(route('tecnico.instalaciones.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('tecnico/instalaciones/Index', false)->where('active', []));
});

it('blocks staff and clients from the technician app', function () {
    $this->actingAs(OpsSetup::staff())->get(route('tecnico.instalaciones.index'))->assertForbidden();
    $this->actingAs($this->client)->get(route('tecnico.instalaciones.show', $this->order))->assertForbidden();
});

it('runs the evidence flow: start → items with evidence and checklist → client review', function () {
    $this->actingAs($this->lead)->post(route('tecnico.instalaciones.iniciar', $this->order))->assertSessionHasNoErrors();

    expect($this->order->refresh()->status)->toBe(InstallationStatus::InProgress)
        ->and($this->order->started_at)->not->toBeNull()
        ->and($this->order->project->status)->toBe(ProjectStatus::Installing);

    // No se puede enviar con partidas pendientes.
    $this->actingAs($this->lead)->post(route('tecnico.instalaciones.enviar', $this->order))->assertSessionHasErrors('items');

    foreach ($this->order->items as $item) {
        // Sin evidencia: error.
        $this->actingAs($this->lead)->post(route('tecnico.instalaciones.partidas.completar', [$this->order, $item]), [
            'checklist' => OpsSetup::checklist(),
        ])->assertSessionHasErrors('media_ids');

        // Checklist incompleto: error.
        $this->actingAs($this->lead)->post(route('tecnico.instalaciones.partidas.completar', [$this->order, $item]), [
            'media_ids' => [OpsSetup::evidence($this->lead)->id],
            'checklist' => array_slice(OpsSetup::checklist(), 0, 5),
        ])->assertSessionHasErrors('checklist');

        $this->actingAs($this->lead)->post(route('tecnico.instalaciones.partidas.completar', [$this->order, $item]), [
            'media_ids' => [OpsSetup::evidence($this->lead)->id, OpsSetup::evidence($this->lead, 'video', 40_000_000)->id],
            'checklist' => OpsSetup::checklist(),
            'notes' => 'Quedó nivelado.',
        ])->assertSessionHasNoErrors();

        expect($item->refresh()->status)->toBe(InstallationItemStatus::Done)
            ->and($item->attachments()->where('role', 'evidence')->count())->toBeGreaterThanOrEqual(2);
    }

    expect(QualityInspection::query()->where('stage', QualityStage::Installer->value)->count())->toBe(2);

    $this->actingAs($this->lead)->post(route('tecnico.instalaciones.enviar', $this->order))->assertSessionHasNoErrors();

    expect($this->order->refresh()->status)->toBe(InstallationStatus::ClientReview)
        ->and($this->order->evidence_submitted_at)->not->toBeNull();

    Notification::assertSentTo($this->client, InstallationEvidenceReady::class);
});

it('rejects evidence uploaded by someone else, public files and videos over 60 MB', function () {
    OpsSetup::forceStatus($this->order, InstallationStatus::InProgress, ProjectStatus::Installing);
    $item = $this->order->items()->first();

    $foreign = OpsSetup::evidence(OpsSetup::installer());
    $this->actingAs($this->lead)->post(route('tecnico.instalaciones.partidas.completar', [$this->order, $item]), [
        'media_ids' => [$foreign->id], 'checklist' => OpsSetup::checklist(),
    ])->assertSessionHasErrors('media_ids');

    $public = Media::factory()->create(['uploaded_by' => $this->lead->id]);
    $this->actingAs($this->lead)->post(route('tecnico.instalaciones.partidas.completar', [$this->order, $item]), [
        'media_ids' => [$public->id], 'checklist' => OpsSetup::checklist(),
    ])->assertSessionHasErrors('media_ids');

    $huge = OpsSetup::evidence($this->lead, 'video', 61 * 1024 * 1024);
    $this->actingAs($this->lead)->post(route('tecnico.instalaciones.partidas.completar', [$this->order, $item]), [
        'media_ids' => [$huge->id], 'checklist' => OpsSetup::checklist(),
    ])->assertSessionHasErrors('media_ids');

    expect($item->refresh()->status)->toBe(InstallationItemStatus::Pending);
});

it('requires a note for each failing checklist criterion', function () {
    OpsSetup::forceStatus($this->order, InstallationStatus::InProgress, ProjectStatus::Installing);
    $item = $this->order->items()->first();
    $checklist = OpsSetup::checklist();
    $checklist[0] = ['criterion' => $checklist[0]['criterion'], 'result' => 'fail', 'notes' => null];

    $this->actingAs($this->lead)->post(route('tecnico.instalaciones.partidas.completar', [$this->order, $item]), [
        'media_ids' => [OpsSetup::evidence($this->lead)->id], 'checklist' => $checklist,
    ])->assertSessionHasErrors('checklist');
});

it('cannot register progress before starting', function () {
    $item = $this->order->items()->first();

    $this->actingAs($this->lead)->post(route('tecnico.instalaciones.partidas.completar', [$this->order, $item]), [
        'media_ids' => [OpsSetup::evidence($this->lead)->id], 'checklist' => OpsSetup::checklist(),
    ])->assertSessionHasErrors('status');
});
