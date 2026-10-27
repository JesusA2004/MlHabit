<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function mediaUsageAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

beforeEach(function () {
    Storage::fake('media_local');
});

test('a media file used as a post cover cannot be deleted without confirmation', function () {
    $category = Category::factory()->create();
    $media = Media::factory()->create();
    $post = Post::factory()->for($category)->create(['cover_media_id' => $media->id]);

    $response = $this->actingAs(mediaUsageAdmin())->deleteJson("/admin/multimedia/{$media->id}");

    $response->assertStatus(409);
    expect($response->json('usages.0.label'))->toBe($post->title);
    expect(Media::query()->find($media->id))->not->toBeNull();
});

test('forcing the deletion of an in-use file removes it and clears the hero reference', function () {
    $media = Media::factory()->create();
    SiteSetting::set('hero', ['eyebrow' => 'x', 'title_lines' => ['x'], 'photo_media_id' => $media->id]);

    $this->actingAs(mediaUsageAdmin())
        ->deleteJson("/admin/multimedia/{$media->id}", ['force' => true])
        ->assertOk();

    expect(Media::query()->find($media->id))->toBeNull();
    expect(SiteSetting::get('hero')['photo_media_id'])->toBeNull();
});

test('a media file with no references deletes immediately without usages', function () {
    $media = Media::factory()->create();

    $response = $this->actingAs(mediaUsageAdmin())->deleteJson("/admin/multimedia/{$media->id}");

    $response->assertOk();
    expect(Media::query()->find($media->id))->toBeNull();
});

test('the usage endpoint reports a post gallery reference', function () {
    $category = Category::factory()->create();
    $media = Media::factory()->create();
    $post = Post::factory()->for($category)->create();
    $post->media()->attach($media->id, ['role' => 'gallery', 'order' => 0]);

    $response = $this->actingAs(mediaUsageAdmin())->getJson("/admin/multimedia/{$media->id}/uso");

    $response->assertOk();
    expect($response->json('usages'))->toHaveCount(1);
    expect($response->json('usages.0.type'))->toBe('Galería/contenido de publicación');
});
