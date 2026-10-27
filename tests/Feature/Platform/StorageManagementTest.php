<?php

use App\Enums\UserRole;
use App\Models\StorageProvider;
use Database\Seeders\StorageProviderSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Platform\PlatformFixtures as F;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StorageProviderSeeder::class);
});

test('guests and users without settings.manage cannot access storage', function () {
    $this->get('/admin/almacenamiento')->assertRedirect('/login');
    $this->actingAs(F::user(UserRole::Operations))->get('/admin/almacenamiento')->assertForbidden();
});

test('the seeder is idempotent and keeps local active', function () {
    $this->seed(StorageProviderSeeder::class);

    expect(StorageProvider::query()->count())->toBe(2)
        ->and(StorageProvider::query()->where('key', 'local')->value('is_active'))->toBeTrue();
});

test('an unconfigured synology provider reports pending status when tested', function () {
    $synology = StorageProvider::query()->where('key', 'synology')->firstOrFail();

    $this->actingAs(F::user(UserRole::SuperAdmin))
        ->post("/admin/almacenamiento/{$synology->id}/probar")
        ->assertSessionHas('error');

    expect($synology->refresh()->last_test_result['status'])->toBe('pending');
});

test('testing the local provider writes and reads back a probe file', function () {
    Storage::fake('media_local');
    $local = StorageProvider::query()->where('key', 'local')->firstOrFail();

    $this->actingAs(F::user(UserRole::SuperAdmin))
        ->post("/admin/almacenamiento/{$local->id}/probar")
        ->assertSessionHas('success');

    expect($local->refresh()->last_test_result['status'])->toBe('ok');
});

test('saving synology credentials never returns them in the page props', function () {
    $admin = F::user(UserRole::SuperAdmin);
    $synology = StorageProvider::query()->where('key', 'synology')->firstOrFail();

    $this->actingAs($admin)->put("/admin/almacenamiento/{$synology->id}", [
        'host' => '100.64.0.10',
        'port' => 22,
        'username' => 'mlhabitat',
        'auth_method' => 'password',
        'password' => 'contrasena-del-nas',
    ])->assertSessionHasNoErrors();

    $response = $this->actingAs($admin)->get('/admin/almacenamiento');
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/almacenamiento/Index')
        ->where('providers.1.config.hasPassword', true));

    expect(json_encode($response->inertiaProps('providers')))->not->toContain('contrasena-del-nas');
});

test('synology cannot be activated before a successful test', function () {
    $admin = F::user(UserRole::SuperAdmin);
    $synology = StorageProvider::query()->where('key', 'synology')->firstOrFail();

    $this->actingAs($admin)->post("/admin/almacenamiento/{$synology->id}/activar")->assertSessionHas('error');
    expect($synology->refresh()->is_active)->toBeFalse();

    $synology->update(['last_test_result' => ['status' => 'ok']]);
    $this->actingAs($admin)->post("/admin/almacenamiento/{$synology->id}/activar")->assertSessionHas('success');

    expect($synology->refresh()->is_active)->toBeTrue()
        ->and(StorageProvider::query()->where('key', 'local')->value('is_active'))->toBeFalse();
});
