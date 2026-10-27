<?php

use App\Enums\UserRole;
use App\Models\CoverageZone;
use App\Models\Media;
use App\Models\Supplier;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Notifications\Platform\UserInvitation;
use App\Services\Operations\SupplierService;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Operations\OpsSetup;

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
    $this->ops = OpsSetup::staff(UserRole::Operations);
});

it('creates a supplier with zones, contacts and documents', function () {
    $zone = CoverageZone::factory()->create();

    $this->actingAs($this->ops)->post(route('admin.proveedores.store'), [
        'name' => 'Carpintería Norte',
        'rfc' => 'cno010101ab1',
        'type' => 'fabricator',
        'status' => 'active',
        'lead_time_days' => 21,
        'zone_ids' => [$zone->id],
    ])->assertRedirect();

    $supplier = Supplier::query()->sole();
    expect($supplier->rfc)->toBe('CNO010101AB1')
        ->and($supplier->coverageZones()->pluck('coverage_zones.id')->all())->toBe([$zone->id]);

    $this->actingAs($this->ops)->post(route('admin.proveedores.contactos.store', $supplier), [
        'name' => 'Ana Ruiz', 'email' => 'ana@example.com', 'is_primary' => true,
    ])->assertSessionHasNoErrors();
    expect($supplier->contacts()->sole()->is_primary)->toBeTrue();

    $doc = Media::factory()->private()->create(['uploaded_by' => $this->ops->id, 'type' => 'document', 'mime_type' => 'application/pdf']);
    $this->actingAs($this->ops)->post(route('admin.proveedores.documentos.store', $supplier), ['media_ids' => [$doc->id]])->assertSessionHasNoErrors();
    expect($supplier->attachments()->where('role', 'document')->count())->toBe(1);

    $this->actingAs($this->ops)->get(route('admin.proveedores.show', $supplier))->assertOk();
});

it('recalculates the rating as an exact average of all scores', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs($this->ops)->post(route('admin.proveedores.evaluaciones.store', $supplier), [
        'quality_score' => 5, 'timeliness_score' => 4, 'communication_score' => 4,
    ])->assertSessionHasNoErrors();
    // 13 / 3 = 4.3333… → 4.33
    expect($supplier->refresh()->rating)->toBe('4.33');

    $this->actingAs($this->ops)->post(route('admin.proveedores.evaluaciones.store', $supplier), [
        'quality_score' => 3, 'timeliness_score' => 3, 'communication_score' => 4,
    ])->assertSessionHasNoErrors();
    // (13 + 10) / 6 = 3.8333… → 3.83
    expect($supplier->refresh()->rating)->toBe('3.83')
        ->and(SupplierService::average('25', 6))->toBe('4.17');

    $this->actingAs($this->ops)->post(route('admin.proveedores.evaluaciones.store', $supplier), [
        'quality_score' => 6, 'timeliness_score' => 3, 'communication_score' => 4,
    ])->assertSessionHasErrors('quality_score');
});

it('invites a technician by email with a password link and assigns zones', function () {
    $zone = CoverageZone::factory()->create();
    $supplier = Supplier::factory()->installer()->create();

    $this->actingAs($this->ops)->post(route('admin.tecnicos.store'), [
        'name' => 'Luis Pérez',
        'email' => 'luis@example.com',
        'supplier_id' => $supplier->id,
        'status' => 'active',
        'does_visits' => false,
        'does_installations' => true,
        'zone_ids' => [$zone->id],
    ])->assertRedirect();

    $user = User::query()->where('email', 'luis@example.com')->sole();
    expect($user->role)->toBe(UserRole::Technician)
        ->and($user->technicianProfile->does_installations)->toBeTrue()
        ->and($user->coverageZones()->pluck('coverage_zones.id')->all())->toBe([$zone->id]);

    Notification::assertSentTo($user, UserInvitation::class);

    // Correo duplicado.
    $this->actingAs($this->ops)->post(route('admin.tecnicos.store'), [
        'name' => 'Otro', 'email' => 'luis@example.com', 'status' => 'active', 'does_visits' => true, 'does_installations' => false,
    ])->assertSessionHasErrors('email');

    $profile = TechnicianProfile::query()->sole();
    $this->actingAs($this->ops)->put(route('admin.tecnicos.update', $profile), [
        'name' => 'Luis Pérez G.', 'status' => 'unavailable', 'does_visits' => true, 'does_installations' => true, 'is_active' => false, 'zone_ids' => [],
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->is_active)->toBeFalse()->and($user->name)->toBe('Luis Pérez G.');
    $this->actingAs($this->ops)->get(route('admin.tecnicos.show', $profile))->assertOk();
});

it('restricts suppliers and technicians management', function () {
    $quality = OpsSetup::staff(UserRole::Quality); // suppliers.view sin manage

    $this->actingAs($quality)->get(route('admin.proveedores.index'))->assertOk();
    $this->actingAs($quality)->get(route('admin.tecnicos.index'))->assertOk();
    $this->actingAs($quality)->post(route('admin.proveedores.store'), ['name' => 'X', 'type' => 'both', 'status' => 'active'])->assertForbidden();
    $this->actingAs($quality)->post(route('admin.tecnicos.store'), [
        'name' => 'X', 'email' => 'x@example.com', 'status' => 'active', 'does_visits' => true, 'does_installations' => true,
    ])->assertForbidden();

    $this->actingAs(OpsSetup::staff(UserRole::Sales))->get(route('admin.proveedores.index'))->assertForbidden();
});
