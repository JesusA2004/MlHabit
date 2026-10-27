<?php

use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;

function homeAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

test('admin can feature a published post for its category from the home screen', function () {
    $category = Category::factory()->create();
    $current = Post::factory()->for($category)->create(['status' => PostStatus::Published, 'published_at' => now(), 'is_featured' => true]);
    $next = Post::factory()->for($category)->create(['status' => PostStatus::Published, 'published_at' => now()]);

    $this->actingAs(homeAdmin())
        ->post('/admin/inicio/destacados', ['category_id' => $category->id, 'post_id' => $next->id])
        ->assertRedirect();

    expect($next->refresh()->is_featured)->toBeTrue();
    expect($current->refresh()->is_featured)->toBeFalse();
});

test('a draft post cannot be featured from the home screen', function () {
    $category = Category::factory()->create();
    $draft = Post::factory()->for($category)->create(['status' => PostStatus::Draft, 'published_at' => null]);

    $this->actingAs(homeAdmin())
        ->post('/admin/inicio/destacados', ['category_id' => $category->id, 'post_id' => $draft->id])
        ->assertNotFound();

    expect($draft->refresh()->is_featured)->toBeFalse();
});

test('a post from another category cannot be featured', function () {
    $categoryA = Category::factory()->create();
    $categoryB = Category::factory()->create();
    $post = Post::factory()->for($categoryB)->create(['status' => PostStatus::Published, 'published_at' => now()]);

    $this->actingAs(homeAdmin())
        ->post('/admin/inicio/destacados', ['category_id' => $categoryA->id, 'post_id' => $post->id])
        ->assertNotFound();
});
