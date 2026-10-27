<?php

namespace Database\Factories;

use App\Enums\VisitCreditType;
use App\Models\VisitPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Política de visita para PRUEBAS. La tarifa real es una decisión
 * comercial pendiente: nunca se siembra una política activa.
 *
 * @extends Factory<VisitPolicy>
 */
class VisitPolicyFactory extends Factory
{
    protected $model = VisitPolicy::class;

    public function definition(): array
    {
        return [
            'version' => fn () => VisitPolicy::nextVersion(),
            'fee_amount' => '1000.00',
            'currency' => 'MXN',
            'is_creditable' => false,
            'credit_type' => null,
            'credit_value' => null,
            'credit_validity_days' => null,
            'credit_conditions' => null,
            'duration_minutes' => 90,
            'buffer_minutes' => 30,
            'hold_minutes' => 15,
            'min_notice_hours' => 0,
            'booking_window_days' => 30,
            'scope' => 'Medición del espacio, revisión de instalaciones y recomendaciones técnicas.',
            'cancellation_policy' => 'Cancelación sin costo con 24 horas de anticipación.',
            'reschedule_policy' => 'Puedes reprogramar una vez sin costo con 24 horas de anticipación.',
            'is_active' => false,
            'activated_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true, 'activated_at' => now()]);
    }

    /** Acreditable: porcentaje de la tarifa con vigencia en días. */
    public function creditablePercent(string $percent = '100', int $days = 60): static
    {
        return $this->state(fn () => [
            'is_creditable' => true,
            'credit_type' => VisitCreditType::Percent,
            'credit_value' => $percent,
            'credit_validity_days' => $days,
        ]);
    }

    public function creditableFixed(string $amount, int $days = 60): static
    {
        return $this->state(fn () => [
            'is_creditable' => true,
            'credit_type' => VisitCreditType::Fixed,
            'credit_value' => $amount,
            'credit_validity_days' => $days,
        ]);
    }
}
