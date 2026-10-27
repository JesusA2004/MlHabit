<?php

use App\Enums\UserRole;
use App\Models\StorageProvider;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function storageAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

function storageEditor(): User
{
    return User::factory()->create(['role' => UserRole::Editor]);
}

beforeEach(function () {
    StorageProvider::query()->create(['key' => 'local', 'label' => 'Almacenamiento local', 'is_active' => true, 'config' => ['disk' => 'media_local']]);
    StorageProvider::query()->create(['key' => 'synology', 'label' => 'Synology NAS', 'is_active' => false, 'config' => ['disk' => 'media_synology']]);
});

test('guests cannot access the storage panel', function () {
    $this->get('/admin/almacenamiento')->assertRedirect('/login');
});

test('an editor cannot access the storage panel', function () {
    $this->actingAs(storageEditor())->get('/admin/almacenamiento')->assertForbidden();
});

test('an unconfigured synology provider reports pending status when tested', function () {
    $synology = StorageProvider::query()->where('key', 'synology')->first();

    $this->actingAs(storageAdmin())
        ->post("/admin/almacenamiento/{$synology->id}/probar")
        ->assertRedirect();

    expect($synology->refresh()->last_test_result['status'])->toBe('pending');
});

test('testing the local provider writes and reads back a real probe file', function () {
    Storage::fake('media_local');
    $local = StorageProvider::query()->where('key', 'local')->first();

    $this->actingAs(storageAdmin())
        ->post("/admin/almacenamiento/{$local->id}/probar")
        ->assertRedirect();

    expect($local->refresh()->last_test_result['status'])->toBe('ok');
    expect($local->last_tested_at)->not->toBeNull();
});

test('saving synology credentials never returns them in the page props', function () {
    $synology = StorageProvider::query()->where('key', 'synology')->first();

    $this->actingAs(storageAdmin())->put("/admin/almacenamiento/{$synology->id}", [
        'host' => 'nas.tailnet.ts.net',
        'port' => 22,
        'username' => 'zuriel',
        'auth_method' => 'password',
        'password' => 'super-secreto',
    ])->assertRedirect();

    $response = $this->actingAs(storageAdmin())->get('/admin/almacenamiento');
    $response->assertInertia(function ($page) {
        $page->where('providers.1.config.hasPassword', true);
        $providers = collect($page->toArray()['props']['providers']);
        expect(json_encode($providers))->not->toContain('super-secreto');
    });
});

test('an editor cannot activate a provider', function () {
    $synology = StorageProvider::query()->where('key', 'synology')->first();

    $this->actingAs(storageEditor())
        ->post("/admin/almacenamiento/{$synology->id}/activar")
        ->assertForbidden();
});

test('activating a provider deactivates the others', function () {
    $synology = StorageProvider::query()->where('key', 'synology')->first();

    $this->actingAs(storageAdmin())->post("/admin/almacenamiento/{$synology->id}/activar")->assertRedirect();

    expect($synology->refresh()->is_active)->toBeTrue();
    expect(StorageProvider::query()->where('key', 'local')->first()->is_active)->toBeFalse();
});
