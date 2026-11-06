<?php

namespace App\Http\Controllers\Admin\Payments;

use App\Enums\PaymentAttemptStatus;
use App\Enums\WebhookEventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\UpdateOpenpayCredentialsRequest;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Payments\Openpay\OpenpayConfig;
use App\Payments\Openpay\OpenpayCredentialStore;
use App\Payments\Openpay\OpenpaySetupStatus;
use App\Payments\PaymentGateway;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /admin/openpay — configuración y diagnóstico de cobros con tarjeta.
 *
 *  - Ver: permiso openpay.diagnostics.
 *  - Capturar credenciales: además settings.manage.
 *
 * Las credenciales se guardan cifradas (OpenpayCredentialStore) y tienen
 * prioridad sobre el .env. La llave privada y la contraseña del webhook
 * NUNCA regresan al navegador: solo "configurada" y los últimos 4.
 */
class OpenpayDiagnosticsController extends Controller
{
    public const WEBHOOK_EVENTS = ['charge.succeeded', 'charge.failed', 'charge.cancelled', 'charge.refunded', 'charge.rescored.to.decline', 'chargeback.created', 'chargeback.accepted', 'chargeback.rejected'];

    public function index(Request $request, OpenpayConfig $config, OpenpayCredentialStore $store, OpenpaySetupStatus $setup): Response
    {
        Gate::authorize('openpay.diagnostics');

        /** @var User $user */
        $user = $request->user();
        $canEdit = $user->can('settings.manage');

        $lastWebhook = WebhookEvent::query()->where('provider', 'openpay')->where('event_type', '!=', 'verification')->latest('id')->first();
        $lastFailedWebhook = WebhookEvent::query()->where('provider', 'openpay')->where('status', WebhookEventStatus::Failed->value)->latest('id')->first();
        $lastDecline = PaymentAttempt::query()->whereIn('status', [PaymentAttemptStatus::Declined->value, PaymentAttemptStatus::Unknown->value])->latest('id')->first();
        $webhook = $setup->webhook();
        $stored = $store->all();
        $updatedBy = isset($stored['updated_by']) ? User::query()->find($stored['updated_by'])?->name : null;
        $checklist = $setup->checklist();

        return Inertia::render('admin/openpay/Index', [
            'environment' => $config->isProduction() ? 'production' : 'sandbox',
            'canEdit' => $canEdit,
            'configured' => $config->isConfigured(),
            'webhookConfigured' => $config->webhookConfigured(),
            'credentials' => [
                // Merchant id y llave pública completos solo para quien puede editar
                // (de todos modos los recibe Openpay.js en el checkout); el resto ve los últimos 4.
                'merchantId' => ['value' => $canEdit ? $config->merchantId() : ($config->maskedMerchantId() ?? ''), 'source' => $config->source('merchant_id'), 'panelValue' => $canEdit ? $store->text('merchant_id') : ''],
                'publicKey' => ['value' => $canEdit ? $config->publicKey() : (OpenpayConfig::mask($config->publicKey()) ?? ''), 'source' => $config->source('public_key'), 'panelValue' => $canEdit ? $store->text('public_key') : ''],
                'privateKey' => ['masked' => OpenpayConfig::mask($config->privateKey()), 'source' => $config->source('private_key')],
                'webhookUser' => [
                    'value' => $canEdit ? $config->webhookUser() : (OpenpayConfig::mask($config->webhookUser()) ?? ''),
                    'source' => $config->source('webhook_user'),
                    'panelValue' => $canEdit ? $store->text('webhook_user') : '',
                ],
                'webhookPassword' => ['masked' => OpenpayConfig::mask($config->webhookPassword()), 'source' => $config->source('webhook_password')],
                'production' => ['value' => $config->isProduction(), 'source' => $config->source('production')],
                'threeDsMode' => ['value' => $config->threeDsMode(), 'source' => $config->source('three_ds_mode')],
                'updatedAt' => $stored['updated_at'] ?? null,
                'updatedBy' => $updatedBy,
                'hasPanelValues' => $store->hasAny(),
                'unreadable' => $store->isUnreadable(),
            ],
            'threeDsModes' => [
                ['value' => 'auto', 'label' => 'Automático (recomendado)', 'description' => 'Cobro directo; solo si el antifraude de Openpay lo pide, se envía al banco para autenticar (3D Secure).'],
                ['value' => 'always', 'label' => 'Siempre', 'description' => 'Todos los pagos pasan por la autenticación del banco. Más seguro, un paso más para el cliente.'],
                ['value' => 'never', 'label' => 'Nunca', 'description' => 'Sin 3D Secure. Openpay puede rechazar más pagos por antifraude.'],
            ],
            'webhookUrl' => route('webhooks.openpay'),
            'webhookEvents' => self::WEBHOOK_EVENTS,
            'webhook' => $webhook,
            'dashboardUrl' => $config->isProduction() ? 'https://dashboard.openpay.mx' : 'https://sandbox-dashboard.openpay.mx',
            'checklist' => $checklist,
            'progress' => [
                'done' => count(array_filter($checklist, fn ($s) => $s['status'] === 'done')),
                'required' => count(array_filter($checklist, fn ($s) => $s['status'] !== 'optional')),
            ],
            'testCards' => [
                ['number' => '4111 1111 1111 1111', 'brand' => 'Visa', 'result' => 'Aprobada'],
                ['number' => '5555 5555 5555 4444', 'brand' => 'Mastercard', 'result' => 'Aprobada'],
                ['number' => '3456 780000 00007', 'brand' => 'American Express', 'result' => 'Aprobada (CVV de 4 dígitos)'],
                ['number' => '4222 2222 2222 2220', 'brand' => 'Visa', 'result' => 'Rechazada por el banco'],
                ['number' => '4444 4444 4444 4448', 'brand' => 'Visa', 'result' => 'Fondos insuficientes'],
                ['number' => '4000 0000 0000 0069', 'brand' => 'Visa', 'result' => 'Tarjeta vencida'],
                ['number' => '5454 5454 5454 5454', 'brand' => 'Mastercard', 'result' => 'Pide 3D Secure (autenticación del banco)'],
            ],
            'lastWebhook' => $lastWebhook ? [
                'type' => $lastWebhook->event_type,
                'status' => $lastWebhook->status->toArray(),
                'receivedAt' => $lastWebhook->received_at?->toIso8601String(),
                'error' => $lastWebhook->error,
            ] : null,
            // Compatibilidad: el código también vive en `webhook.code`.
            'verification' => $webhook['code'] !== null ? ['code' => $webhook['code'], 'receivedAt' => $webhook['codeReceivedAt']] : null,
            'lastError' => $this->lastError($lastFailedWebhook, $lastDecline),
            'lastPing' => $setup->lastPing(),
            'queue' => $setup->queue(),
            'urls' => [
                'update' => route('admin.openpay.credenciales'),
                'reset' => route('admin.openpay.credenciales.restablecer'),
                'test' => route('admin.openpay.probar'),
                'settings' => '/admin/configuracion',
                'payments' => route('admin.pagos.index'),
            ],
        ]);
    }

