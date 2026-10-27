<?php

use App\Models\Category;
use App\Models\PageView;
use App\Models\Post;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function seedViews(?Post $post = null, int $count = 5, bool $unique = true): void
{
    foreach (range(1, $count) as $i) {
        PageView::query()->create([
            'path' => $post ? '/tips/'.$post->slug : '/',
            'post_id' => $post?->id,
            'studio_page_key' => $post ? 'tip' : 'home',
            'label' => $post?->title ?? 'Inicio',
            'referrer_host' => $i % 2 === 0 ? 'google.com' : null,
            'visitor_hash' => hash('sha256', 'v'.$i),
            'is_unique' => $unique,
            'viewed_on' => now()->subDays($i % 3)->toDateString(),
        ]);
    }
}

test('invitados no pueden ver las estadísticas', function () {
    $this->get('/admin/estadisticas')->assertRedirect(route('login'));
});

test('un usuario autenticado ve el panel de estadísticas', function () {
    $user = User::factory()->create();

    seedViews(null, 4);

    $this->actingAs($user)
        ->get('/admin/estadisticas')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/estadisticas/Index')
            ->where('range', 30)
            ->where('summary.totalViews', 4)
            ->has('series', 30)
            ->has('topPages'),
        );
});

test('el ranking de lo más visto ordena las publicaciones por visitas', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    $popular = Post::factory()->create(['category_id' => $category->id, 'title' => 'Muy visto']);
    $quiet = Post::factory()->create(['category_id' => $category->id, 'title' => 'Poco visto']);

    seedViews($popular, 10);
    seedViews($quiet, 2);

    $this->actingAs($user)
        ->get('/admin/estadisticas')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('topPosts.0.title', 'Muy visto')
            ->where('topPosts.0.views', 10)
            ->where('topPosts.1.title', 'Poco visto'),
        );
});

test('el rango se limita a valores permitidos', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/estadisticas?dias=999')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('range', 30));

    $this->actingAs($user)
        ->get('/admin/estadisticas?dias=7')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('range', 7)->has('series', 7));
});
