<?php

namespace Tests\Feature\Operations;

use App\Enums\InstallationStatus;
use App\Enums\ProjectStatus;
use App\Enums\QualityCriterion;
use App\Enums\TechnicianStatus;
use App\Enums\UserRole;
use App\Models\InstallationOrder;
use App\Models\Media;
use App\Models\Project;
use App\Models\RateCard;
use App\Models\Supplier;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Services\Operations\InstallationService;
use Database\Factories\OperationsFixtures;

/** Datos de apoyo para las pruebas de operaciones (solo factories y filas mínimas). */
final class OpsSetup
{
    public static function staff(UserRole $role = UserRole::Operations): User
    {
        return User::factory()->role($role)->create()->fresh();
    }

    public static function installer(): User
    {
        $user = User::factory()->role(UserRole::Technician)->create();
        TechnicianProfile::factory()->create([
            'user_id' => $user->id,
            'does_installations' => true,
            'status' => TechnicianStatus::Active,
        ]);

        return $user->fresh();
    }

    /**
     * Tarifario vigente con dos módulos (importes de prueba).
     */
    public static function activeRateCard(string $mobilization = '500.00', string $minimum = '0.00', ?Supplier $supplier = null): RateCard
    {
        return RateCard::factory()->active()->withItems([
            ['code' => 'ALTO-60', 'unit_price' => '850.00'],
            ['code' => 'BAJO-80', 'unit_price' => '1200.50', 'unit' => 'ml'],
        ])->create([
            'mobilization_fee' => $mobilization,
            'minimum_charge' => $minimum,
            'supplier_id' => $supplier?->id,
        ]);
    }

    /**
     * Crea una orden de instalación vía el servicio (precios congelados).
     *
     * @param  array<string, string>  $quantities  code => cantidad
     */
    public static function order(
        ?Project $project = null,
        ?RateCard $card = null,
        ?User $lead = null,
        array $quantities = ['ALTO-60' => '2', 'BAJO-80' => '1.5'],
        ?User $actor = null,
    ): InstallationOrder {
        $project ??= OperationsFixtures::project(ProjectStatus::ReadyToInstall);
        $card ??= self::activeRateCard();
        $supplier = Supplier::factory()->installer()->create();
        $lead ??= self::installer();
        $actor ??= self::staff();

        $items = [];
        foreach ($quantities as $code => $qty) {
            $items[] = ['rate_card_item_id' => $card->items()->where('code', $code)->value('id'), 'quantity' => $qty];
        }

        return app(InstallationService::class)->create($project, [
            'supplier_id' => $supplier->id,
            'rate_card_id' => $card->id,
            'lead_installer_id' => $lead->id,
            'items' => $items,
        ], $actor);
    }

    /** Lleva una orden hasta un estado dado sin pasar por HTTP. */
    public static function forceStatus(InstallationOrder $order, InstallationStatus $status, ?ProjectStatus $project = null): InstallationOrder
    {
        $order->update(['status' => $status]);
        if ($project !== null) {
            $order->project->update(['status' => $project]);
        }

        return $order->refresh();
    }

    public static function evidence(User $owner, string $type = 'image', int $size = 2_000_000): Media
    {
        return Media::factory()->private()->create([
            'uploaded_by' => $owner->id,
            'type' => $type,
            'mime_type' => $type === 'video' ? 'video/mp4' : 'image/jpeg',
            'size' => $size,
        ]);
    }

    /**
     * Checklist completo con el mismo resultado.
     *
     * @return list<array{criterion: string, result: string, notes: string|null}>
     */
    public static function checklist(string $result = 'pass'): array
    {
        return array_map(fn (QualityCriterion $c) => [
            'criterion' => $c->value,
            'result' => $result,
            'notes' => $result === 'fail' ? 'Detalle observado' : null,
        ], QualityCriterion::cases());
    }
}
