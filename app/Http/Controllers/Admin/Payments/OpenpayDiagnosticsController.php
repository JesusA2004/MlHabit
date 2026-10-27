<?php

namespace App\Http\Controllers\Admin\Payments;

use App\Enums\PaymentAttemptStatus;
use App\Enums\WebhookEventStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentAttempt;
use App\Models\WebhookEvent;
use App\Payments\Openpay\OpenpayConfig;
use App\Payments\PaymentGateway;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /admin/openpay — diagnóstico SIN revelar secretos: solo configurado/no
 * configurado por variable, últimos 4 del merchant id, ambiente, último
 * webhook, último error, código de verificación y prueba de conexión.
 */
class OpenpayDiagnosticsController extends Controller
{
    private const PING_CACHE = 'openpay.diagnostics.last_ping';

    public function index(OpenpayConfig $config): Response
    {
        Gate::authorize('openpay.diagnostics');

        $lastWebhook = WebhookEvent::query()->where('provider', 'openpay')->where('event_type', '!=', 'verification')->latest('id')->first();
        $verification = WebhookEvent::query()->where('provider', 'openpay')->where('event_type', 'verification')->latest('id')->first();
        $lastFailedWebhook = WebhookEvent::query()->where('provider', 'openpay')->where('status', WebhookEventStatus::Failed->value)->latest('id')->first();
        $lastDecline = PaymentAttempt::query()->whereIn('status', [PaymentAttemptStatus::Declined->value, PaymentAttemptStatus::Unknown->value])->latest('id')->first();

        return Inertia::render('admin/openpay/Index', [
            'environment' => $config->isProduction() ? 'production' : 'sandbox',
            'variables' => [
                ['key' => 'OPENPAY_MERCHANT_ID', 'configured' => $config->merchantId() !== '', 'hint' => $config->maskedMerchantId()],
                ['key' => 'OPENPAY_PUBLIC_KEY', 'configured' => $config->publicKey() !== '', 'hint' => null],
                ['key' => 'OPENPAY_PRIVATE_KEY', 'configured' => $config->privateKey() !== '', 'hint' => null],
                ['key' => 'OPENPAY_WEBHOOK_USER', 'configured' => $config->webhookUser() !== '', 'hint' => null],
                ['key' => 'OPENPAY_WEBHOOK_PASSWORD', 'configured' => $config->webhookPassword() !== '', 'hint' => null],
                ['key' => 'OPENPAY_PRODUCTION', 'configured' => true, 'hint' => $config->isProduction() ? 'true' : 'false'],
                ['key' => 'OPENPAY_3DS_MODE', 'configured' => true, 'hint' => $config->threeDsMode()],
            ],
            'configured' => $config->isConfigured(),
            'webhookConfigured' => $config->webhookConfigured(),
            'webhookUrl' => route('webhooks.openpay'),
            'webhookEvents' => ['charge.succeeded', 'charge.failed', 'charge.cancelled', 'charge.refunded', 'charge.rescored.to.decline', 'chargeback.created', 'chargeback.accepted', 'chargeback.rejected'],
            'dashboardUrl' => $config->isProduction() ? 'https://dashboard.openpay.mx' : 'https://sandbox-dashboard.openpay.mx',
            'lastWebhook' => $lastWebhook ? [
                'type' => $lastWebhook->event_type,
                'status' => $lastWebhook->status->toArray(),
                'receivedAt' => $lastWebhook->received_at?->toIso8601String(),
                'error' => $lastWebhook->error,
            ] : null,
            'verification' => $verification ? [
                'code' => (string) ($verification->payload['verification_code'] ?? ''),
                'receivedAt' => $verification->received_at?->toIso8601String(),
            ] : null,
            'lastError' => $this->lastError($lastFailedWebhook, $lastDecline),
            'lastPing' => Cache::get(self::PING_CACHE),
        ]);
    }

    public function test(PaymentGateway $gateway, ActivityLogger $audit): RedirectResponse
    {
        Gate::authorize('openpay.diagnostics');

        $result = $gateway->ping();
        Cache::put(self::PING_CACHE, [...$result->toArray(), 'at' => now()->toIso8601String()], now()->addDays(7));
        $audit->log('openpay.prueba_conexion', null, ['ok' => $result->ok, 'http' => $result->httpStatus, 'codigo' => $result->errorCode], $result->ok ? 'success' : 'error');

        return back()->with($result->ok ? 'success' : 'error', $result->message);
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
