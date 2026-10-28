<?php

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Support\Settings;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Platform\PlatformFixtures as F;

beforeEach(function () {
    $this->withoutVite();
});

test('settings screen requires settings.manage', function () {
    $this->actingAs(F::user(UserRole::Direction))->get('/admin/configuracion')->assertForbidden();

    $this->actingAs(F::user(UserRole::SuperAdmin))
        ->get('/admin/configuracion')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/configuracion/Index')
            ->where('settings.commercial.deposit_percent', '10.00')
            ->where('settings.commercial.tax_rate', '16.00')
            ->where('settings.security.require_2fa_for_staff', false));
});

test('commercial settings are normalized without floats and audited', function () {
    $admin = F::user(UserRole::SuperAdmin);

    $this->actingAs($admin)
        ->put('/admin/configuracion/commercial', [
            'deposit_percent' => '30',
            'tax_rate' => '8',
            'prices_include_tax' => false,
            'quote_validity_days' => 30,
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect(Settings::get('commercial.deposit_percent'))->toBe('30.00')
        ->and(Settings::get('commercial.tax_rate'))->toBe('0.0800')
        ->and(Settings::get('commercial.prices_include_tax'))->toBeFalse()
        ->and(Settings::get('commercial.quote_validity_days'))->toBe(30)
        ->and(Settings::get('commercial.currency'))->toBe('MXN');

    $log = ActivityLog::query()->where('action', 'configuracion.actualizada')->firstOrFail();
    expect($log->changes['group'])->toBe('commercial')
        ->and($log->changes['before']['deposit_percent'])->toBe('10.00')
        ->and($log->changes['after']['deposit_percent'])->toBe('30.00');
});

test('invalid values are rejected', function (string $group, array $payload, string $field) {
    $this->actingAs(F::user(UserRole::SuperAdmin))
        ->put("/admin/configuracion/{$group}", $payload)
        ->assertSessionHasErrors($field);
})->with([
    'deposit over 100' => ['commercial', ['deposit_percent' => '150', 'tax_rate' => '16', 'prices_include_tax' => true, 'quote_validity_days' => 15], 'deposit_percent'],
    'deposit with 3 decimals' => ['commercial', ['deposit_percent' => '10.123', 'tax_rate' => '16', 'prices_include_tax' => true, 'quote_validity_days' => 15], 'deposit_percent'],
    'validity zero' => ['commercial', ['deposit_percent' => '10', 'tax_rate' => '16', 'prices_include_tax' => true, 'quote_validity_days' => 0], 'quote_validity_days'],
    'unknown channel' => ['consultation', ['enabled' => true, 'duration_minutes' => 30, 'buffer_minutes' => 10, 'price' => '0', 'booking_window_days' => 21, 'min_notice_hours' => 4, 'channels' => ['telepathy']], 'channels.0'],
    'no channels while enabled' => ['consultation', ['enabled' => true, 'duration_minutes' => 30, 'buffer_minutes' => 10, 'price' => '0', 'booking_window_days' => 21, 'min_notice_hours' => 4, 'channels' => []], 'channels'],
    'consultation price zero (every consultation is paid)' => ['consultation', ['enabled' => true, 'duration_minutes' => 30, 'buffer_minutes' => 10, 'price' => '0', 'booking_window_days' => 21, 'min_notice_hours' => 4, 'channels' => ['video']], 'price'],
    'consultation price 0.00' => ['consultation', ['enabled' => true, 'duration_minutes' => 30, 'buffer_minutes' => 10, 'price' => '0.00', 'booking_window_days' => 21, 'min_notice_hours' => 4, 'channels' => ['video']], 'price'],
    'follow up zero' => ['leads', ['follow_up_hours' => 0], 'follow_up_hours'],
]);

test('consultation and leads settings are saved', function () {
    $admin = F::user(UserRole::SuperAdmin);

    $this->actingAs($admin)->put('/admin/configuracion/consultation', [
        'enabled' => true, 'duration_minutes' => 45, 'buffer_minutes' => 15, 'price' => '250.5',
        'booking_window_days' => 30, 'min_notice_hours' => 12, 'channels' => ['whatsapp', 'video'],
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->put('/admin/configuracion/leads', ['follow_up_hours' => 12])->assertSessionHasNoErrors();

    expect(Settings::get('consultation.price'))->toBe('250.50')
        ->and(Settings::get('consultation.channels'))->toBe(['video', 'whatsapp'])
        ->and(Settings::get('leads.follow_up_hours'))->toBe(12);
});

test('unknown groups are not routable', function () {
    $this->actingAs(F::user(UserRole::SuperAdmin))
        ->put('/admin/configuracion/brand', ['tagline' => 'x'])
        ->assertNotFound();
});

test('requiring 2FA for staff needs the admin to have 2FA first', function () {
    $admin = F::user(UserRole::SuperAdmin);

    $this->actingAs($admin)
        ->put('/admin/configuracion/security', ['require_2fa_for_staff' => true])
        ->assertSessionHasErrors('require_2fa_for_staff');

    $admin->forceFill(['two_factor_secret' => encrypt('x'), 'two_factor_confirmed_at' => now()])->save();

    $this->actingAs($admin)
        ->put('/admin/configuracion/security', ['require_2fa_for_staff' => true])
        ->assertSessionHasNoErrors();

    expect(Settings::get('security.require_2fa_for_staff'))->toBeTrue();
});

test('when 2FA is required staff without it are sent to security settings', function () {
    Settings::put('security', ['require_2fa_for_staff' => true]);
    $sales = F::user(UserRole::Sales);

    $this->actingAs($sales)->get('/admin')->assertRedirect('/settings/security');
    $this->actingAs($sales)->get('/settings/security')->assertOk();

    $sales->forceFill(['two_factor_secret' => encrypt('x'), 'two_factor_confirmed_at' => now()])->save();
    $this->actingAs($sales)->get('/admin')->assertOk();
});

test('the 2FA requirement does not affect customers or technicians outside admin', function () {
    Settings::put('security', ['require_2fa_for_staff' => true]);

    $this->actingAs(F::user(UserRole::Customer))->get('/settings/profile')->assertOk();
    $this->actingAs(F::user(UserRole::Customer))->get('/admin')->assertForbidden();
});
