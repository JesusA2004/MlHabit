<?php

use App\Console\Commands\Visits\ReconcilePayments;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Payments\Openpay\OpenpayConfig;
use App\Payments\Openpay\OpenpayCredentialStore;
use App\Payments\Openpay\OpenpaySetupStatus;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Visits\VisitScenario;

/*
 * Credenciales de Openpay capturadas en /admin/openpay: cifradas en la base,
 * con prioridad sobre el .env, nunca devueltas al navegador. Openpay se
 * simula con Http::fake (nunca se llama al API real).
 */

const PANEL_MERCHANT = 'mpanelmerchant00042';
const PANEL_PUBLIC = 'pk_panelpublic1234567890abcd';
const PANEL_PRIVATE = 'sk_panelprivate9876543210wxyz';
const PANEL_HOOK_PASSWORD = 'panel-hook-password-long-0001';

function ownerUser(): User
{
    return User::factory()->role(UserRole::SuperAdmin)->create()->fresh();
}

/** @return array<string, mixed> */
function credentialsPayload(array $overrides = []): array
{
    return array_merge([
        'merchant_id' => PANEL_MERCHANT,
        'public_key' => PANEL_PUBLIC,
        'private_key' => PANEL_PRIVATE,
        'webhook_user' => 'mlhabitat-panel',
        'webhook_password' => PANEL_HOOK_PASSWORD,
        'production' => false,
        'three_ds_mode' => 'always',
    ], $overrides);
}

beforeEach(function () {
    $this->withoutVite();
    VisitScenario::configureOpenpay();
});

it('sin nada en el panel usa el .env (fallback)', function () {
    $config = app(OpenpayConfig::class);

    expect($config->merchantId())->toBe(VisitScenario::MERCHANT)
        ->and($config->privateKey())->toBe(VisitScenario::PRIVATE_KEY)
        ->and($config->source('merchant_id'))->toBe('env')
        ->and($config->threeDsMode())->toBe('auto');
});

