<?php

use App\Enums\FabricationStatus;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\FabricationOrder;
use App\Models\Supplier;
use Database\Factories\OperationsFixtures;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Operations\OpsSetup;

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
    $this->ops = OpsSetup::staff(UserRole::Operations);
    $this->supplier = Supplier::factory()->fabricator()->create();
});

it('blocks a fabrication order when the design has no technical approval', function () {
    $project = OperationsFixtures::project(ProjectStatus::Authorized);
    $design = OperationsFixtures::designVersion($project, technicallyApproved: false);

    $this->actingAs($this->ops)
        ->post(route('admin.fabricacion.store'), [
            'project_id' => $project->id,
            'design_version_id' => $design,
            'supplier_id' => $this->supplier->id,
        ])
        ->assertSessionHasErrors('design_version_id');

    expect(FabricationOrder::query()->count())->toBe(0);
});

it('rejects a design version from another project', function () {
    $project = OperationsFixtures::project(ProjectStatus::Authorized);
    $other = OperationsFixtures::project(ProjectStatus::Authorized);
    $foreignDesign = OperationsFixtures::designVersion($other);

    $this->actingAs($this->ops)
        ->post(route('admin.fabricacion.store'), [
            'project_id' => $project->id,
            'design_version_id' => $foreignDesign,
            'supplier_id' => $this->supplier->id,
        ])
        ->assertSessionHasErrors('design_version_id');
});

it('blocks fabrication when the project is not authorized yet', function () {
    $project = OperationsFixtures::project(ProjectStatus::PendingDeposit);
    $design = OperationsFixtures::designVersion($project);

    $this->actingAs($this->ops)
        ->post(route('admin.fabricacion.store'), [
            'project_id' => $project->id,
            'design_version_id' => $design,
            'supplier_id' => $this->supplier->id,
        ])
        ->assertSessionHasErrors('project_id');
});

it('rejects installer-only suppliers for fabrication', function () {
    $project = OperationsFixtures::project(ProjectStatus::Authorized);
    $design = OperationsFixtures::designVersion($project);
    $installer = Supplier::factory()->installer()->create();

    $this->actingAs($this->ops)
        ->post(route('admin.fabricacion.store'), [
            'project_id' => $project->id,
            'design_version_id' => $design,
            'supplier_id' => $installer->id,
        ])
        ->assertSessionHasErrors('supplier_id');
});

it('creates an order with folio F and moves the project to fabrication when issued', function () {
    $project = OperationsFixtures::project(ProjectStatus::Authorized);
    $design = OperationsFixtures::designVersion($project);

    $this->actingAs($this->ops)
        ->post(route('admin.fabricacion.store'), [
            'project_id' => $project->id,
            'design_version_id' => $design,
            'supplier_id' => $this->supplier->id,
            'cost' => '15,250.50',
            'due_on' => now()->addDays(20)->toDateString(),
        ])
        ->assertSessionHasErrors('cost'); // formato con comas no se acepta en el backend

    $this->actingAs($this->ops)
        ->post(route('admin.fabricacion.store'), [
            'project_id' => $project->id,
            'design_version_id' => $design,
            'supplier_id' => $this->supplier->id,
            'cost' => '15250.50',
            'due_on' => now()->addDays(20)->toDateString(),
        ])
        ->assertRedirect();

    $order = FabricationOrder::query()->sole();
    expect($order->folio)->toStartWith('MLH-F-')
        ->and($order->status)->toBe(FabricationStatus::Draft)
        ->and($order->cost)->toBe('15250.50');

    $this->actingAs($this->ops)
        ->post(route('admin.fabricacion.estado', $order), ['status' => 'issued'])
        ->assertSessionHasNoErrors();

    expect($order->refresh()->status)->toBe(FabricationStatus::Issued)
        ->and($order->issued_at)->not->toBeNull()
        ->and($project->refresh()->status)->toBe(ProjectStatus::Fabrication);
});

it('moves the project to ready to install only when every order is finished', function () {
    $project = OperationsFixtures::project(ProjectStatus::Fabrication);
    $a = FabricationOrder::factory()->create(['project_id' => $project->id, 'status' => FabricationStatus::InProduction]);
    $b = FabricationOrder::factory()->create(['project_id' => $project->id, 'status' => FabricationStatus::InProduction]);

    $this->actingAs($this->ops)->post(route('admin.fabricacion.estado', $a), ['status' => 'ready'])->assertSessionHasNoErrors();
    expect($project->refresh()->status)->toBe(ProjectStatus::Fabrication);

    $this->actingAs($this->ops)->post(route('admin.fabricacion.estado', $b), ['status' => 'ready'])->assertSessionHasNoErrors();
    expect($project->refresh()->status)->toBe(ProjectStatus::ReadyToInstall);

    $this->actingAs($this->ops)->post(route('admin.fabricacion.estado', $a), ['status' => 'delivered'])->assertSessionHasNoErrors();
    expect($a->refresh()->delivered_at)->not->toBeNull();
});

it('rejects invalid fabrication transitions', function () {
    $order = FabricationOrder::factory()->create(['status' => FabricationStatus::Draft]);

    $this->actingAs($this->ops)
        ->post(route('admin.fabricacion.estado', $order), ['status' => 'delivered'])
        ->assertSessionHasErrors('status');

    expect($order->refresh()->status)->toBe(FabricationStatus::Draft);
});

it('forbids fabrication management without permission', function () {
    $design = OpsSetup::staff(UserRole::Design);
    $project = OperationsFixtures::project(ProjectStatus::Authorized);

    $this->actingAs($design)
        ->post(route('admin.fabricacion.store'), [
            'project_id' => $project->id,
            'design_version_id' => OperationsFixtures::designVersion($project),
            'supplier_id' => $this->supplier->id,
        ])
        ->assertForbidden();
});
