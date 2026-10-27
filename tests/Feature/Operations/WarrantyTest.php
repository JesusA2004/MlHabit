<?php

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Enums\WarrantyClaimStatus;
use App\Enums\WarrantyStatus;
use App\Models\Warranty;
use App\Models\WarrantyClaim;
use App\Notifications\Operations\WarrantyRegistered;
use Database\Factories\OperationsFixtures;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Operations\OpsSetup;

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
    $this->quality = OpsSetup::staff(UserRole::Quality);
    $this->client = OperationsFixtures::client();
    $this->project = OperationsFixtures::project(ProjectStatus::Completed, $this->client);
});

it('registers separate warranties per type with captured terms and notifies the client', function () {
    $this->actingAs($this->quality)->post(route('admin.garantias.store'), [
        'project_id' => $this->project->id,
        'warranties' => [
            ['type' => 'product', 'starts_on' => now()->toDateString(), 'ends_on' => now()->addYears(2)->toDateString(), 'scope' => 'Herrajes y estructura.'],
            ['type' => 'installation', 'starts_on' => now()->toDateString(), 'ends_on' => now()->addYear()->toDateString(), 'scope' => 'Nivelación y anclajes.', 'exclusions' => 'Mal uso.'],
        ],
    ])->assertRedirect();

    expect(Warranty::query()->count())->toBe(2)
        ->and(Warranty::query()->pluck('folio')->every(fn ($f) => str_starts_with($f, 'MLH-W-')))->toBeTrue();

    Notification::assertSentTo($this->client, WarrantyRegistered::class);

    // Mismo tipo dos veces: rechazado.
    $this->actingAs($this->quality)->post(route('admin.garantias.store'), [
        'project_id' => $this->project->id,
        'warranties' => [
            ['type' => 'product', 'starts_on' => now()->toDateString(), 'ends_on' => now()->addYear()->toDateString(), 'scope' => 'a'],
            ['type' => 'product', 'starts_on' => now()->toDateString(), 'ends_on' => now()->addYear()->toDateString(), 'scope' => 'b'],
        ],
    ])->assertSessionHasErrors('warranties.1.type');
});

it('only registers warranties for completed projects', function () {
    $project = OperationsFixtures::project(ProjectStatus::Installing);

    $this->actingAs($this->quality)->post(route('admin.garantias.store'), [
        'project_id' => $project->id,
        'warranties' => [['type' => 'product', 'starts_on' => now()->toDateString(), 'ends_on' => now()->addYear()->toDateString(), 'scope' => 'x']],
    ])->assertSessionHasErrors('project_id');
});

it('toggles the project to warranty service while a claim is open and back when resolved', function () {
    $warranty = Warranty::factory()->create(['project_id' => $this->project->id]);

    $this->actingAs($this->quality)->post(route('admin.garantias.reclamos.store', $warranty), [
        'description' => 'Se despegó el canto.',
    ])->assertSessionHasNoErrors();

    $claim = WarrantyClaim::query()->sole();
    expect($claim->folio)->toStartWith('MLH-R-')
        ->and($this->project->refresh()->status)->toBe(ProjectStatus::WarrantyService);

    $this->actingAs($this->quality)->put(route('admin.garantias.reclamos.update', [$warranty, $claim]), ['status' => 'resolved'])
        ->assertSessionHasErrors('resolution');

    $this->actingAs($this->quality)->put(route('admin.garantias.reclamos.update', [$warranty, $claim]), [
        'status' => 'resolved', 'resolution' => 'Se volvió a pegar el canto.',
    ])->assertSessionHasNoErrors();

    expect($claim->refresh()->status)->toBe(WarrantyClaimStatus::Resolved)
        ->and($this->project->refresh()->status)->toBe(ProjectStatus::Completed);
});

it('does not open claims on expired or voided warranties', function () {
    $expired = Warranty::factory()->create([
        'project_id' => $this->project->id,
        'starts_on' => now()->subYears(2)->toDateString(),
        'ends_on' => now()->subDay()->toDateString(),
    ]);

    $this->actingAs($this->quality)->post(route('admin.garantias.reclamos.store', $expired), ['description' => 'x'])
        ->assertSessionHasErrors('warranty_id');

    expect($this->project->refresh()->status)->toBe(ProjectStatus::Completed);
});

it('expires warranties with the scheduled command', function () {
    $due = Warranty::factory()->create(['starts_on' => now()->subYear()->toDateString(), 'ends_on' => now()->subDay()->toDateString()]);
    $current = Warranty::factory()->create(['ends_on' => now()->toDateString()]);

    $this->artisan('warranties:expire')->assertSuccessful();

    expect($due->refresh()->status)->toBe(WarrantyStatus::Expired)
        ->and($current->refresh()->status)->toBe(WarrantyStatus::Active);
});

it('restricts warranty management to warranties.manage', function () {
    $ops = OpsSetup::staff(UserRole::Operations); // warranties.view sin manage
    $warranty = Warranty::factory()->create(['project_id' => $this->project->id]);

    $this->actingAs($ops)->get(route('admin.garantias.index'))->assertOk();
    $this->actingAs($ops)->post(route('admin.garantias.reclamos.store', $warranty), ['description' => 'x'])->assertForbidden();
    $this->actingAs($ops)->post(route('admin.garantias.anular', $warranty), ['reason' => 'x'])->assertForbidden();
    $this->actingAs(OpsSetup::staff(UserRole::Sales))->get(route('admin.garantias.index'))->assertForbidden();
});
