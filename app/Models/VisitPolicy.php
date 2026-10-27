<?php

namespace App\Models;

use App\Enums\VisitCreditType;
use App\Support\Money;
use Database\Factories\VisitPolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Política comercial VERSIONADA de la visita técnica (tarifa, acreditación,
 * agenda y textos de alcance/cancelación). Una versión no se edita: se crea
 * otra y se activa. Solo una está activa; cada visita referencia la versión
 * con la que se reservó y además congela tarifa y crédito en su fila.
 *
 * @property int $id
 * @property int $version
 * @property string $fee_amount
 * @property string $currency
 * @property bool $is_creditable
 * @property VisitCreditType|null $credit_type
 * @property string|null $credit_value
 * @property int|null $credit_validity_days
 * @property string|null $credit_conditions
 * @property int $duration_minutes
 * @property int $buffer_minutes
 * @property int $hold_minutes
 * @property int $min_notice_hours
 * @property int $booking_window_days
 * @property string $scope
 * @property string $cancellation_policy
 * @property string $reschedule_policy
 * @property bool $is_active
 * @property Carbon|null $activated_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class VisitPolicy extends Model
{
    /** @use HasFactory<VisitPolicyFactory> */
    use HasFactory;

    protected $fillable = [
        'version', 'fee_amount', 'currency', 'is_creditable', 'credit_type', 'credit_value', 'credit_validity_days',
        'credit_conditions', 'duration_minutes', 'buffer_minutes', 'hold_minutes', 'min_notice_hours',
        'booking_window_days', 'scope', 'cancellation_policy', 'reschedule_policy', 'is_active', 'activated_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fee_amount' => 'decimal:2',
            'credit_value' => 'decimal:2',
            'credit_type' => VisitCreditType::class,
            'is_creditable' => 'boolean',
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
            'version' => 'integer',
            'credit_validity_days' => 'integer',
            'duration_minutes' => 'integer',
            'buffer_minutes' => 'integer',
            'hold_minutes' => 'integer',
            'min_notice_hours' => 'integer',
            'booking_window_days' => 'integer',
        ];
    }

    /** Versión activa (o null: el checkout público queda cerrado). */
    public static function current(): ?self
    {
        return self::query()->where('is_active', true)->orderByDesc('version')->first();
    }

    public static function nextVersion(): int
    {
        return ((int) self::query()->max('version')) + 1;
    }

    public function feeMoney(): Money
    {
        return Money::of($this->fee_amount);
    }

    /**
     * Crédito abonable para una tarifa: porcentaje de la tarifa o monto fijo
     * (nunca mayor que la tarifa). Cero si la política no es acreditable.
     */
    public function creditAmountFor(Money $fee): Money
    {
        if (! $this->is_creditable || $this->credit_type === null || $this->credit_value === null) {
            return Money::zero();
        }

        $value = Money::of($this->credit_value);

        $credit = match ($this->credit_type) {
            VisitCreditType::Percent => $fee->percent($value->min('100')->amount),
            VisitCreditType::Fixed => $value->min($fee),
        };

        return $credit->max('0');
    }

    /** ¿Ya se usó en alguna visita? (informativo: las versiones nunca se editan) */
    public function isInUse(): bool
    {
        return $this->visits()->exists();
    }

    /** @return HasMany<SiteVisit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(SiteVisit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
