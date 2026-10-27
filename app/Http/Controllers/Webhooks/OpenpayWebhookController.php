<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\WebhookEventStatus;
use App\Http\Controllers\Controller;
use App\Jobs\Payments\ProcessOpenpayWebhook;
use App\Models\WebhookEvent;
use App\Payments\Openpay\OpenpayConfig;
use App\Payments\Openpay\OpenpayRedactor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * POST /webhooks/openpay — notificaciones de Openpay.
 *
 * Openpay no firma sus webhooks (investigación §8): se exige HTTP Basic
 * (comparación en tiempo constante) y el payload se trata como AVISO. El job
 * re-consulta la transacción con la llave privada y solo actúa con esa
 * respuesta. Se responde 200 rápido; un duplicado (dedupe_key UNIQUE)
 * responde 200 sin reprocesar.
 */
class OpenpayWebhookController extends Controller
{
    public function __invoke(Request $request, OpenpayConfig $config): JsonResponse
    {
        if (! $this->authorized($request, $config)) {
            Log::warning('openpay.webhook.unauthorized', ['ip' => $request->ip()]);

            return response()->json(['message' => 'No autorizado.'], 401, ['WWW-Authenticate' => 'Basic realm="openpay"']);
        }

        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload) || $payload === []) {
            return response()->json(['message' => 'Contenido inválido.'], 400);
        }

        $type = mb_substr((string) ($payload['type'] ?? 'unknown'), 0, 64);

        if ($type === 'verification') {
            $code = mb_substr((string) ($payload['verification_code'] ?? ''), 0, 64);
            $this->store('verification:'.($code !== '' ? $code : sha1((string) $request->getContent())), $type, null, [
                'type' => $type,
                'event_date' => $payload['event_date'] ?? null,
                'verification_code' => $code,
            ], WebhookEventStatus::Processed);

            Log::info('openpay.webhook.verification');

            return response()->json(['received' => true]);
        }

        $transaction = is_array($payload['transaction'] ?? null) ? $payload['transaction'] : [];
        $transactionId = isset($transaction['id']) ? mb_substr((string) $transaction['id'], 0, 64) : null;
        $dedupe = $this->dedupeKey($type, $transactionId, $payload);

        $event = $this->store($dedupe, $type, $transactionId, OpenpayRedactor::redact($payload), WebhookEventStatus::Received);

        if ($event === null) {
            // Duplicado: si el anterior falló se reintenta; si no, solo 200.
            $existing = WebhookEvent::query()->where('dedupe_key', $dedupe)->first();
            if ($existing !== null && $existing->status === WebhookEventStatus::Failed) {
                $this->dispatch($existing);
            }

            return response()->json(['received' => true, 'duplicate' => true]);
        }

        $this->dispatch($event);

        return response()->json(['received' => true]);
    }

    private function authorized(Request $request, OpenpayConfig $config): bool
    {
        if (! $config->webhookConfigured()) {
            return false;
        }

        $user = (string) $request->getUser();
        $password = (string) $request->getPassword();

        // Ambas comparaciones siempre se evalúan (tiempo constante).
        $userOk = hash_equals($config->webhookUser(), $user);
        $passwordOk = hash_equals($config->webhookPassword(), $password);

        return $userOk && $passwordOk;
    }

    /** @param array<string, mixed> $payload */
    private function dedupeKey(string $type, ?string $transactionId, array $payload): string
    {
        $date = (string) ($payload['event_date'] ?? '');
        $key = $transactionId !== null
            ? "{$type}:{$transactionId}:{$date}"
            : $type.':'.sha1((string) json_encode($payload));

        return strlen($key) <= 128 ? $key : mb_substr($type, 0, 40).':'.hash('sha256', $key);
    }

    /** @param array<string, mixed> $payload */
    private function store(string $dedupe, string $type, ?string $transactionId, array $payload, WebhookEventStatus $status): ?WebhookEvent
    {
        try {
            return WebhookEvent::query()->create([
                'provider' => 'openpay',
                'event_type' => $type,
                'dedupe_key' => $dedupe,
                'transaction_id' => $transactionId,
                'payload' => $payload,
                'status' => $status,
                'received_at' => now(),
                'processed_at' => $status === WebhookEventStatus::Processed ? now() : null,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    private function dispatch(WebhookEvent $event): void
    {
        try {
            ProcessOpenpayWebhook::dispatch($event->id);
        } catch (Throwable $e) {
            // Nunca se responde error a Openpay por un fallo interno: el evento
            // queda guardado y payments:reconcile converge el estado.
            Log::error('openpay.webhook.dispatch_failed', ['event' => $event->id, 'error' => class_basename($e)]);
        }
    }
}
