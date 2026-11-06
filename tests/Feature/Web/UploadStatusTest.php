<?php

use App\Enums\UserRole;
use App\Models\Media;
use App\Models\User;
use Tests\Feature\Web\WebFixtures;

/*
 * GET /subidas/medios/{uuid}/estado: la interfaz de subida consulta aquí
 * si un video sigue "procesando" (miniatura/duración con FFmpeg).
 */

it('reports the processing status of a file to the user who uploaded it', function () {
    $customer = User::factory()->role(UserRole::Customer)->create();
    $media = Media::factory()->private()->create([
        'uploaded_by' => $customer->id,
        'type' => 'video',
        'mime_type' => 'video/mp4',
        'status' => 'processing',
    ]);

    $this->actingAs($customer)
        ->getJson(route('subidas.estado', $media->uuid))
        ->assertOk()
        ->assertJson(['id' => $media->id, 'uuid' => $media->uuid, 'status' => 'processing', 'posterUrl' => null]);

    $media->update(['status' => 'ready']);

    $this->actingAs($customer)
        ->getJson(route('subidas.estado', $media->uuid))
        ->assertOk()
        ->assertJsonPath('status', 'ready');
});

it('lets library managers check any file but hides it from other users', function () {
    $owner = User::factory()->role(UserRole::Customer)->create();
    $media = Media::factory()->private()->create(['uploaded_by' => $owner->id, 'status' => 'processing']);

    $this->actingAs(User::factory()->role(UserRole::Customer)->create())
        ->getJson(route('subidas.estado', $media->uuid))
        ->assertNotFound();

    $this->actingAs(WebFixtures::staff(['media.manage']))
        ->getJson(route('subidas.estado', $media->uuid))
        ->assertOk()
        ->assertJsonPath('status', 'processing');
});

it('requires authentication to check an upload status', function () {
    $media = Media::factory()->create();

    $this->getJson(route('subidas.estado', $media->uuid))->assertUnauthorized();
});
