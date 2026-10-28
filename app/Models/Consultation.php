<?php

namespace App\Models;

use App\Enums\ConsultationChannel;
use App\Enums\ConsultationOutcome;
use App\Enums\ConsultationStatus;
use Database\Factories\ConsultationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Asesoría remota con un asesor. Ocupa la agenda `consultation` mediante
 * un CalendarBooking (bookable = esta asesoría).
 *
 * @property int $id
 * @property string $uuid
 * @property string $folio
 * @property int $lead_id
 * @property int $advisor_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property ConsultationStatus $status
 * @property ConsultationChannel $channel
 * @property string|null $meeting_provider
 * @property string|null $meeting_url
 * @property string $price
 * @property int|null $payment_id
 * @property Carbon|null $hold_expires_at
 * @property Carbon|null $paid_at
 * @property string|null $attention_reason
 * @property string|null $customer_notes
 * @property string|null $internal_notes
 * @property ConsultationOutcome|null $outcome
 * @property string|null $outcome_notes
 * @property string|null $cancel_reason
 * @property int|null $rescheduled_from_id
 * @property Carbon|null $reminder_sent_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Lead $lead
 * @property-read User $advisor
 * @property-read CalendarBooking|null $booking
 * @property-read Payment|null $payment
 */
class Consultation extends Model
{
    /** @use HasFactory<ConsultationFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid', 'folio', 'lead_id', 'advisor_id', 'starts_at', 'ends_at', 'status', 'channel',
        'meeting_provider', 'meeting_url', 'price', 'customer_notes', 'internal_notes', 'outcome',
        'outcome_notes', 'cancel_reason', 'rescheduled_from_id', 'reminder_sent_at', 'completed_at',
        'cancelled_at', 'created_by', 'payment_id', 'hold_expires_at', 'paid_at', 'attention_reason',
    ];

    protected static function booted(): void
    {
        static::creating(function (Consultation $consultation) {
            $consultation->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => ConsultationStatus::class,
            'channel' => ConsultationChannel::class,
            'outcome' => ConsultationOutcome::class,
            'price' => 'decimal:2',
            'reminder_sent_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'hold_expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    /** @return BelongsTo<Consultation, $this> */
    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(Consultation::class, 'rescheduled_from_id');
    }

    /** @return HasOne<Consultation, $this> */
    public function rescheduledTo(): HasOne
    {
        return $this->hasOne(Consultation::class, 'rescheduled_from_id');
    }

    /** @return MorphOne<CalendarBooking, $this> */
    public function booking(): MorphOne
    {
        return $this->morphOne(CalendarBooking::class, 'bookable')->latestOfMany();
    }

    /** @return BelongsTo<Payment, $this> Pago vigente (se conserva al reprogramar). */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return HasMany<Payment, $this> Pagos creados para esta asesoría (payments.consultation_id). */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** ¿El apartado del horario sigue vigente para pagar? */
    public function holdIsActive(): bool
    {
        return $this->hold_expires_at !== null && $this->hold_expires_at->isFuture();
    }

    /**
     * @param  Builder<Consultation>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', array_map(fn (ConsultationStatus $s) => $s->value, ConsultationStatus::active()));
    }
}
