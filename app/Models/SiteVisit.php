<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Enums\BookingStatus;
use App\Enums\PaymentConcept;
use App\Enums\VisitStatus;
use App\Support\Money;
use Database\Factories\SiteVisitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Visita técnica a domicilio. Tarifa, acreditación y versión de política se
 * CONGELAN al reservar: cambiar la política después no altera la visita.
 * El horario vive en calendar_bookings (bookable = esta visita).
 *
 * @property int $id
 * @property string $uuid
 * @property string $folio
 * @property int|null $lead_id
 * @property int|null $project_id
 * @property int $customer_id
 * @property int $address_id
 * @property int $coverage_zone_id
 * @property int|null $technician_id
 * @property int $visit_policy_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property VisitStatus $status
 * @property string $fee_amount
 * @property string $currency
 * @property bool $is_creditable
 * @property string $credit_amount
 * @property Carbon|null $hold_expires_at
 * @property string $idempotency_key
 * @property string|null $access_notes
 * @property string|null $customer_notes
 * @property string|null $internal_notes
 * @property Carbon|null $paid_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property string|null $attention_reason
 * @property Carbon|null $attention_at
 * @property int|null $rescheduled_from_id
 * @property Carbon|null $reminder_sent_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer $customer
 * @property-read Address $address
 * @property-read CoverageZone $zone
 * @property-read User|null $technician
 * @property-read VisitPolicy $policy
 * @property-read Lead|null $lead
 * @property-read Project|null $project
 * @property-read Payment|null $payment
 * @property-read VisitCredit|null $credit
 */
class SiteVisit extends Model
{
    /** @use HasFactory<SiteVisitFactory> */
    use HasAttachments, HasFactory;

    protected $fillable = [
        'uuid', 'folio', 'lead_id', 'project_id', 'customer_id', 'address_id', 'coverage_zone_id', 'technician_id',
        'visit_policy_id', 'starts_at', 'ends_at', 'status', 'fee_amount', 'currency', 'is_creditable',
        'credit_amount', 'hold_expires_at', 'idempotency_key', 'access_notes', 'customer_notes', 'internal_notes',
        'paid_at', 'started_at', 'completed_at', 'cancelled_at', 'cancel_reason', 'attention_reason', 'attention_at',
        'rescheduled_from_id', 'reminder_sent_at', 'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (SiteVisit $visit) {
            $visit->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => VisitStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'fee_amount' => 'decimal:2',
            'credit_amount' => 'decimal:2',
            'is_creditable' => 'boolean',
            'hold_expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'attention_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function feeMoney(): Money
    {
        return Money::of($this->fee_amount);
    }

    public function creditMoney(): Money
    {
        return Money::of($this->credit_amount);
    }

    public function holdIsActive(): bool
    {
        return $this->status->isAwaitingPayment() && $this->hold_expires_at !== null && $this->hold_expires_at->isFuture();
    }

    /** Reserva de agenda vigente (apartada o confirmada) más reciente. */
    public function activeBooking(): ?CalendarBooking
    {
        return $this->bookings()->where('status', '!=', BookingStatus::Released->value)->latest('id')->first();
    }

    /** Última reserva (aunque se haya liberado): para recuperar el horario si el pago llegó tarde. */
    public function latestBooking(): ?CalendarBooking
    {
        return $this->bookings()->latest('id')->first();
    }

    /** ¿El usuario es el cliente dueño de la visita? (protección IDOR) */
    public function belongsToUser(User $user): bool
    {
        return Customer::query()->whereKey($this->customer_id)->where('user_id', $user->id)->exists();
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /** @return BelongsTo<Address, $this> */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    /** @return BelongsTo<CoverageZone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(CoverageZone::class, 'coverage_zone_id');
    }

    /** @return BelongsTo<User, $this> */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /** @return BelongsTo<VisitPolicy, $this> */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(VisitPolicy::class, 'visit_policy_id');
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Pago de la tarifa de la visita (el más reciente).
     *
     * @return HasOne<Payment, $this>
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->ofMany(['id' => 'max'], fn ($q) => $q->where('concept', PaymentConcept::VisitFee->value));
    }

    /** @return HasOne<VisitCredit, $this> */
    public function credit(): HasOne
    {
        return $this->hasOne(VisitCredit::class);
    }

    /** @return MorphMany<CalendarBooking, $this> */
    public function bookings(): MorphMany
    {
        return $this->morphMany(CalendarBooking::class, 'bookable');
    }
}
