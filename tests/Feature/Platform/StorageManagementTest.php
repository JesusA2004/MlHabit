<?php

use App\Enums\UserRole;
use App\Models\StorageProvider;
use Database\Seeders\StorageProviderSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Platform\FlakyDisk;
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
        ->and(StorageProvider::query()->where('key', 'local')->value('is_active'))->toBeTrue()
        ->and(StorageProvider::query()->where('key', 'synology')->value('label'))->toBe('SynologyExtra (SMB vía Tailscale)');
});

test('the seeder does not overwrite an existing synology label or config', function () {
    StorageProvider::query()->where('key', 'synology')->update(['label' => 'Mi NAS']);
    $this->seed(StorageProviderSeeder::class);

    expect(StorageProvider::query()->where('key', 'synology')->value('label'))->toBe('Mi NAS');
});

test('testing synology without the mount reports it as disconnected', function () {
    $synology = StorageProvider::query()->where('key', 'synology')->firstOrFail();

    $this->actingAs(F::user(UserRole::SuperAdmin))
        ->post("/admin/almacenamiento/{$synology->id}/probar")
        ->assertSessionHas('error');

    $result = $synology->refresh()->last_test_result;
    expect($result['status'])->toBe('disconnected')
        ->and($result['state'])->toBe('disconnected')
        ->and($result['checks']['mount'])->toBeFalse();
});

test('testing the local provider writes and reads back a probe file', function () {
    Storage::fake('media_local');
    $local = StorageProvider::query()->where('key', 'local')->firstOrFail();

    $this->actingAs(F::user(UserRole::SuperAdmin))
        ->post("/admin/almacenamiento/{$local->id}/probar")
        ->assertSessionHas('success');

    expect($local->refresh()->last_test_result['status'])->toBe('ok');
});

test('the page exposes the synology mount state and never the stored config', function () {
    $admin = F::user(UserRole::SuperAdmin);
    $synology = StorageProvider::query()->where('key', 'synology')->firstOrFail();
    // Configuración histórica (SFTP) guardada: se conserva pero no se expone.
    $synology->update(['config' => ['disk' => 'media_synology', 'host' => 'nas', 'password' => 'contrasena-del-nas']]);

    FlakyDisk::install();
    $this->actingAs($admin)->post("/admin/almacenamiento/{$synology->id}/probar")->assertSessionHas('success');

    $response = $this->actingAs($admin)->get('/admin/almacenamiento');
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/almacenamiento/Index')
        ->missing('providers.0.config')
        ->missing('providers.1.config')
        ->has('providers.0.space')
        ->where('providers.1.key', 'synology')
        ->where('providers.1.synology.name', 'SynologyExtra')
        ->where('providers.1.synology.host', '100.67.89.20')
        ->where('providers.1.synology.protocol', 'SMB/CIFS')
        ->where('providers.1.synology.share', 'MLHabitat')
        ->where('providers.1.synology.state', 'connected')
        ->where('providers.1.synology.mounted', true)
        ->where('providers.1.synology.canActivate', true)
        ->where('providers.1.synology.checks.write', true)
        ->where('providers.1.synology.checks.delete', true)
        ->has('providers.1.synology.capacity.totalBytes')
        ->has('providers.1.synology.checkedAt')
        ->where('providers.1.lastTestResult.status', 'ok'));

    expect(json_encode($response->inertiaProps('providers')))->not->toContain('contrasena-del-nas')
        ->and($synology->refresh()->config['password'])->toBe('contrasena-del-nas');
});

test('the page reports disconnected without touching the mount when the NAS is not mounted', function () {
    $synology = StorageProvider::query()->where('key', 'synology')->firstOrFail();
    $synology->update(['last_test_result' => ['status' => 'ok', 'state' => 'connected', 'message' => 'ok']]);

    $this->actingAs(F::user(UserRole::SuperAdmin))->get('/admin/almacenamiento')
        ->assertInertia(fn (Assert $page) => $page
            ->where('providers.1.synology.state', 'disconnected')
            ->where('providers.1.synology.mounted', false)
            ->where('providers.1.synology.canActivate', false)
            ->where('providers.1.synology.capacity', null));
});

test('the old credentials endpoint no longer exists', function () {
    $synology = StorageProvider::query()->where('key', 'synology')->firstOrFail();

    $this->actingAs(F::user(UserRole::SuperAdmin))
        ->put("/admin/almacenamiento/{$synology->id}", ['host' => 'x'])
        ->assertNotFound();
});

test('synology is activated only after a fresh successful check', function () {
    $admin = F::user(UserRole::SuperAdmin);
    $synology = StorageProvider::query()->where('key', 'synology')->firstOrFail();

    // Una prueba «ok» vieja no basta si ahora no hay montaje.
    $synology->update(['last_test_result' => ['status' => 'ok']]);
    $this->actingAs($admin)->post("/admin/almacenamiento/{$synology->id}/activar")->assertSessionHas('error');
    expect($synology->refresh()->is_active)->toBeFalse()
        ->and($synology->last_test_result['status'])->toBe('disconnected');

    $nas = FlakyDisk::install();
    $nas->failWrites = true;
    $this->actingAs($admin)->post("/admin/almacenamiento/{$synology->id}/activar")->assertSessionHas('error');
    expect($synology->refresh()->is_active)->toBeFalse()
        ->and($synology->last_test_result['state'])->toBe('error');

    $nas->failWrites = false;
    $this->actingAs($admin)->post("/admin/almacenamiento/{$synology->id}/activar")->assertSessionHas('success');

    expect($synology->refresh()->is_active)->toBeTrue()
        ->and(StorageProvider::query()->where('key', 'local')->value('is_active'))->toBeFalse();
});