    public function update(UpdateOpenpayCredentialsRequest $request, OpenpayConfig $config, OpenpayCredentialStore $store, ActivityLogger $audit): RedirectResponse
    {
        $before = $this->snapshot($config);
        $store->put($request->values(), $request->user()?->id);
        $after = $this->snapshot($config);

        $changed = array_keys(array_filter($after, fn ($value, $key) => $before[$key] !== $value, ARRAY_FILTER_USE_BOTH));

        if ($changed !== []) {
            // Solo nombres de campo y ambiente: nunca valores de llaves.
            $audit->log('openpay.credenciales_actualizadas', null, [
                'campos' => $changed,
                'ambiente' => ['before' => $before['production'] ? 'production' : 'sandbox', 'after' => $after['production'] ? 'production' : 'sandbox'],
                '3ds' => ['before' => $before['three_ds_mode'], 'after' => $after['three_ds_mode']],
            ]);
        }

        $message = match (true) {
            $changed === [] => 'No hubo cambios.',
            $config->isConfigured() => 'Credenciales guardadas. Ahora pulsa "Probar conexión".',
            default => 'Guardado. Aún faltan datos para activar los cobros.',
        };

        return back()->with('success', $message);
    }

    /** Borra lo capturado en el panel: se vuelve a usar solo el .env. */
    public function reset(Request $request, OpenpayCredentialStore $store, ActivityLogger $audit): RedirectResponse
    {
        Gate::authorize('openpay.diagnostics');
        Gate::authorize('settings.manage');

        if ($store->hasAny() || $store->isUnreadable()) {
            $store->forget();
            $audit->log('openpay.credenciales_restablecidas', null, ['origen' => 'env'], 'warning');
        }

        return back()->with('success', 'Se quitaron las credenciales del panel. Ahora se usan las del archivo .env (si existen).');
    }

    public function test(PaymentGateway $gateway, OpenpayConfig $config, ActivityLogger $audit): RedirectResponse
    {
        Gate::authorize('openpay.diagnostics');

        $result = $gateway->ping();
        Cache::put(OpenpaySetupStatus::PING_CACHE, [
            ...$result->toArray(),
            'at' => now()->toIso8601String(),
            'fingerprint' => $config->fingerprint(),
            'environment' => $config->isProduction() ? 'production' : 'sandbox',
        ], now()->addDays(30));
        $audit->log('openpay.prueba_conexion', null, ['ok' => $result->ok, 'http' => $result->httpStatus, 'codigo' => $result->errorCode, 'ambiente' => $config->isProduction() ? 'production' : 'sandbox'], $result->ok ? 'success' : 'error');

        return back()->with($result->ok ? 'success' : 'error', $result->message);
    }

    /**
     * Huella por campo (sin valores) para saber qué cambió.
     *
     * @return array<string, mixed>
     */
    private function snapshot(OpenpayConfig $config): array
    {
        $hash = fn (string $v) => $v === '' ? null : hash('sha256', $v);

        return [
            'merchant_id' => $hash($config->merchantId()),
            'public_key' => $hash($config->publicKey()),
            'private_key' => $hash($config->privateKey()),
            'webhook_user' => $hash($config->webhookUser()),
            'webhook_password' => $hash($config->webhookPassword()),
            'production' => $config->isProduction(),
            'three_ds_mode' => $config->threeDsMode(),
        ];
    }

    /** @return array{source: string, message: string, at: string|null}|null */
    private function lastError(?WebhookEvent $webhook, ?PaymentAttempt $attempt): ?array
    {
        $candidates = array_filter([
            $webhook ? ['source' => 'Webhook', 'message' => (string) $webhook->error, 'at' => $webhook->received_at?->toIso8601String()] : null,
            $attempt ? ['source' => 'Cobro', 'message' => trim(($attempt->error_code ? "Código {$attempt->error_code}. " : '').($attempt->error_message ?? $attempt->status->label())), 'at' => $attempt->created_at?->toIso8601String()] : null,
        ]);

        usort($candidates, fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));

        return $candidates[0] ?? null;
    }
}
