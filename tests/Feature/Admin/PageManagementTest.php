<?php

use App\Enums\UserRole;
use App\Models\Page;
use App\Models\PageEntry;
use App\Models\User;

test('editor can update mi historia', function () {
    $page = Page::query()->create(['key' => 'mi-historia', 'title' => 'Mi historia']);
    $editor = User::factory()->create(['role' => UserRole::Editor]);

    $response = $this->actingAs($editor)->put('/admin/paginas/mi-historia', [
        'title' => 'Mi historia real',
        'intro' => 'Una introducción honesta.',
    ]);

    $response->assertRedirect();
    expect($page->refresh()->title)->toBe('Mi historia real');
    expect($page->intro)->toBe('Una introducción honesta.');
});

test('colaborador cannot update pages', function () {
    Page::query()->create(['key' => 'mi-proceso', 'title' => 'Mi proceso']);
    $colaborador = User::factory()->create(['role' => UserRole::Colaborador]);

    $this->actingAs($colaborador)
        ->put('/admin/paginas/mi-proceso', ['title' => 'Intento'])
        ->assertForbidden();
});

test('unknown page keys are not editable', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get('/admin/paginas/pagina-inexistente')->assertNotFound();
});

test('admin can add, hide and remove a proceso entry', function () {
    $page = Page::query()->create(['key' => 'mi-proceso', 'title' => 'Mi proceso']);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->post('/admin/paginas-entradas', [
        'title' => 'Día 1: empezar',
        'entry_date' => '2026-01-01',
    ])->assertRedirect();

    $entry = PageEntry::query()->where('page_id', $page->id)->first();
    expect($entry)->not->toBeNull();

    $this->actingAs($admin)->put("/admin/paginas-entradas/{$entry->id}", [
        'title' => $entry->title,
        'is_visible' => false,
    ]);
    expect($entry->refresh()->is_visible)->toBeFalse();

    $this->actingAs($admin)->delete("/admin/paginas-entradas/{$entry->id}");
    expect(PageEntry::query()->find($entry->id))->toBeNull();
});

test('admin can edit the rich content of an existing proceso entry', function () {
    $page = Page::query()->create(['key' => 'mi-proceso', 'title' => 'Mi proceso']);
    $entry = $page->entries()->create(['title' => 'Día 1', 'is_visible' => true, 'order' => 0]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $body = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Así empezó todo.']]]]];

    $this->actingAs($admin)->put("/admin/paginas-entradas/{$entry->id}", [
        'title' => $entry->title,
        'body' => $body,
        'is_visible' => true,
    ])->assertRedirect();

    expect($entry->refresh()->body)->toBe($body);
});

test('mi proceso page exposes entry body content publicly', function () {
    $page = Page::query()->create(['key' => 'mi-proceso', 'title' => 'Mi proceso']);
    $body = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Contenido real.']]]]];
    $page->entries()->create(['title' => 'Con contenido', 'is_visible' => true, 'order' => 0, 'body' => $body]);

    $this->get('/mi-proceso')->assertInertia(fn ($p) => $p
        ->component('public/MiProceso')
        ->where('entries.0.body', $body)
    );
});

test('mi proceso page shows only visible entries publicly', function () {
    $page = Page::query()->create(['key' => 'mi-proceso', 'title' => 'Mi proceso']);
    $page->entries()->create(['title' => 'Visible', 'is_visible' => true, 'order' => 0]);
    $page->entries()->create(['title' => 'Oculta', 'is_visible' => false, 'order' => 1]);

    $this->get('/mi-proceso')->assertInertia(fn ($p) => $p
        ->component('public/MiProceso')
        ->has('entries', 1)
        ->where('entries.0.title', 'Visible')
    );
});
