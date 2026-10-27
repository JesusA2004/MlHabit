<?php

namespace Tests\Feature\Projects\Support;

use App\Enums\PaymentConcept;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Enums\VisitCreditStatus;
use App\Models\Address;
use App\Models\Contract;
use App\Models\CoverageZone;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Quote;
use App\Models\SiteVisit;
use App\Models\User;
use App\Models\VisitCredit;
use App\Support\Folio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Datos de prueba del módulo projects. Las tablas de otros módulos
 * (visitas, pagos, créditos) se llenan con lo mínimo necesario.
 */
final class Fixtures
{
    public static function staff(UserRole $role = UserRole::SuperAdmin): User
    {
        return User::factory()->role($role)->create()->refresh();
    }

    public static function technician(): User
    {
        return User::factory()->role(UserRole::Technician)->create()->refresh();
    }

    /** Cliente con cuenta de portal. @return array{0: User, 1: Customer} */
    public static function client(): array
    {
        $user = User::factory()->role(UserRole::Customer)->create()->refresh();
        $customer = Customer::factory()->create(['user_id' => $user->id, 'email' => $user->email]);

        return [$user, $customer];
    }

    public static function project(?Customer $customer = null, ProjectStatus $status = ProjectStatus::Design, array $attributes = []): Project
    {
        $customer ??= Customer::factory()->create();

        return Project::factory()->create([
            'customer_id' => $customer->id,
            'status' => $status,
            ...$attributes,
        ]);
    }

    public static function sentQuote(Project $project, string $total = '113500.00', array $attributes = []): Quote
    {
        $version = (int) Quote::query()->where('project_id', $project->id)->max('version') + 1;

        return Quote::factory()->sent($total)->create([
            'project_id' => $project->id,
            'version' => $version,
            ...$attributes,
        ]);
    }

    public static function visit(Customer $customer, array $attributes = []): SiteVisit
    {
        $zone = CoverageZone::factory()->create();
        $address = Address::factory()->create(['customer_id' => $customer->id, 'coverage_zone_id' => $zone->id]);
        $policyId = DB::table('visit_policies')->insertGetId([
            'version' => (int) DB::table('visit_policies')->max('version') + 1,
            'fee_amount' => '1500.00',
            'currency' => 'MXN',
            'is_creditable' => true,
            'scope' => 'Medición del espacio.',
            'cancellation_policy' => 'Sin costo con 24 h de anticipación.',
            'reschedule_policy' => 'Una vez sin costo.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return SiteVisit::query()->create([
            'uuid' => (string) Str::uuid(),
            'folio' => Folio::next('V'),
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'coverage_zone_id' => $zone->id,
            'visit_policy_id' => $policyId,
            'starts_at' => now()->setTime(10, 0),
            'ends_at' => now()->setTime(11, 30),
            'status' => 'confirmed',
            'fee_amount' => '1500.00',
            'currency' => 'MXN',
            'is_creditable' => true,
            'credit_amount' => '1500.00',
            'idempotency_key' => (string) Str::uuid(),
            'paid_at' => now(),
            ...$attributes,
        ]);
    }

    public static function payment(Customer $customer, PaymentConcept $concept, string $amount, PaymentStatus $status = PaymentStatus::Paid, array $links = []): Payment
    {
        return Payment::query()->create([
            'folio' => Folio::next('G'),
            'idempotency_key' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'concept' => $concept,
            'description' => 'Pago de prueba',
            'method' => PaymentMethod::Transfer,
            'provider' => PaymentProvider::Manual,
            'amount' => $amount,
            'currency' => 'MXN',
            'status' => $status,
            'paid_at' => $status === PaymentStatus::Paid ? now() : null,
            ...$links,
        ]);
    }

    /** Crédito de visita disponible del cliente (opcionalmente ligado a proyecto/prospecto). */
    public static function credit(Customer $customer, string $amount = '1500.00', array $visitAttributes = [], array $creditAttributes = []): VisitCredit
    {
        $visit = self::visit($customer, ['status' => 'completed', ...$visitAttributes]);
        $payment = self::payment($customer, PaymentConcept::VisitFee, '1500.00', PaymentStatus::Paid, ['site_visit_id' => $visit->id]);

        return VisitCredit::query()->create([
            'site_visit_id' => $visit->id,
            'payment_id' => $payment->id,
            'amount' => $amount,
            'status' => VisitCreditStatus::Available,
            'expires_on' => now()->addDays(60)->toDateString(),
            ...$creditAttributes,
        ]);
    }

    public static function contract(Project $project, array $attributes = []): Contract
    {
        $quote = self::sentQuote($project, '113500.00', ['status' => QuoteStatus::Accepted]);

        return Contract::factory()->create(['quote_id' => $quote->id, 'project_id' => $project->id, ...$attributes]);
    }
}
