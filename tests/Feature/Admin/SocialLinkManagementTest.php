<?php

use App\Enums\UserRole;
use App\Models\SocialLink;
use App\Models\User;

test('only admin can create a social link', function () {
    $editor = User::factory()->create(['role' => UserRole::Editor]);

    $this->actingAs($editor)
        ->post('/admin/redes-sociales', ['platform' => 'instagram', 'url' => 'https://instagram.com/mrinsight'])
        ->assertForbidden();

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->post('/admin/redes-sociales', ['platform' => 'instagram', 'url' => 'https://instagram.com/mrinsight'])
        ->assertRedirect();

    expect(SocialLink::query()->where('platform', 'instagram')->exists())->toBeTrue();
});

test('a social link must use a valid url', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->post('/admin/redes-sociales', ['platform' => 'instagram', 'url' => 'not-a-url'])
        ->assertInvalid('url');
});

test('hiding a social link removes it from the public home page', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $link = SocialLink::factory()->create(['platform' => 'instagram', 'is_visible' => true]);

    $this->get('/')->assertInertia(fn ($page) => $page->where('social.0.platform', 'instagram'));

    $this->actingAs($admin)->put("/admin/redes-sociales/{$link->id}", [
        'platform' => 'instagram',
        'url' => $link->url,
        'is_visible' => false,
    ]);

    $this->get('/')->assertInertia(fn ($page) => $page->where('social', []));
});
