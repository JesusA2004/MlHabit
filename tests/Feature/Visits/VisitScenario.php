<?php

namespace Tests\Feature\Visits;

use App\Enums\BookingKind;
use App\Enums\UserRole;
use App\Models\AvailabilityRule;
use App\Models\CoveragePostalCode;
use App\Models\CoverageZone;
use App\Models\Payment;
use App\Models\SiteVisit;
use App\Models\User;
use App\Models\VisitPolicy;
use App\Services\Visits\VisitBookingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Escenario compartido de pruebas de visitas/pagos: zona con CP 06700, un
 * técnico con disponibilidad diaria 08:00–18:00, política activa y
 * credenciales FALSAS de Openpay sandbox (el API se simula con Http::fake).
 */
final class VisitScenario
{
    public const POSTAL_CODE = '06700';

    public const MERCHANT = 'mtestmerchant0001';

    public const PRIVATE_KEY = 'sk_test_super_secret_value_123';

    public const WEBHOOK_USER = 'openpay-hook';

    public const WEBHOOK_PASSWORD = 'hook-pass-very-long-123';

    public CoverageZone $zone;

    public User $technician;

    public VisitPolicy $policy;

    public static function make(?callable $policyState = null, int $technicians = 1): self
    {
        self::configureOpenpay();

        $s = new self;
        $s->zone = CoverageZone::factory()->create(['name' => 'Zona Roma', 'travel_buffer_minutes' => 30]);
        CoveragePostalCode::query()->create(['coverage_zone_id' => $s->zone->id, 'postal_code' => self::POSTAL_CODE]);

        for ($i = 0; $i < $technicians; $i++) {
            $tech = User::factory()->role(UserRole::Technician)->create(['name' => 'Técnico'.($i + 1).' Apellido']);
            $s->zone->technicians()->attach($tech->id);
            foreach (range(0, 6) as $weekday) {
                AvailabilityRule::query()->create([
                    'user_id' => $tech->id,
                    'kind' => BookingKind::Visit->value,
                    'weekday' => $weekday,
                    'starts_at' => '08:00:00',
                    'ends_at' => '18:00:00',
                    'is_active' => true,
                ]);
            }
            $s->technician ??= $tech;
        }

        $factory = VisitPolicy::factory()->active();
        $s->policy = ($policyState ? $policyState($factory) : $factory)->create();

        return $s;
    }

    public static function configureOpenpay(): void
    {
        config([
            'services.openpay.merchant_id' => self::MERCHANT,
            'services.openpay.public_key' => 'pk_test_public_key_123',
            'services.openpay.private_key' => self::PRIVATE_KEY,
            'services.openpay.production' => false,
            'services.openpay.webhook_user' => self::WEBHOOK_USER,
            'services.openpay.webhook_password' => self::WEBHOOK_PASSWORD,
            'services.openpay.3ds_mode' => 'auto',
            'services.openpay.timeout' => 5,
        ]);
    }

    public static function day(int $plusDays = 2): string
    {
        return CarbonImmutable::today()->addDays($plusDays)->toDateString();
    }

    /** @return array<string, mixed> */
    public static function bookingData(array $overrides = []): array
    {
        return array_merge([
            'idempotency_key' => (string) Str::uuid(),
            'postal_code' => self::POSTAL_CODE,
            'date' => self::day(),
            'start' => '10:00',
            'first_name' => 'Ana',
            'last_name' => 'López',
            'email' => 'ana'.Str::random(6).'@example.com',
            'phone' => '5512345678',
            'street' => 'Av. Álvaro Obregón',
            'exterior_number' => '120',
            'interior_number' => '3B',
            'neighborhood' => 'Roma Norte',
            'municipality' => 'Cuauhtémoc',
            'state' => 'Ciudad de México',
            'references' => 'Portón negro',
            'access_notes' => 'Tocar timbre 3B',
            'terms' => true,
        ], $overrides);
    }

    /** Reserva directa con el servicio (sin HTTP). */
    public function book(array $overrides = []): SiteVisit
    {
        $data = self::bookingData($overrides);

        return app(VisitBookingService::class)->book($data, $data['idempotency_key']);
    }

    public static function paymentOf(SiteVisit $visit): Payment
    {
        return Payment::query()->where('site_visit_id', $visit->id)->latest('id')->firstOrFail();
    }

    /** @return array<string, mixed> Transacción de Openpay (forma del API). */
    public static function transaction(array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 'trxtest00000000001',
            'authorization' => '801585',
            'operation_type' => 'in',
            'transaction_type' => 'charge',
            'method' => 'card',
            'status' => 'completed',
            'amount' => 1000.00,
            'currency' => 'MXN',
            'order_id' => 'MLH-000000000000-1',
            'description' => 'Visita técnica',
            'error_message' => null,
            'creation_date' => now()->toIso8601String(),
            'card' => [
                'type' => 'debit',
                'brand' => 'visa',
                'card_number' => '411111XXXXXX1111',
                'holder_name' => 'Ana Lopez',
                'bank_name' => 'Banamex',
            ],
        ], $overrides);
    }

    /** @return array<string, mixed> Objeto Error de Openpay. */
    public static function error(int $code, int $http = 402): array
    {
        return ['category' => 'gateway', 'description' => 'The card was declined by the bank', 'http_code' => $http, 'error_code' => $code, 'request_id' => 'req-'.Str::random(8)];
    }

    /**
     * Simula el API de Openpay. Claves: 'POST charge', 'GET get', 'GET list', 'POST refund'.
     * Cada valor es una respuesta o un closure (Request) => respuesta.
     *
     * @param  array<string, mixed>  $routes
     */
    public static function fakeOpenpay(array $routes): void
    {
        // Cada llamada reemplaza la simulación anterior (y su registro de peticiones).
        Http::swap(new Factory(app('events')));

        Http::fake(function (Request $request) use ($routes) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            $rest = (string) preg_replace('#^/v1/[^/]+/#', '', $path);

            $key = match (true) {
                $rest === 'charges' && $request->method() === 'GET' => 'GET list',
                $rest === 'charges' => 'POST charge',
                str_ends_with($rest, '/refund') => 'POST refund',
                str_starts_with($rest, 'charges/') => 'GET get',
                default => $request->method().' '.$rest,
            };

            $handler = $routes[$key] ?? null;

            if ($handler === null) {
                return Factory::response(self::error(1005, 404), 404);
            }

            return is_callable($handler) ? $handler($request) : $handler;
        });
    }

    /** Cuenta las peticiones de cobro (POST /charges) enviadas. */
    public static function chargeRequests(): int
    {
        return count(Http::recorded(fn (Request $r) => $r->method() === 'POST' && str_ends_with((string) parse_url($r->url(), PHP_URL_PATH), '/charges')));
    }

    /** @return array<string, string> Cabecera Basic del webhook. */
    public static function webhookAuth(?string $user = null, ?string $password = null): array
    {
        return ['Authorization' => 'Basic '.base64_encode(($user ?? self::WEBHOOK_USER).':'.($password ?? self::WEBHOOK_PASSWORD))];
    }

    /** @return array<string, string> */
    public static function chargeBody(?string $attemptKey = null): array
    {
        return [
            'token_id' => 'tok'.Str::lower(Str::random(17)),
            'device_session_id' => Str::lower(Str::random(32)),
            'attempt_key' => $attemptKey ?? (string) Str::uuid(),
        ];
    }
}
