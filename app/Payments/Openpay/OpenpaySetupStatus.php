<?php

namespace App\Payments\Openpay;

use App\Console\Commands\Visits\ReconcilePayments;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\WebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Lista "Pasos para activar cobros" de /admin/openpay: cada paso con su
 * estado calculado a partir de datos reales (credenciales, última prueba
 * de conexión, eventos del webhook, cola, programador, HTTPS).
 *
 * Estados: done (listo) · pending (falta) · warning (revisar) · optional.
 */
class OpenpaySetupStatus
{
    /** Caché de la última prueba de conexión (la escribe el controlador). */
    public const PING_CACHE = 'openpay.diagnostics.last_ping';

    public function __construct(private readonly OpenpayConfig $config) {}

    /** @return array{ok: bool, message: string, httpStatus: int|null, errorCode: string|null, latencyMs: int|null, at: string, current: bool}|null */
    public function lastPing(): ?array
    {
        $ping = Cache::get(self::PING_CACHE);
        if (! is_array($ping)) {
            return null;
        }

        return [
            'ok' => (bool) ($ping['ok'] ?? false),
            'message' => (string) ($ping['message'] ?? ''),
            'httpStatus' => isset($ping['httpStatus']) ? (int) $ping['httpStatus'] : null,
            'errorCode' => isset($ping['errorCode']) ? (string) $ping['errorCode'] : null,
            'latencyMs' => isset($ping['latencyMs']) ? (int) $ping['latencyMs'] : null,
            'at' => (string) ($ping['at'] ?? ''),
            // ¿La prueba se hizo con las credenciales que están activas hoy?
            'current' => ($ping['fingerprint'] ?? null) === $this->config->fingerprint(),
        ];
    }

    /**
     * Estado del webhook según los eventos recibidos.
     *
     * @return array{state: 'missing_credentials'|'not_registered'|'awaiting_verification'|'verified', code: string|null, codeReceivedAt: string|null, lastEventAt: string|null}
     */
    public function webhook(): array
    {
        $verification = WebhookEvent::query()->where('provider', 'openpay')->where('event_type', 'verification')->latest('id')->first();
        $lastReal = WebhookEvent::query()->where('provider', 'openpay')->where('event_type', '!=', 'verification')->latest('id')->first();

        $state = match (true) {
            ! $this->config->webhookConfigured() => 'missing_credentials',
            // Openpay solo envía eventos reales a un webhook verificado.
            $lastReal !== null && ($verification === null || $lastReal->id > $verification->id) => 'verified',
            $verification !== null => 'awaiting_verification',
            default => 'not_registered',
        };

        return [
            'state' => $state,
            'code' => $verification !== null ? ((string) ($verification->payload['verification_code'] ?? '') ?: null) : null,
            'codeReceivedAt' => $verification?->received_at?->toIso8601String(),
            'lastEventAt' => $lastReal?->received_at?->toIso8601String(),
        ];
    }

    /**
     * Salud de la cola de trabajos (webhooks y correos se procesan ahí).
     *
     * @return array{driver: string, backlog: int, failed24h: int, ok: bool}
     */
    public function queue(): array
    {
        $driver = (string) config('queue.default', 'sync');
        $backlog = 0;
        $failed = 0;

        try {
            if ($driver === 'database') {
                $table = (string) config('queue.connections.database.table', 'jobs');
                $backlog = (int) DB::table($table)
                    ->whereNull('reserved_at')
                    ->where('available_at', '<', now()->subMinutes(5)->getTimestamp())
                    ->count();
            }
            if (Schema::hasTable('failed_jobs')) {
                $failed = (int) DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
            }
        } catch (Throwable) {
            // Sin acceso a las tablas: se muestra como "revisar".
            return ['driver' => $driver, 'backlog' => 0, 'failed24h' => 0, 'ok' => false];
        }

        return ['driver' => $driver, 'backlog' => $backlog, 'failed24h' => $failed, 'ok' => $backlog === 0];
    }

    /** Última ejecución de payments:reconcile (lo corre el programador cada 5 min). */
    public function schedulerLastRun(): ?CarbonImmutable
    {
        $at = Cache::get(ReconcilePayments::HEARTBEAT);

        return is_string($at) ? CarbonImmutable::parse($at) : null;
    }

    public function siteIsHttps(): bool
    {
        return str_starts_with(route('webhooks.openpay'), 'https://');
    }

