<?php

use App\Models\PageView;

// Un visitante normal es navegador real (UA de escritorio) y sin cabecera de Inertia.
function browserHeaders(array $extra = []): array
{
    return array_merge([
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
    ], $extra);
}

test('registra una visita a una página pública', function () {
    $this->withHeaders(browserHeaders())->get('/')->assertOk();

    expect(PageView::query()->count())->toBe(1);
    $this->assertDatabaseHas('page_views', [
        'path' => '/',
        'studio_page_key' => 'home',
        'post_id' => null,
        'is_unique' => true,
    ]);
});

test('no registra visitas al panel de administración', function () {
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)->withHeaders(browserHeaders())->get('/admin')->assertOk();

    expect(PageView::query()->count())->toBe(0);
});

test('no registra visitas de bots ni peticiones sin user agent', function () {
    $this->withHeaders(['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])->get('/');
    $this->withHeaders(['User-Agent' => ''])->get('/');

    expect(PageView::query()->count())->toBe(0);
});

test('la segunda visita del mismo visitante el mismo día no cuenta como única', function () {
    $this->withHeaders(browserHeaders())->get('/')->assertOk();
    $this->withHeaders(browserHeaders())->get('/')->assertOk();

    expect(PageView::query()->count())->toBe(2);
    expect(PageView::query()->where('is_unique', true)->count())->toBe(1);
});

test('no almacena IP ni datos personales del visitante', function () {
    $this->withHeaders(browserHeaders())->get('/')->assertOk();

    $view = PageView::query()->firstOrFail();
    // El hash no revela la IP de origen (127.0.0.1 en pruebas).
    expect($view->visitor_hash)->not->toContain('127.0.0.1');
    expect(strlen($view->visitor_hash))->toBe(64);
});
