<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Web\WebFixtures;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('media_private');
    Storage::fake('media_local');
});

function privateFile(array $attributes = []): Media
{
    $media = Media::factory()->private()->create($attributes);
    Storage::disk('media_private')->put($media->path, 'contenido-privado');

    return $media;
}

it('redirects guests to the login page', function () {
    $media = privateFile();

    $this->get(route('archivos.show', $media->uuid))->assertRedirect('/login');
    $this->getJson(route('archivos.show', $media->uuid))->assertUnauthorized();
});

it('forbids an unrelated user', function () {
    $owner = User::factory()->create();
    $media = privateFile(['uploaded_by' => $owner->id]);

    $this->actingAs(User::factory()->create())->get(route('archivos.show', $media->uuid))->assertForbidden();
});

it('lets the uploader preview an orphan file within 24 hours, with private headers', function () {
    $owner = User::factory()->create();
    $media = privateFile(['uploaded_by' => $owner->id]);

    $response = $this->actingAs($owner)->get(route('archivos.show', $media->uuid));
    $response->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('inline');

    $this->travel(25)->hours();
    $this->actingAs($owner)->get(route('archivos.show', $media->uuid))->assertForbidden();
});

it('lets users with sensitive.view open any private file', function () {
    $media = privateFile();

    $this->actingAs(WebFixtures::staff(['sensitive.view'], UserRole::Credit))
        ->get(route('archivos.show', $media->uuid))
        ->assertOk();
});

it('delegates to the policy of the entity the file is attached to', function () {
    $media = privateFile();
    $category = ProductCategory::factory()->unpublished()->create();
    $category->attachMedia($media->id, 'document');

    $this->actingAs(WebFixtures::staff(['catalog.manage']))->get(route('archivos.show', $media->uuid))->assertOk();
    $this->actingAs(WebFixtures::staff(['leads.view'], UserRole::Sales))->get(route('archivos.show', $media->uuid))->assertForbidden();
});

it('uses viewMedia so a customer only downloads client documents of their project', function () {
    $customerUser = User::factory()->role(UserRole::Customer)->create();
    $customer = Customer::factory()->create(['user_id' => $customerUser->id]);
    $project = Project::factory()->create(['customer_id' => $customer->id]);

    $internal = privateFile();
    $shared = privateFile();
    MediaAttachment::query()->create(['media_id' => $internal->id, 'attachable_type' => $project->getMorphClass(), 'attachable_id' => $project->id, 'role' => 'document']);
    MediaAttachment::query()->create(['media_id' => $shared->id, 'attachable_type' => $project->getMorphClass(), 'attachable_id' => $project->id, 'role' => 'client_document']);

    $this->actingAs($customerUser)->get(route('archivos.show', $internal->uuid))->assertForbidden();
    $this->actingAs($customerUser)->get(route('archivos.show', $shared->uuid))->assertOk();
});

it('never serves private files through the public media stream', function () {
    $private = privateFile();
    $public = Media::factory()->create();
    Storage::disk('media_local')->put($public->path, 'imagen');

    $this->get('/media/'.$private->path)->assertNotFound();

    $response = $this->get('/media/'.$public->path);
    $response->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('immutable')->toContain('max-age=31536000');
});
