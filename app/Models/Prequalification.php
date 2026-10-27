<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Enums\EmploymentType;
use App\Enums\IncomeRange;
use App\Enums\PrequalificationStatus;
use Database\Factories\PrequalificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Precalificación para pago a plazos. Revisión manual; documentos privados
 * como adjuntos con rol `document` (requieren prequalification.view +
 * sensitive.view para descargarse).
 *
 * @property int $id
 * @property string $uuid
 * @property string $folio
 * @property int $lead_id
 * @property int $customer_id
 * @property PrequalificationStatus $status
 * @property string|null $requested_amount
 * @property int|null $preferred_term_months
 * @property IncomeRange|null $income_range
 * @property EmploymentType|null $employment_type
 * @property string|null $applicant_notes
 * @property int|null $reviewer_id
 * @property Carbon|null $submitted_at
 * @property Carbon|null $reviewed_at
 * @property string|null $decision_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Lead $lead
 * @property-read Customer $customer
 * @property-read User|null $reviewer
 */
class Prequalification extends Model
{
    /** @use HasFactory<PrequalificationFactory> */
    use HasAttachments, HasFactory;

    public const DOCUMENT_ROLE = 'document';

    protected $fillable = [
        'uuid', 'folio', 'lead_id', 'customer_id', 'status', 'requested_amount', 'preferred_term_months',
        'income_range', 'employment_type', 'applicant_notes', 'reviewer_id', 'submitted_at',
        'reviewed_at', 'decision_notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (Prequalification $prequalification) {
            $prequalification->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => PrequalificationStatus::class,
            'income_range' => IncomeRange::class,
            'employment_type' => EmploymentType::class,
            'requested_amount' => 'decimal:2',
            'preferred_term_months' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
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

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
