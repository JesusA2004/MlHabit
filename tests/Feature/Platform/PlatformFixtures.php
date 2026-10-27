<?php

namespace Tests\Feature\Platform;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Datos mínimos para pruebas del módulo platform insertados con el query
 * builder (no depende de factories de otros módulos).
 */
final class PlatformFixtures
{
    public static function user(UserRole $role, array $attributes = []): User
    {
        return User::factory()->role($role)->create($attributes);
    }

    public static function customer(): int
    {
        return (int) DB::table('customers')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'first_name' => 'Ana',
            'last_name' => 'Prueba',
            'email' => Str::random(8).'@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function lead(int $customerId, Carbon|\DateTimeInterface $createdAt, string $status = 'new', ?Carbon $contactedAt = null): int
    {
        return (int) DB::table('leads')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'folio' => 'MLH-L-'.Str::upper(Str::random(10)),
            'customer_id' => $customerId,
            'status' => $status,
            'first_contacted_at' => $contactedAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    public static function project(int $customerId, string $status, ?Carbon $contractedAt = null): int
    {
        return (int) DB::table('projects')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'folio' => 'MLH-P-'.Str::upper(Str::random(10)),
            'customer_id' => $customerId,
            'name' => 'Cocina integral',
            'status' => $status,
            'contracted_at' => $contractedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function payment(int $customerId, string $amount, string $status, ?Carbon $paidAt, string $refunded = '0.00', ?Carbon $failedAt = null): int
    {
        return (int) DB::table('payments')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'folio' => 'MLH-PG-'.Str::upper(Str::random(10)),
            'order_id' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'customer_id' => $customerId,
            'concept' => 'visit_fee',
            'description' => 'Visita técnica',
            'method' => 'card',
            'provider' => 'openpay',
            'amount' => $amount,
            'status' => $status,
            'refunded_amount' => $refunded,
            'paid_at' => $paidAt,
            'failed_at' => $failedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function refund(int $paymentId, string $amount, Carbon $processedAt): void
    {
        DB::table('refunds')->insert([
            'uuid' => (string) Str::uuid(),
            'payment_id' => $paymentId,
            'idempotency_key' => (string) Str::uuid(),
            'amount' => $amount,
            'reason' => 'Prueba',
            'status' => 'completed',
            'processed_at' => $processedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function consultation(int $leadId, int $advisorId, Carbon $startsAt, string $status = 'scheduled'): void
    {
        DB::table('consultations')->insert([
            'uuid' => (string) Str::uuid(),
            'folio' => 'MLH-A-'.Str::upper(Str::random(10)),
            'lead_id' => $leadId,
            'advisor_id' => $advisorId,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(30),
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function incident(int $projectId, string $severity, string $status = 'open'): void
    {
        DB::table('incidents')->insert([
            'folio' => 'MLH-I-'.Str::upper(Str::random(10)),
            'project_id' => $projectId,
            'category' => 'installation',
            'severity' => $severity,
            'origin' => 'internal',
            'status' => $status,
            'title' => 'Puerta desalineada',
            'description' => 'Ajuste pendiente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function warranty(int $projectId, string $status, Carbon $endsOn): void
    {
        DB::table('warranties')->insert([
            'folio' => 'MLH-G-'.Str::upper(Str::random(10)),
            'project_id' => $projectId,
            'type' => 'workmanship',
            'starts_on' => now()->subYear()->toDateString(),
            'ends_on' => $endsOn->toDateString(),
            'scope' => 'Mano de obra',
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
