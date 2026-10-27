<?php

use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\User;
use App\Services\PostService;

function postAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

function postEditor(): User
{
    return User::factory()->create(['role' => UserRole::Editor]);
}

function postColaborador(): User
{
    return User::factory()->create(['role' => UserRole::Colaborador]);
}

test('guests cannot access post management', function () {
    $this->get('/admin/publicaciones')->assertRedirect('/login');
});

test('editor can create a draft post', function () {
    $category = Category::factory()->create();
    $user = postEditor();

    $response = $this->actingAs($user)->post('/admin/publicaciones', [
        'category_id' => $category->id,
        'title' => 'Cómo empezar sin miedo',
    ]);

    $post = Post::query()->where('title', 'Cómo empezar sin miedo')->first();

    expect($post)->not->toBeNull();
    expect($post->status)->toBe(PostStatus::Draft);
    expect($post->author_id)->toBe($user->id);
    $response->assertRedirect("/admin/publicaciones/{$post->id}/edit");
});

test('colaborador cannot create a post', function () {
    $category = Category::factory()->create();

    $this->actingAs(postColaborador())
        ->post('/admin/publicaciones', ['category_id' => $category->id, 'title' => 'Intento'])
        ->assertForbidden();
});

test('publishing sets status and published_at', function () {
    $post = Post::factory()->create(['status' => PostStatus::Draft]);

    $this->actingAs(postAdmin())->post("/admin/publicaciones/{$post->id}/publicar")->assertRedirect();

    $post->refresh();
    expect($post->status)->toBe(PostStatus::Published);
    expect($post->published_at)->not->toBeNull();
});

test('scheduling requires a future date', function () {
    $post = Post::factory()->create(['status' => PostStatus::Draft]);

    $this->actingAs(postAdmin())
        ->post("/admin/publicaciones/{$post->id}/programar", ['scheduled_at' => now()->subDay()->toDateTimeString()])
        ->assertInvalid('scheduled_at');
});

test('scheduling a post sets status to scheduled', function () {
    $post = Post::factory()->create(['status' => PostStatus::Draft]);
    $future = now()->addDays(2)->toDateTimeString();

    $this->actingAs(postAdmin())->post("/admin/publicaciones/{$post->id}/programar", ['scheduled_at' => $future]);

    $post->refresh();
    expect($post->status)->toBe(PostStatus::Scheduled);
    expect($post->scheduled_at)->not->toBeNull();
});

test('archiving a published post removes it from public view', function () {
    $post = Post::factory()->published()->create();

    $this->actingAs(postAdmin())->post("/admin/publicaciones/{$post->id}/archivar");

    $post->refresh();
    expect($post->status)->toBe(PostStatus::Archived);
    $this->get("/tips/{$post->slug}")->assertNotFound();
});

test('toggling featured flips the flag', function () {
    $post = Post::factory()->create(['is_featured' => false]);

    $this->actingAs(postAdmin())->post("/admin/publicaciones/{$post->id}/destacar");
    expect($post->refresh()->is_featured)->toBeTrue();

    $this->actingAs(postAdmin())->post("/admin/publicaciones/{$post->id}/destacar");
    expect($post->refresh()->is_featured)->toBeFalse();
});

test('autosave stores a revision without changing the published post', function () {
    $post = Post::factory()->published()->create(['title' => 'Original']);

    $this->actingAs(postAdmin())->patch("/admin/publicaciones/{$post->id}/autoguardar", [
        'title' => 'Borrador en progreso',
        'excerpt' => 'Resumen temporal',
        'body' => ['type' => 'doc', 'content' => []],
    ]);

    expect(PostRevision::query()->where('post_id', $post->id)->where('type', 'autosave')->exists())->toBeTrue();
    expect($post->refresh()->title)->toBe('Original');
});

test('only staff can preview an unpublished post', function () {
    $post = Post::factory()->create(['status' => PostStatus::Draft]);

    $this->get("/admin/publicaciones/{$post->id}/vista-previa")->assertRedirect('/login');

    $this->actingAs(postAdmin())
        ->get("/admin/publicaciones/{$post->id}/vista-previa")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('public/PostShow')->where('previewing', true));
});

test('only admin can delete a post', function () {
    $post = Post::factory()->create();

    $this->actingAs(postEditor())->delete("/admin/publicaciones/{$post->id}")->assertForbidden();

    $this->actingAs(postAdmin())->delete("/admin/publicaciones/{$post->id}")->assertRedirect('/admin/publicaciones');
    expect(Post::query()->find($post->id))->toBeNull();
});

test('updating a post without related posts in the payload keeps the existing ones', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $category = Category::factory()->create();
    $other = Post::factory()->for($category)->create();
    $post = Post::factory()->for($category)->create(['related_post_ids' => [$other->id]]);

    app(PostService::class)->update($post, ['category_id' => $category->id, 'title' => 'Nuevo título']);

    expect($post->refresh()->related_post_ids)->toBe([$other->id]);
});
