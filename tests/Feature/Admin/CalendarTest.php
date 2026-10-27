<?php

use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;

test('editorial calendar lists scheduled and published posts of the month', function () {
    $category = Category::factory()->create();
    $scheduled = Post::factory()->for($category)->create(['status' => PostStatus::Scheduled, 'scheduled_at' => now()->startOfMonth()->addDays(10)->setTime(9, 0)]);
    $published = Post::factory()->for($category)->create(['status' => PostStatus::Published, 'published_at' => now()->startOfMonth()->addDays(2)]);
    Post::factory()->for($category)->create(['status' => PostStatus::Draft]);

    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->get('/admin/calendario?mes='.now()->format('Y-m'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/calendario/Index')
            ->has('events', 2)
            ->has('unscheduled', 1)
        );

    expect([$scheduled->id, $published->id])->toHaveCount(2);
});

test('dashboard exposes real editorial data', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Dashboard')
            ->has('charts.postsByMonth', 6)
            ->has('activity')
            ->has('upcoming')
            ->has('storage')
        );
});
