<?php

namespace App\Models;

use App\Enums\VisitCreditStatus;
use App\Support\Money;
use Database\Factories\VisitCreditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Crédito abonable al anticipo, nacido de una visita pagada con política
 * acreditable. Lo APLICA el módulo projects al crear el contrato (lock +
 * status available + vigencia; contract_id UNIQUE). visits lo crea y lo
 * anula ante un reembolso.
 *
 * @property int $id
 * @property int $site_visit_id
 * @property int $payment_id
 * @property string $amount
 * @property VisitCreditStatus $status
 * @property Carbon|null $expires_on
 * @property int|null $contract_id
 * @property Carbon|null $applied_at
 * @property int|null $applied_by
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SiteVisit $siteVisit
 * @property-read Payment $payment
 */
class VisitCredit extends Model
{
    /** @use HasFactory<VisitCreditFactory> */
    use HasFactory;

    protected $fillable = [
        'site_visit_id', 'payment_id', 'amount', 'status', 'expires_on', 'contract_id',
        'applied_at', 'applied_by', 'voided_at', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => VisitCreditStatus::class,
            'amount' => 'decimal:2',
            'expires_on' => 'date',
            'applied_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function amountMoney(): Money
    {
        return Money::of($this->amount);
    }

    /** ¿Disponible y vigente hoy? */
    public function isUsable(): bool
    {
        return $this->status === VisitCreditStatus::Available
            && ($this->expires_on === null || $this->expires_on->endOfDay()->isFuture());
    }

    /** @return BelongsTo<SiteVisit, $this> */
    public function siteVisit(): BelongsTo
    {
        return $this->belongsTo(SiteVisit::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
