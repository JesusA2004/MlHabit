<?php

use App\Enums\PublicationStatus;
use App\Enums\UserRole;
use App\Models\Finish;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductCategory;
use Tests\Feature\Web\WebFixtures;

beforeEach(function () {
    $this->withoutVite();
});

it('creates a category with an automatic and unique slug', function () {
    $user = WebFixtures::staff(['catalog.manage']);
    ProductCategory::factory()->create(['name' => 'Clósets', 'slug' => 'closets']);

    $this->actingAs($user)->post(route('admin.catalogo.categorias.store'), ['name' => 'Clósets', 'is_published' => false])
        ->assertSessionHasNoErrors();

    expect(ProductCategory::query()->where('slug', 'closets-2')->exists())->toBeTrue();
});

it('validates editable slugs', function () {
    $user = WebFixtures::staff(['catalog.manage']);
    ProductCategory::factory()->create(['slug' => 'cocinas']);

    $this->actingAs($user)->post(route('admin.catalogo.categorias.store'), ['name' => 'Cocinas', 'slug' => 'cocinas'])->assertSessionHasErrors('slug');
    $this->actingAs($user)->post(route('admin.catalogo.categorias.store'), ['name' => 'Cocinas', 'slug' => 'Mal Slug!'])->assertSessionHasErrors('slug');
});

it('creates a product with gallery, finishes, tags and editorial lists', function () {
    $user = WebFixtures::staff(['catalog.manage']);
    $category = ProductCategory::factory()->create();
    $cover = Media::factory()->create();
    $render = Media::factory()->create();
    $finish = Finish::factory()->create();

    $this->actingAs($user)->post(route('admin.catalogo.productos.store'), [
        'product_category_id' => $category->id,
        'name' => 'Cocina en L',
        'summary' => 'Aprovecha las esquinas.',
        'description' => '<p>Texto</p><script>alert(1)</script>',
        'benefits' => [['title' => 'Más espacio', 'body' => null]],
        'features' => [['title' => 'Herrajes', 'body' => 'Cierre suave']],
        'cover_media_id' => $cover->id,
        'gallery' => [['id' => $render->id, 'origin' => 'render', 'caption' => 'Propuesta']],
        'finish_ids' => [$finish->id],
        'tags' => ['Moderna', 'L'],
        'status' => 'draft',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $product = Product::query()->where('slug', 'cocina-en-l')->firstOrFail();
    expect($product->description)->not->toContain('script')
        ->and($product->benefits)->toBe([['title' => 'Más espacio', 'body' => null]])
        ->and($product->mediaWithRole('gallery')->pluck('media.id')->all())->toBe([$render->id])
        ->and($render->refresh()->origin)->toBe('render')
        ->and($product->finishes()->count())->toBe(1)
        ->and($product->tags()->count())->toBe(2);
});

it('requires content.publish to publish or unpublish a product', function () {
    $category = ProductCategory::factory()->create();
    $product = Product::factory()->draft()->create(['product_category_id' => $category->id]);
    $editor = WebFixtures::staff(['catalog.manage']);

    $this->actingAs($editor)->patch(route('admin.catalogo.productos.estado', $product), ['status' => 'published'])->assertForbidden();
    expect($product->refresh()->status)->toBe(PublicationStatus::Draft);

    $publisher = WebFixtures::staff(['catalog.manage', 'content.publish'], UserRole::Quality);
    $this->actingAs($publisher)->patch(route('admin.catalogo.productos.estado', $product), ['status' => 'published'])->assertRedirect();
    expect($product->refresh()->status)->toBe(PublicationStatus::Published)->and($product->published_at)->not->toBeNull();

    $this->actingAs($editor)->patch(route('admin.catalogo.productos.estado', $product), ['status' => 'draft'])->assertForbidden();
});

it('forbids the catalog to staff without catalog.manage', function () {
    $this->actingAs(WebFixtures::staff(['content.manage']))->get(route('admin.catalogo.index'))->assertForbidden();
    $this->actingAs(WebFixtures::staff(['content.manage']))->post(route('admin.catalogo.categorias.store'), ['name' => 'X'])->assertForbidden();
});

it('shows only published content on public catalog pages', function () {
    $category = ProductCategory::factory()->create(['slug' => 'cocinas']);
    $published = Product::factory()->create(['product_category_id' => $category->id, 'slug' => 'cocina-l']);
    Product::factory()->draft()->create(['product_category_id' => $category->id, 'slug' => 'borrador']);
    $hidden = ProductCategory::factory()->unpublished()->create(['slug' => 'oculta']);
    Product::factory()->create(['product_category_id' => $hidden->id, 'slug' => 'en-oculta']);
    $other = ProductCategory::factory()->create(['slug' => 'closets']);

    $this->get('/catalogo/cocinas')->assertOk()->assertInertia(fn ($page) => $page->component('public/catalogo/Category')->has('products', 1));
    $this->get('/catalogo/cocinas/cocina-l')->assertOk()->assertInertia(fn ($page) => $page->component('public/catalogo/Product')
        ->where('product.name', $published->name)
        ->where('product.price', null)
        ->where('ctaHref', '/solicitar?categoria=cocinas'));
    $this->get('/catalogo/cocinas/borrador')->assertNotFound();
    $this->get('/catalogo/oculta')->assertNotFound();
    $this->get('/catalogo/oculta/en-oculta')->assertNotFound();
    $this->get('/catalogo/closets/cocina-l')->assertNotFound();
    $this->get('/catalogo')->assertOk()->assertInertia(fn ($page) => $page->component('public/catalogo/Index')->has('categories', 2));
    expect($other->exists)->toBeTrue();
});

it('shows the price range only when show_price is enabled', function () {
    $category = ProductCategory::factory()->create(['slug' => 'recamaras']);
    Product::factory()->withPrice('25000.00', '40000.00')->create(['product_category_id' => $category->id, 'slug' => 'cama']);

    $this->get('/catalogo/recamaras/cama')->assertInertia(fn ($page) => $page->where('product.price.from', '25000.00'));
});