    /**
     * @return list<array{key: string, title: string, description: string, status: 'done'|'pending'|'warning'|'optional'}>
     */
    public function checklist(): array
    {
        $production = $this->config->isProduction();
        $ping = $this->lastPing();
        $webhook = $this->webhook();
        $queue = $this->queue();
        $scheduler = $this->schedulerLastRun();
        $https = $this->siteIsHttps();

        $testPaid = Payment::query()
            ->where('provider', PaymentProvider::Openpay->value)
            ->where('method', PaymentMethod::Card->value)
            ->whereIn('status', [PaymentStatus::Paid->value, PaymentStatus::PartiallyRefunded->value, PaymentStatus::Refunded->value])
            ->exists();

        return [
            [
                'key' => 'credentials',
                'title' => 'Captura las llaves de Openpay',
                'description' => $this->config->isConfigured()
                    ? 'ID de comercio, llave pública y llave privada listas.'
                    : 'Copia el ID de comercio, la llave pública (pk_…) y la privada (sk_…) desde el dashboard de Openpay y guárdalas abajo.',
                'status' => $this->config->isConfigured() ? 'done' : 'pending',
            ],
            [
                'key' => 'connection',
                'title' => 'Prueba la conexión',
                'description' => match (true) {
                    $ping === null || ! $ping['current'] => 'Pulsa "Probar conexión" para confirmar que Openpay acepta las llaves guardadas.',
                    $ping['ok'] => 'Openpay aceptó las llaves en '.($production ? 'producción' : 'sandbox').'.',
                    default => 'La última prueba falló: '.$ping['message'],
                },
                'status' => match (true) {
                    $ping !== null && $ping['current'] && $ping['ok'] => 'done',
                    $ping !== null && $ping['current'] => 'warning',
                    default => 'pending',
                },
            ],
            [
                'key' => 'webhook_credentials',
                'title' => 'Define usuario y contraseña del webhook',
                'description' => $this->config->webhookConfigured()
                    ? 'Listos. Usa los mismos datos al registrar el webhook en Openpay.'
                    : 'Openpay los usará para avisarnos de pagos, reembolsos y contracargos. Genera una contraseña segura abajo.',
                'status' => $this->config->webhookConfigured() ? 'done' : 'pending',
            ],
            [
                'key' => 'webhook_registered',
                'title' => 'Registra el webhook en Openpay',
                'description' => $webhook['state'] === 'not_registered' || $webhook['state'] === 'missing_credentials'
                    ? 'En el dashboard de Openpay: Perfil → Webhooks → Agregar webhook, con la URL de esta página.'
                    : 'Openpay ya se comunicó con nosotros.',
                'status' => in_array($webhook['state'], ['awaiting_verification', 'verified'], true) ? 'done' : 'pending',
            ],
            [
                'key' => 'webhook_verified',
                'title' => 'Verifica el webhook',
                'description' => match ($webhook['state']) {
                    'verified' => 'Verificado: ya recibimos avisos de Openpay.',
                    'awaiting_verification' => 'Copia el código de verificación de esta página y pégalo en Openpay. Se marca como listo al llegar el primer aviso (por ejemplo, tras un pago de prueba).',
                    default => 'Después de registrarlo, Openpay manda un código que aparecerá aquí.',
                },
                'status' => $webhook['state'] === 'verified' ? 'done' : 'pending',
            ],
            [
                'key' => 'test_payment',
                'title' => $production ? 'Haz un cobro real de monto bajo' : 'Haz un pago de prueba',
                'description' => $testPaid
                    ? 'Ya hay al menos un cobro con tarjeta confirmado.'
                    : ($production
                        ? 'Agenda una asesoría con tu propia tarjeta y después reembólsala desde Pagos.'
                        : 'Agenda una asesoría o visita con la tarjeta de prueba 4111 1111 1111 1111.'),
                'status' => $testPaid ? 'done' : 'pending',
            ],
            [
                'key' => 'queue',
                'title' => 'Cola de trabajos en marcha',
                'description' => match (true) {
                    $queue['driver'] === 'sync' => 'La cola está en modo "sync": los avisos se procesan al momento. En el servidor se recomienda QUEUE_CONNECTION=database con un worker.',
                    $queue['backlog'] > 0 => "Hay {$queue['backlog']} trabajo(s) esperando más de 5 minutos: el worker (php artisan queue:work) no está corriendo.",
                    $queue['failed24h'] > 0 => "Sin atrasos, pero hubo {$queue['failed24h']} trabajo(s) fallido(s) en 24 h. Revisa los registros.",
                    default => 'Sin trabajos atrasados.',
                },
                'status' => match (true) {
                    $queue['driver'] === 'sync' => 'optional',
                    ! $queue['ok'] => 'warning',
                    $queue['failed24h'] > 0 => 'warning',
                    default => 'done',
                },
            ],
            [
                'key' => 'scheduler',
                'title' => 'Tareas programadas en marcha',
                'description' => $scheduler === null
                    ? 'Aún no detectamos el programador. En el servidor agrega el cron de "php artisan schedule:run" cada minuto.'
                    : ($scheduler->greaterThan(now()->subMinutes(15))
                        ? 'La conciliación de pagos corrió '.$scheduler->diffForHumans().'.'
                        : 'La última conciliación fue '.$scheduler->diffForHumans().': revisa el cron de "php artisan schedule:run".'),
                'status' => match (true) {
                    $scheduler === null => 'pending',
                    $scheduler->greaterThan(now()->subMinutes(15)) => 'done',
                    default => 'warning',
                },
            ],
            [
                'key' => 'https',
                'title' => 'Sitio con HTTPS',
                'description' => $https
                    ? 'El webhook usa una dirección segura (https).'
                    : 'Producción exige HTTPS con certificado válido. En pruebas locales puede quedar pendiente.',
                'status' => $https ? 'done' : ($production ? 'warning' : 'optional'),
            ],
            [
                'key' => 'production',
                'title' => 'Cambia a producción',
                'description' => $production
                    ? 'Estás cobrando en producción: los cargos son reales.'
                    : 'Cuando Openpay apruebe tu cuenta, reemplaza las llaves por las de producción y cambia el ambiente.',
                'status' => $production ? 'done' : 'optional',
            ],
        ];
    }
}
