<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Enums\ContractStatus;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Contrato nacido de una cotización aceptada. Montos CONGELADOS: un cambio
 * posterior en la configuración comercial no los altera.
 * deposit_due_amount = deposit_amount − visit_credit_amount (mínimo 0);
 * contract_amount no cambia por el crédito de visita.
 *
 * @property int $id
 * @property string $uuid
 * @property string $folio
 * @property int $project_id
 * @property int $quote_id
 * @property ContractStatus $status
 * @property string $contract_amount
 * @property string $deposit_percent
 * @property string $deposit_amount
 * @property string $visit_credit_amount
 * @property string $deposit_due_amount
 * @property string $currency
 * @property string|null $terms_snapshot
 * @property Carbon|null $signed_at
 * @property Carbon|null $deposit_paid_at
 * @property Carbon|null $cancelled_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasAttachments, HasFactory;

    protected $fillable = [
        'uuid', 'folio', 'project_id', 'quote_id', 'status', 'contract_amount', 'deposit_percent', 'deposit_amount',
        'visit_credit_amount', 'deposit_due_amount', 'currency', 'terms_snapshot', 'signed_at', 'deposit_paid_at',
        'cancelled_at', 'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (Contract $contract) {
            $contract->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => ContractStatus::class,
            'signed_at' => 'datetime',
            'deposit_paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasOne<VisitCredit, $this> */
    public function visitCredit(): HasOne
    {
        return $this->hasOne(VisitCredit::class);
    }
}