it('guarda las credenciales cifradas y tienen prioridad sobre el .env', function () {
    $owner = ownerUser();

    $this->actingAs($owner)
        ->put(route('admin.openpay.credenciales'), credentialsPayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    // En la base solo hay texto cifrado: ningún valor en claro.
    $raw = (string) DB::table('site_settings')->where('key', OpenpayCredentialStore::KEY)->value('value');
    expect($raw)->not->toContain(PANEL_PRIVATE)
        ->not->toContain(PANEL_HOOK_PASSWORD)
        ->not->toContain(PANEL_MERCHANT)
        ->not->toContain(PANEL_PUBLIC);

    $config = app(OpenpayConfig::class);
    expect($config->merchantId())->toBe(PANEL_MERCHANT)
        ->and($config->publicKey())->toBe(PANEL_PUBLIC)
        ->and($config->privateKey())->toBe(PANEL_PRIVATE)
        ->and($config->webhookPassword())->toBe(PANEL_HOOK_PASSWORD)
        ->and($config->threeDsMode())->toBe('always')
        ->and($config->source('private_key'))->toBe('panel')
        ->and($config->apiBase())->toBe(OpenpayConfig::SANDBOX_API.'/v1/'.PANEL_MERCHANT)
        // El checkout recibe la llave pública del panel, nunca la privada.
        ->and($config->jsConfig())->toBe(['merchantId' => PANEL_MERCHANT, 'publicKey' => PANEL_PUBLIC, 'sandbox' => true, 'configured' => true]);

    // Auditoría sin valores de llaves.
    $log = ActivityLog::query()->where('action', 'openpay.credenciales_actualizadas')->sole();
    expect(json_encode($log->changes))->not->toContain(PANEL_PRIVATE)->not->toContain(PANEL_HOOK_PASSWORD)
        ->and($log->changes['campos'])->toContain('private_key', 'merchant_id');
});

it('el cobro usa la llave privada del panel (HTTP Basic) y el webhook su contraseña', function () {
    app(OpenpayCredentialStore::class)->put(credentialsPayload());
    VisitScenario::fakeOpenpay(['GET list' => Factory::response([])]);

    $this->actingAs(ownerUser())->post(route('admin.openpay.probar'))->assertSessionHas('success');

    Http::assertSent(fn (Request $r) => str_starts_with($r->url(), OpenpayConfig::SANDBOX_API.'/v1/'.PANEL_MERCHANT.'/charges')
        && $r->method() === 'GET'
        && $r->hasHeader('Authorization', 'Basic '.base64_encode(PANEL_PRIVATE.':')));

    // El webhook autentica con lo del panel (ya no con lo del .env).
    $this->postJson(route('webhooks.openpay'), ['type' => 'verification', 'verification_code' => 'PANEL123'], VisitScenario::webhookAuth())->assertUnauthorized();
    $this->postJson(route('webhooks.openpay'), ['type' => 'verification', 'verification_code' => 'PANEL123'], VisitScenario::webhookAuth('mlhabitat-panel', PANEL_HOOK_PASSWORD))->assertOk();
});

it('nunca envía la llave privada ni la contraseña del webhook al navegador', function () {
    app(OpenpayCredentialStore::class)->put(credentialsPayload());

    $response = $this->actingAs(ownerUser())
        ->get(route('admin.openpay.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('admin/openpay/Index', false)
            ->where('canEdit', true)
            ->where('credentials.privateKey.masked', '••••wxyz')
            ->where('credentials.privateKey.source', 'panel')
            ->where('credentials.webhookPassword.masked', '••••0001')
            ->where('credentials.merchantId.value', PANEL_MERCHANT)
            ->missing('credentials.privateKey.value')
            ->has('checklist', 10)
            ->has('testCards')
            ->where('webhookUrl', route('webhooks.openpay')));

    expect($response->getContent())->not->toContain(PANEL_PRIVATE)->not->toContain(PANEL_HOOK_PASSWORD);
});

it('al editar sin reemplazar los secretos se conservan; vacío vuelve al .env', function () {
    $owner = ownerUser();
    app(OpenpayCredentialStore::class)->put(credentialsPayload());

    $payload = credentialsPayload(['merchant_id' => '', 'production' => true, 'three_ds_mode' => 'auto']);
    unset($payload['private_key'], $payload['webhook_password']);

    $this->actingAs($owner)->put(route('admin.openpay.credenciales'), $payload)->assertSessionHasNoErrors();

    $config = app(OpenpayConfig::class);
    expect($config->privateKey())->toBe(PANEL_PRIVATE)
        ->and($config->webhookPassword())->toBe(PANEL_HOOK_PASSWORD)
        ->and($config->merchantId())->toBe(VisitScenario::MERCHANT)
        ->and($config->source('merchant_id'))->toBe('env')
        ->and($config->isProduction())->toBeTrue()
        ->and($config->apiBase())->toStartWith(OpenpayConfig::PRODUCTION_API);

    // Quitar del panel: todo vuelve al .env.
    $this->actingAs($owner)->delete(route('admin.openpay.credenciales.restablecer'))->assertSessionHas('success');

    $config = app(OpenpayConfig::class);
    expect($config->privateKey())->toBe(VisitScenario::PRIVATE_KEY)
        ->and($config->isProduction())->toBeFalse()
        ->and(SiteSetting::query()->where('key', OpenpayCredentialStore::KEY)->exists())->toBeFalse()
        ->and(ActivityLog::query()->where('action', 'openpay.credenciales_restablecidas')->exists())->toBeTrue();
});

it('valida el formato de las llaves (la pública no va donde la privada)', function () {
    $this->actingAs(ownerUser())
        ->put(route('admin.openpay.credenciales'), credentialsPayload(['private_key' => PANEL_PUBLIC, 'public_key' => 'sk_oops123456789', 'webhook_password' => 'corta']))
        ->assertSessionHasErrors(['private_key', 'public_key', 'webhook_password']);

    expect(app(OpenpayCredentialStore::class)->hasAny())->toBeFalse();
});

it('solo quien tiene settings.manage puede cambiar credenciales; finanzas solo las ve enmascaradas', function () {
    $finance = User::factory()->role(UserRole::Finance)->create()->fresh();

    $this->actingAs($finance)->put(route('admin.openpay.credenciales'), credentialsPayload())->assertForbidden();
    $this->actingAs($finance)->delete(route('admin.openpay.credenciales.restablecer'))->assertForbidden();
    $this->actingAs(User::factory()->role(UserRole::Sales)->create()->fresh())->put(route('admin.openpay.credenciales'), credentialsPayload())->assertForbidden();

    app(OpenpayCredentialStore::class)->put(credentialsPayload());

    $content = $this->actingAs($finance)->get(route('admin.openpay.index'))
        ->assertInertia(fn (Assert $p) => $p->where('canEdit', false)
            ->where('credentials.merchantId.value', '••••0042')
            ->where('credentials.merchantId.panelValue', ''))
        ->getContent();

    expect($content)->not->toContain(PANEL_PRIVATE)->not->toContain(PANEL_MERCHANT)->not->toContain(PANEL_PUBLIC);
});

it('si cambia la APP_KEY lo guardado se ignora y se usa el .env', function () {
    SiteSetting::set(OpenpayCredentialStore::KEY, ['payload' => 'texto-que-no-se-puede-descifrar']);

    $store = app(OpenpayCredentialStore::class);
    expect(app(OpenpayConfig::class)->privateKey())->toBe(VisitScenario::PRIVATE_KEY)
        ->and($store->isUnreadable())->toBeTrue();
});

it('probar conexión: éxito, credenciales rechazadas y prueba vieja tras cambiar llaves', function () {
    $owner = ownerUser();
    $setup = app(OpenpaySetupStatus::class);

    VisitScenario::fakeOpenpay(['GET list' => Factory::response([])]);
    $this->actingAs($owner)->post(route('admin.openpay.probar'))
        ->assertSessionHas('success', 'Conexión correcta: Openpay aceptó las credenciales.');

    expect($setup->lastPing())->toMatchArray(['ok' => true, 'current' => true])
        ->and(collect($setup->checklist())->firstWhere('key', 'connection')['status'])->toBe('done');

    VisitScenario::fakeOpenpay(['GET list' => Factory::response(VisitScenario::error(1002, 401), 401)]);
    $this->actingAs($owner)->post(route('admin.openpay.probar'))
        ->assertSessionHas('error', 'Credenciales rechazadas: revisa la llave privada y el ambiente (sandbox/producción).');
    expect(collect($setup->checklist())->firstWhere('key', 'connection')['status'])->toBe('warning');

    // Nuevas llaves → la prueba anterior ya no cuenta.
    app(OpenpayCredentialStore::class)->put(credentialsPayload());
    expect(app(OpenpaySetupStatus::class)->lastPing()['current'])->toBeFalse()
        ->and(collect(app(OpenpaySetupStatus::class)->checklist())->firstWhere('key', 'connection')['status'])->toBe('pending');

    // Nunca se creó un cargo.
    expect(VisitScenario::chargeRequests())->toBe(0);
});

it('la lista de activación refleja webhook, programador y cola', function () {
    $setup = app(OpenpaySetupStatus::class);
    $status = fn (string $key) => collect(app(OpenpaySetupStatus::class)->checklist())->firstWhere('key', $key)['status'];

    expect($setup->webhook()['state'])->toBe('not_registered')
        ->and($status('credentials'))->toBe('done')
        ->and($status('webhook_registered'))->toBe('pending')
        ->and($status('scheduler'))->toBe('pending');

    WebhookEvent::factory()->create(['event_type' => 'verification', 'dedupe_key' => 'verification:XYZ', 'transaction_id' => null, 'payload' => ['type' => 'verification', 'verification_code' => 'XYZ']]);
    expect($setup->webhook())->toMatchArray(['state' => 'awaiting_verification', 'code' => 'XYZ'])
        ->and($status('webhook_registered'))->toBe('done')
        ->and($status('webhook_verified'))->toBe('pending');

    WebhookEvent::factory()->create(['event_type' => 'charge.succeeded', 'dedupe_key' => 'charge.succeeded:t1:x', 'transaction_id' => 't1']);
    expect($setup->webhook()['state'])->toBe('verified')
        ->and($status('webhook_verified'))->toBe('done');

    Cache::forever(ReconcilePayments::HEARTBEAT, now()->subMinutes(2)->toIso8601String());
    expect($status('scheduler'))->toBe('done');

    // QUEUE_CONNECTION=sync en pruebas: el paso queda como opcional.
    expect($status('queue'))->toBe('optional');
});

it('payments:reconcile deja el latido del programador', function () {
    $this->artisan('payments:reconcile')->assertSuccessful();

    expect(Cache::get(ReconcilePayments::HEARTBEAT))->not->toBeNull();
});
