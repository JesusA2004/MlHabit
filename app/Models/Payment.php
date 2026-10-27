<?php

namespace App\Models;

use App\Enums\PaymentConcept;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Support\Money;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Pago auditable (visita, anticipo, abono…). Nunca guarda PAN ni CVV.
 * El estado SOLO cambia mediante App\Payments\PaymentService.
 *
 * order_id: identificador interno único. Cada intento con tarjeta envía a
 * Openpay `{order_id}-{n}` (n = número de intento), así un rechazo nunca
 * bloquea el reintento con otra tarjeta (error 1006) y la conciliación por
 * order_id es determinista.
 *
 * @property int $id
 * @property string $uuid
 * @property string $folio
 * @property string $order_id
 * @property string $idempotency_key
 * @property int $customer_id
 * @property int|null $site_visit_id
 * @property int|null $project_id
 * @property int|null $contract_id
 * @property PaymentConcept $concept
 * @property string $description
 * @property PaymentMethod $method
 * @property PaymentProvider $provider
 * @property string $amount
 * @property string $currency
 * @property PaymentStatus $status
 * @property string|null $provider_transaction_id
 * @property string|null $provider_authorization
 * @property string|null $card_brand
 * @property string|null $card_last4
 * @property string|null $fee_amount
 * @property string|null $net_amount
 * @property string $refunded_amount
 * @property string|null $error_code
 * @property string|null $error_message
 * @property string|null $redirect_url
 * @property string|null $reference
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $paid_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $reconciled_at
 * @property int|null $recorded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer $customer
 * @property-read SiteVisit|null $siteVisit
 * @property-read Project|null $project
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid', 'folio', 'order_id', 'idempotency_key', 'customer_id', 'site_visit_id', 'project_id', 'contract_id',
        'concept', 'description', 'method', 'provider', 'amount', 'currency', 'status', 'provider_transaction_id',
        'provider_authorization', 'card_brand', 'card_last4', 'fee_amount', 'net_amount', 'refunded_amount',
        'error_code', 'error_message', 'redirect_url', 'reference', 'metadata', 'paid_at', 'failed_at',
        'reconciled_at', 'recorded_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            $payment->uuid ??= (string) Str::uuid();
            $payment->order_id ??= self::newOrderId();
        });
    }

    protected function casts(): array
    {
        return [
            'concept' => PaymentConcept::class,
            'method' => PaymentMethod::class,
            'provider' => PaymentProvider::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'metadata' => 'array',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'reconciled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** Identificador interno corto y no adivinable: MLH-3F9A1C2B7D4E. */
    public static function newOrderId(): string
    {
        return 'MLH-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 12));
    }

    /** order_id que se envía a Openpay para el intento n. */
    public function attemptOrderId(int $number): string
    {
        return $this->order_id.'-'.$number;
    }

    /**
     * Número de intento a partir de un order_id de Openpay (o null si no es nuestro).
     *
     * @return array{order_id: string, number: int}|null
     */
    public static function parseAttemptOrderId(?string $providerOrderId): ?array
    {
        if ($providerOrderId === null || ! preg_match('/^(MLH-[A-Z0-9]{12})-(\d{1,5})$/', $providerOrderId, $m)) {
            return null;
        }

        return ['order_id' => $m[1], 'number' => (int) $m[2]];
    }

    public function amountMoney(): Money
    {
        return Money::of($this->amount);
    }

    public function refundedMoney(): Money
    {
        return Money::of($this->refunded_amount);
    }

    /** Monto aún reembolsable (pagado − reembolsado). */
    public function refundableMoney(): Money
    {
        return $this->amountMoney()->minus($this->refundedMoney())->max('0');
    }

    /** ¿El usuario es el cliente dueño del pago? (protección IDOR) */
    public function belongsToUser(User $user): bool
    {
        return Customer::query()->whereKey($this->customer_id)->where('user_id', $user->id)->exists();
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /** @return BelongsTo<SiteVisit, $this> */
    public function siteVisit(): BelongsTo
    {
        return $this->belongsTo(SiteVisit::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return HasMany<PaymentAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class)->orderBy('number');
    }

    /** @return HasMany<PaymentEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class)->orderBy('created_at')->orderBy('id');
    }

    /** @return HasMany<Refund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->orderBy('id');
    }

    /** @return HasOne<VisitCredit, $this> */
    public function visitCredit(): HasOne
    {
        return $this->hasOne(VisitCredit::class);
    }
}
