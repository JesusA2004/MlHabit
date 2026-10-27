<?php

namespace App\Console\Commands;

use App\Enums\BookingKind;
use App\Enums\PublicationStatus;
use App\Enums\RateCardStatus;
use App\Enums\SupplierStatus;
use App\Enums\SupplierType;
use App\Enums\TechnicianStatus;
use App\Enums\UserRole;
use App\Enums\VisitCreditType;
use App\Models\AvailabilityRule;
use App\Models\CoveragePostalCode;
use App\Models\CoverageZone;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RateCard;
use App\Models\RateCardItem;
use App\Models\Supplier;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Models\VisitPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de PRUEBA para las pruebas de navegador (tests/e2e). Todo lo que
 * crea lleva "TEST" en el nombre. Se niega a correr si la base no termina
 * en `_e2e` o si el entorno es producción.
 */
class E2eSeedCommand extends Command
{
    public const PASSWORD = 'E2e-Test-2026!';

    /** @var array<string, string> rol => correo */
    public const USERS = [
        'super_admin' => 'admin.e2e@mlhabitat.test',
        'comercial' => 'comercial.e2e@mlhabitat.test',
        'diseno' => 'diseno.e2e@mlhabitat.test',
        'operaciones' => 'operaciones.e2e@mlhabitat.test',
        'calidad' => 'calidad.e2e@mlhabitat.test',
        'finanzas' => 'finanzas.e2e@mlhabitat.test',
        'tecnico' => 'tecnico.e2e@mlhabitat.test',
    ];

    protected $signature = 'e2e:seed';

    protected $description = 'Crea datos de PRUEBA (marcados TEST) para Playwright. Solo en bases *_e2e.';

    public function handle(): int
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');

        if (! str_ends_with($database, '_e2e') || app()->isProduction()) {
            $this->error("Rechazado: e2e:seed solo corre sobre una base que termine en _e2e (actual: {$database}) y fuera de producción.");

            return self::FAILURE;
        }

        DB::transaction(function () {
            $users = [];
            foreach (self::USERS as $role => $email) {
                $users[$role] = User::query()->forceCreate([
                    'name' => 'TEST '.UserRole::from($role)->label(),
                    'email' => $email,
                    'email_verified_at' => now(),
                    'password' => Hash::make(self::PASSWORD),
                    'role' => UserRole::from($role),
                    'is_active' => true,
                ]);
            }

            $supplier = Supplier::query()->create([
                'name' => 'Proveedor TEST Fabricación e Instalación',
                'type' => SupplierType::Both,
                'status' => SupplierStatus::Active,
                'email' => 'proveedor.e2e@mlhabitat.test',
                'phone' => '5500000000',
                'lead_time_days' => 10,
                'notes' => 'Datos de prueba E2E.',
            ]);

            TechnicianProfile::query()->create([
                'user_id' => $users['tecnico']->id,
                'supplier_id' => $supplier->id,
                'status' => TechnicianStatus::Active,
                'does_visits' => true,
                'does_installations' => true,
            ]);

            $zone = CoverageZone::query()->create([
                'name' => 'ZONA TEST',
                'state' => 'Ciudad de México',
                'municipality' => 'Cuauhtémoc',
                'is_active' => true,
                'visits_enabled' => true,
                'travel_buffer_minutes' => 0,
            ]);
            CoveragePostalCode::query()->create(['coverage_zone_id' => $zone->id, 'postal_code' => '01000']);
            $zone->technicians()->attach($users['tecnico']->id);
            $zone->suppliers()->attach($supplier->id);

            // Agenda para los próximos 14 días, todos los días de la semana
            // (así la suite no depende del día en que se ejecute).
            $from = now()->toDateString();
            $until = now()->addDays(14)->toDateString();
            foreach (range(0, 6) as $weekday) {
                foreach ([['tecnico', BookingKind::Visit], ['comercial', BookingKind::Consultation]] as [$role, $kind]) {
                    AvailabilityRule::query()->create([
                        'user_id' => $users[$role]->id,
                        'kind' => $kind,
                        'weekday' => $weekday,
                        'starts_at' => '08:00:00',
                        'ends_at' => '18:00:00',
                        'effective_from' => $from,
                        'effective_until' => $until,
                        'is_active' => true,
                    ]);
                }
            }

            VisitPolicy::query()->create([
                'version' => VisitPolicy::nextVersion(),
                'fee_amount' => '1500.00',
                'currency' => 'MXN',
                'is_creditable' => true,
                'credit_type' => VisitCreditType::Fixed,
                'credit_value' => '1500.00',
                'credit_validity_days' => 30,
                'credit_conditions' => 'TEST: se abona al anticipo si contratas en 30 días.',
                'duration_minutes' => 60,
                'buffer_minutes' => 0,
                'hold_minutes' => 15,
                'min_notice_hours' => 0,
                'booking_window_days' => 14,
                'scope' => 'TEST: medición del espacio y revisión técnica.',
                'cancellation_policy' => 'TEST: cancelación sin costo con 24 horas de anticipación.',
                'reschedule_policy' => 'TEST: una reprogramación sin costo.',
                'is_active' => true,
                'activated_at' => now(),
                'created_by' => $users['super_admin']->id,
            ]);

            $card = RateCard::query()->create([
                'name' => 'Tarifario TEST',
                'version' => 1,
                'supplier_id' => $supplier->id,
                'status' => RateCardStatus::Active,
                'effective_from' => $from,
                'mobilization_fee' => '0.00',
                'minimum_charge' => '0.00',
                'notes' => 'Datos de prueba E2E.',
                'created_by' => $users['operaciones']->id,
                'activated_at' => now(),
            ]);
            foreach ([['TEST-MOD-01', 'Instalación de módulo bajo TEST', 'pieza', '850.00'], ['TEST-ML-01', 'Instalación de cubierta TEST', 'metro', '400.00']] as $i => [$code, $description, $unit, $price]) {
                RateCardItem::query()->create([
                    'rate_card_id' => $card->id, 'code' => $code, 'description' => $description,
                    'unit' => $unit, 'unit_price' => $price, 'sort' => $i,
                ]);
            }

            $category = ProductCategory::query()->updateOrCreate(['slug' => 'cocinas-test'], [
                'name' => 'Cocinas TEST',
                'tagline' => 'Categoría de prueba E2E',
                'description' => 'Categoría de prueba para las pruebas de navegador.',
                'sort' => 0,
                'is_published' => true,
                'is_featured' => true,
            ]);
            Product::query()->create([
                'product_category_id' => $category->id,
                'name' => 'Cocina Integral TEST',
                'slug' => 'cocina-integral-test',
                'summary' => 'Producto de prueba para las pruebas de navegador.',
                'description' => '<p>Producto de prueba E2E.</p>',
                'benefits' => [['title' => 'Beneficio TEST', 'body' => 'Texto de prueba.']],
                'features' => [['title' => 'Característica TEST', 'body' => null]],
                'show_price' => false,
                'status' => PublicationStatus::Published,
                'published_at' => now(),
                'is_featured' => true,
                'sort' => 0,
            ]);
        });

        $this->info('Datos E2E creados. Contraseña de todos los usuarios: '.self::PASSWORD);
        foreach (self::USERS as $role => $email) {
            $this->line("  {$role}: {$email}");
        }

        return self::SUCCESS;
    }
}
