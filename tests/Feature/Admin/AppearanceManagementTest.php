<?php

use App\Enums\UserRole;
use App\Models\ThemeSetting;
use App\Models\User;

function appearanceAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

function appearanceColaborador(): User
{
    return User::factory()->create(['role' => UserRole::Colaborador]);
}

function appearancePayload(array $overrides = []): array
{
    return array_merge([
        'colors' => ['background' => '#0D0E10', 'foreground' => '#F4F1EA', 'accent' => '#C7AA7D', 'muted' => '#ABA9A3'],
        'fonts' => ['display' => 'Fraunces', 'body' => 'Instrument Sans'],
        'buttons' => ['style' => 'solid', 'radius' => 'md'],
        'cards' => ['radius' => 'lg'],
        'logo' => ['logo_media_id' => null, 'mark_media_id' => null, 'favicon_media_id' => null],
        'site' => ['name' => 'MR. INSIGHT', 'subtitle' => 'POR ZURIEL ÁVILA'],
    ], $overrides);
}

test('guests cannot access appearance settings', function () {
    $this->get('/admin/apariencia')->assertRedirect('/login');
});

test('colaborador cannot update appearance', function () {
    $this->actingAs(appearanceColaborador())
        ->put('/admin/apariencia', appearancePayload())
        ->assertForbidden();
});

test('saving appearance only stores a draft, not the published state', function () {
    $this->actingAs(appearanceAdmin())
        ->put('/admin/apariencia', appearancePayload(['site' => ['name' => 'Borrador', 'subtitle' => 'x']]))
        ->assertRedirect();

    expect(ThemeSetting::get('appearance_draft')['site']['name'])->toBe('Borrador');
    expect(ThemeSetting::get('site'))->toBeNull();
});

test('publishing appearance updates the live keys and clears the draft', function () {
    $this->actingAs(appearanceAdmin())
        ->post('/admin/apariencia/publicar', appearancePayload(['site' => ['name' => 'Publicado', 'subtitle' => 'x']]))
        ->assertRedirect();

    expect(ThemeSetting::get('site')['name'])->toBe('Publicado');
    expect(ThemeSetting::get('appearance_draft'))->toBeNull();
});

test('discarding the draft does not touch the published state', function () {
    ThemeSetting::set('appearance_draft', appearancePayload(['site' => ['name' => 'Temporal', 'subtitle' => 'x']]));
    ThemeSetting::set('site', ['name' => 'MR. INSIGHT', 'subtitle' => 'POR ZURIEL ÁVILA']);

    $this->actingAs(appearanceAdmin())
        ->post('/admin/apariencia/descartar')
        ->assertRedirect();

    expect(ThemeSetting::get('appearance_draft'))->toBeNull();
    expect(ThemeSetting::get('site')['name'])->toBe('MR. INSIGHT');
});

test('resetting appearance restores the original carbon and gold palette', function () {
    ThemeSetting::set('colors', ['background' => '#FFFFFF', 'foreground' => '#000000', 'accent' => '#FF0000', 'muted' => '#CCCCCC']);

    $this->actingAs(appearanceAdmin())
        ->post('/admin/apariencia/restablecer')
        ->assertRedirect();

    expect(ThemeSetting::get('colors')['background'])->toBe('#0D0E10');
    expect(ThemeSetting::get('colors')['accent'])->toBe('#C7AA7D');
});

test('appearance rejects invalid hex colors', function () {
    $this->actingAs(appearanceAdmin())
        ->put('/admin/apariencia', appearancePayload(['colors' => ['background' => 'not-a-color', 'foreground' => '#F4F1EA', 'accent' => '#C7AA7D', 'muted' => '#ABA9A3']]))
        ->assertInvalid('colors.background');
});
