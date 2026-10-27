<?php

use App\Enums\UserRole;
use App\Models\CoveragePostalCode;
use App\Models\CoverageZone;
use App\Models\User;
use App\Services\Crm\CoverageZoneService;
use Tests\Feature\Crm\CrmSetup;

beforeEach(fn () => $this->withoutVite());

it('reports operational coverage for a postal code', function () {
    CrmSetup::zone('06700');

    $this->getJson('/cobertura/verificar?cp=06700')
        ->assertOk()
        ->assertJson(['covered' => true, 'visitsEnabled' => true])
        ->assertJsonPath('zone.state', 'Ciudad de México');

    $this->getJson('/cobertura/verificar?cp=99999')
        ->assertOk()
        ->assertJson(['covered' => false, 'visitsEnabled' => false, 'zone' => null]);
});

it('does not accept visits in a zone without technicians', function () {
    CrmSetup::zone('06700', withTechnician: false);

    $this->getJson('/cobertura/verificar?cp=06700')->assertJson(['covered' => true, 'visitsEnabled' => false]);
});

it('treats inactive zones as not covered', function () {
    $zone = CrmSetup::zone('06700');
    $zone->update(['is_active' => false]);

    $this->getJson('/cobertura/verificar?cp=06700')->assertJson(['covered' => false]);
});

it('rejects malformed postal codes', function () {
    $this->getJson('/cobertura/verificar?cp=12')->assertStatus(422)->assertJson(['valid' => false]);
});

it('imports pasted postal codes and reports conflicts with other zones', function () {
    $admin = User::factory()->role(UserRole::Operations)->create()->refresh();
    $other = CrmSetup::zone('06700', withTechnician: false);
    $zone = CoverageZone::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.cobertura.codigos.store', $zone), ['postal_codes' => "06600, 06700\n6800 abc 123456"])
        ->assertRedirect();

    expect(CoveragePostalCode::query()->where('coverage_zone_id', $zone->id)->pluck('postal_code')->sort()->values()->all())->toBe(['06600', '06800'])
        ->and(CoveragePostalCode::query()->where('postal_code', '06700')->value('coverage_zone_id'))->toBe($other->id);

    expect(CoverageZoneService::parse("06600, 06700\n6800 abc 123456"))->toBe([
        'valid' => ['06600', '06700', '06800'],
        'invalid' => ['abc', '123456'],
    ]);
});

it('only assigns technicians to a zone', function () {
    $admin = User::factory()->role(UserRole::Operations)->create()->refresh();
    $zone = CoverageZone::factory()->create();
    $tech = User::factory()->role(UserRole::Technician)->create()->refresh();
    $sales = User::factory()->role(UserRole::Sales)->create()->refresh();

    $this->actingAs($admin)->put(route('admin.cobertura.tecnicos', $zone), ['technician_ids' => [$tech->id, $sales->id]])
        ->assertSessionHasErrors('technician_ids');

    $this->actingAs($admin)->put(route('admin.cobertura.tecnicos', $zone), ['technician_ids' => [$tech->id]])
        ->assertSessionHasNoErrors();
    expect($zone->technicians()->pluck('users.id')->all())->toBe([$tech->id]);
});

it('requires coverage.manage to create zones', function () {
    $sales = User::factory()->role(UserRole::Sales)->create()->refresh();

    $this->actingAs($sales)->post(route('admin.cobertura.store'), [
        'name' => 'Norte', 'state' => 'Nuevo León', 'travel_buffer_minutes' => 30,
    ])->assertForbidden();
});
