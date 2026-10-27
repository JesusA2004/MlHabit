<?php

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('security page is displayed with 2FA state', function () {
    $user = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($user)
        ->get('/settings/security')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->where('twoFactorEnabled', false)
            ->where('requiresConfirmation', true)
            ->where('twoFactorRequired', false)
            ->has('sessions')
            ->has('passwordRules'));
});

test('password can be updated through fortify', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/settings/security')
        ->put('/user/password', [
            'current_password' => 'password',
            'password' => 'una-frase-nueva-segura',
            'password_confirmation' => 'una-frase-nueva-segura',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/security');

    expect(Hash::check('una-frase-nueva-segura', $user->refresh()->password))->toBeTrue()
        ->and(ActivityLog::query()->where('action', 'usuario.contrasena_actualizada')->exists())->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/settings/security')
        ->put('/user/password', [
            'current_password' => 'wrong-password',
            'password' => 'una-frase-nueva-segura',
            'password_confirmation' => 'una-frase-nueva-segura',
        ])
        ->assertSessionHasErrors('current_password');
});

test('sessions are listed and other sessions can be closed', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->create();
    DB::table('sessions')->insert([
        ['id' => 'otra-sesion-1', 'user_id' => $user->id, 'ip_address' => '10.0.0.2', 'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/604.1', 'payload' => 'x', 'last_activity' => time() - 60],
        ['id' => 'ajena', 'user_id' => null, 'ip_address' => '10.0.0.3', 'user_agent' => 'x', 'payload' => 'x', 'last_activity' => time()],
    ]);

    $this->actingAs($user)
        ->get('/settings/security')
        ->assertInertia(fn (Assert $page) => $page
            ->where('sessionsSupported', true)
            ->where('sessions', fn ($sessions) => collect($sessions)->contains(fn ($s) => $s['platform'] === 'iOS' && $s['isMobile'] === true && $s['id'] !== 'otra-sesion-1')));

    $this->actingAs($user)
        ->delete('/settings/sessions', ['password' => 'wrong'])
        ->assertSessionHasErrors('password');

    $this->actingAs($user)
        ->delete('/settings/sessions', ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect(DB::table('sessions')->where('id', 'otra-sesion-1')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'ajena')->exists())->toBeTrue();
});
