<?php

use App\Enums\UserRole;
use Tests\Feature\Platform\PlatformFixtures as F;

beforeEach(function () {
    $this->withoutVite();
});

test('web responses carry the security headers', function () {
    $response = $this->get('/login');

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN');

    expect($response->headers->get('Permissions-Policy'))
        ->toContain('camera=(self)')
        ->toContain('geolocation=()')
        ->toContain('microphone=()');

    // Fuera de producción no se envía HSTS.
    expect($response->headers->has('Strict-Transport-Security'))->toBeFalse();
});

test('the CSP ships in report-only mode by default and allows Openpay and fonts', function () {
    $response = $this->get('/login');

    expect($response->headers->has('Content-Security-Policy'))->toBeFalse();
    $csp = (string) $response->headers->get('Content-Security-Policy-Report-Only');

    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'self'")
        ->toContain("object-src 'none'")
        ->toContain('https://js.openpay.mx')
        ->toContain('https://sandbox-api.openpay.mx')
        ->toContain('https://fonts.bunny.net')
        ->toMatch("/script-src[^;]*'nonce-[A-Za-z0-9]+'/");
});

test('the CSP can be enforced by configuration', function () {
    config(['security.csp.enforce' => true]);

    $response = $this->actingAs(F::user(UserRole::SuperAdmin))->get('/settings/profile');

    expect($response->headers->get('Content-Security-Policy'))->toContain("default-src 'self'")
        ->and($response->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse();
});

test('HSTS is sent in production over https', function () {
    app()->detectEnvironment(fn () => 'production');

    $response = $this->get('https://localhost/login');

    expect($response->headers->get('Strict-Transport-Security'))->toContain('max-age=31536000');
    app()->detectEnvironment(fn () => 'testing');
});
