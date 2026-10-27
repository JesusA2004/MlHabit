<?php

use App\Models\Category;
use App\Models\Post;
use Database\Seeders\CategorySeeder;
use Database\Seeders\HomepageSectionSeeder;
use Database\Seeders\PageSeeder;
use Database\Seeders\SiteSettingsSeeder;
use Database\Seeders\StorageProviderSeeder;
use Database\Seeders\ThemeSettingsSeeder;

beforeEach(function () {
    $this->seed([
        CategorySeeder::class,
        ThemeSettingsSeeder::class,
        SiteSettingsSeeder::class,
        HomepageSectionSeeder::class,
        PageSeeder::class,
        StorageProviderSeeder::class,
    ]);
});

test('home page loads with seeded categories and no fabricated posts', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('public/Home')
        ->where('categories.0.slug', 'negocios')
        ->where('tips.posts', [])
    );
});

test('tips index only lists published posts', function () {
    $category = Category::query()->where('slug', 'negocios')->first();

    Post::factory()->for($category)->create(['status' => 'draft']);
    $published = Post::factory()->for($category)->published()->create();

    $response = $this->get('/tips');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('public/Tips')
        ->has('posts.data', 1)
        ->where('posts.data.0.slug', $published->slug)
    );
});

test('tips filter by category query param', function () {
    $negocios = Category::query()->where('slug', 'negocios')->first();
    $vida = Category::query()->where('slug', 'vida')->first();

    Post::factory()->for($negocios)->published()->create();
    Post::factory()->for($vida)->published()->create();

    $response = $this->get('/tips?categoria=vida');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('public/Tips')
        ->has('posts.data', 1)
        ->where('posts.data.0.category.slug', 'vida')
    );
});

test('post detail page renders a published post', function () {
    $category = Category::query()->where('slug', 'habitos')->first();
    $post = Post::factory()->for($category)->published()->create();

    $response = $this->get("/tips/{$post->slug}");

    $response->assertOk();
    // La página de un tip ahora se renderiza con el motor de bloques de
    // Studio: la plantilla "tip" se resuelve contra la publicación actual.
    $response->assertInertia(fn ($page) => $page
        ->component('public/PostShow')
        ->where('studio.meta.title', $post->title)
        ->where('studio.resolved.post.slug', $post->slug)
    );
});

test('draft posts are not publicly accessible', function () {
    $category = Category::query()->where('slug', 'vida')->first();
    $draft = Post::factory()->for($category)->create(['status' => 'draft']);

    $this->get("/tips/{$draft->slug}")->assertNotFound();
});

test('unknown routes render the branded 404 page', function () {
    $response = $this->get('/esto-no-existe');

    $response->assertNotFound();
    $response->assertInertia(fn ($page) => $page->component('public/NotFound'));
});

test('static public pages respond successfully', function () {
    $this->get('/mi-historia')->assertOk();
    $this->get('/mi-proceso')->assertOk();
    $this->get('/redes')->assertOk();
    $this->get('/aviso-de-privacidad')->assertOk();
});
