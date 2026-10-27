<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;

function categoryAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

function categoryColaborador(): User
{
    return User::factory()->create(['role' => UserRole::Colaborador]);
}

test('guests cannot access category management', function () {
    $this->get('/admin/categorias')->assertRedirect('/login');
});

test('admin can create a category', function () {
    $response = $this->actingAs(categoryAdmin())->post('/admin/categorias', [
        'name' => 'Finanzas',
        'color' => '#C7AA7D',
        'status' => 'active',
    ]);

    $response->assertRedirect('/admin/categorias');
    expect(Category::query()->where('slug', 'finanzas')->exists())->toBeTrue();
});

test('colaborador cannot create a category', function () {
    $this->actingAs(categoryColaborador())
        ->post('/admin/categorias', ['name' => 'Finanzas', 'color' => '#C7AA7D', 'status' => 'active'])
        ->assertForbidden();
});

test('category slug must be unique', function () {
    Category::factory()->create(['slug' => 'negocios']);

    $this->actingAs(categoryAdmin())
        ->post('/admin/categorias', ['name' => 'Negocios', 'slug' => 'negocios', 'color' => '#C7AA7D', 'status' => 'active'])
        ->assertInvalid('slug');
});

test('a category with posts cannot be deleted', function () {
    $category = Category::factory()->create();
    Post::factory()->for($category)->create();

    $response = $this->actingAs(categoryAdmin())->delete("/admin/categorias/{$category->id}");

    $response->assertRedirect();
    expect(Category::query()->find($category->id))->not->toBeNull();
});

test('an empty category can be deleted', function () {
    $category = Category::factory()->create();

    $this->actingAs(categoryAdmin())->delete("/admin/categorias/{$category->id}");

    expect(Category::query()->find($category->id))->toBeNull();
});
