<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Platform\PlatformFixtures as F;

function platformNotify(User $user, array $data, bool $read = false): string
{
    $id = (string) Str::uuid();
    DB::table('notifications')->insert([
        'id' => $id,
        'type' => 'App\\Notifications\\Test',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode($data),
        'read_at' => $read ? now() : null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

beforeEach(function () {
    $this->withoutVite();
});

test('the json feed returns only my notifications with unread count', function () {
    $me = F::user(UserRole::Sales);
    $other = F::user(UserRole::Sales);
    platformNotify($me, ['title' => 'Nuevo prospecto asignado', 'url' => '/admin/prospectos/1', 'tone' => 'info']);
    platformNotify($me, ['title' => 'Leída'], true);
    platformNotify($other, ['title' => 'Ajena']);

    $this->actingAs($me)
        ->getJson('/notificaciones')
        ->assertOk()
        ->assertJsonPath('unread', 1)
        ->assertJsonCount(2, 'items')
        ->assertJsonMissing(['title' => 'Ajena']);
});

test('external urls are dropped from notification data', function () {
    $me = F::user(UserRole::Sales);
    platformNotify($me, ['title' => 'Phishing', 'url' => 'https://malicioso.example/login']);
    platformNotify($me, ['title' => 'Protocolo relativo', 'url' => '//malicioso.example']);

    $items = $this->actingAs($me)->getJson('/notificaciones')->json('items');

    expect(collect($items)->pluck('url')->filter()->all())->toBe([]);
});

test('marking as read is limited to my own notifications', function () {
    $me = F::user(UserRole::Sales);
    $other = F::user(UserRole::Sales);
    $mine = platformNotify($me, ['title' => 'Mía']);
    $theirs = platformNotify($other, ['title' => 'Ajena']);

    $this->actingAs($me)->patchJson("/notificaciones/{$mine}/leida")->assertOk()->assertJsonPath('unread', 0);
    $this->actingAs($me)->patchJson("/notificaciones/{$theirs}/leida")->assertNotFound();

    expect(DB::table('notifications')->where('id', $theirs)->value('read_at'))->toBeNull();
});

test('all notifications can be marked as read and the page renders', function () {
    $me = F::user(UserRole::Customer);
    platformNotify($me, ['title' => 'Uno']);
    platformNotify($me, ['title' => 'Dos']);

    $this->actingAs($me)->postJson('/notificaciones/leidas')->assertOk()->assertJsonPath('unread', 0);

    $this->actingAs($me)
        ->get('/notificaciones')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notificaciones/Index')
            ->where('unread', 0)
            ->has('notifications.data', 2));
});

test('guests cannot read notifications', function () {
    $this->getJson('/notificaciones')->assertUnauthorized();
});
