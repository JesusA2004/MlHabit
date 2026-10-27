<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Cotización versionada de un proyecto. Totales siempre calculados en
 * backend (App\Services\Projects\QuoteCalculator, bcmath). IVA y modo
 * "precios con IVA" se congelan en la cotización al crearla.
 *
 * @property int $id
 * @property string $uuid
 * @property string $folio
 * @property int $project_id
 * @property int|null $design_version_id
 * @property int $version
 * @property QuoteStatus $status
 * @property string $title
 * @property Carbon|null $valid_until
 * @property string $currency
 * @property string $subtotal
 * @property string $discount_total
 * @property string $tax_rate
 * @property string $tax_total
 * @property string $total
 * @property bool $prices_include_tax
 * @property string|null $inclusions
 * @property string|null $exclusions
 * @property string|null $notes
 * @property string|null $terms
 * @property int|null $discount_authorized_by
 * @property Carbon|null $sent_at
 * @property Carbon|null $accepted_at
 * @property int|null $accepted_by
 * @property string|null $accepted_ip
 * @property Carbon|null $rejected_at
 * @property string|null $rejection_reason
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid', 'folio', 'project_id', 'design_version_id', 'version', 'status', 'title', 'valid_until', 'currency',
        'subtotal', 'discount_total', 'tax_rate', 'tax_total', 'total', 'prices_include_tax',
        'inclusions', 'exclusions', 'notes', 'terms', 'discount_authorized_by', 'sent_at',
        'accepted_at', 'accepted_by', 'accepted_ip', 'rejected_at', 'rejection_reason', 'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (Quote $quote) {
            $quote->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'version' => 'integer',
            'valid_until' => 'date',
            'prices_include_tax' => 'boolean',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** ¿Ya pasó su vigencia? (vale todo el día de `valid_until`). */
    public function isPastValidity(): bool
    {
        return $this->valid_until !== null && $this->valid_until->copy()->endOfDay()->isPast();
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<DesignVersion, $this> */
    public function designVersion(): BelongsTo
    {
        return $this->belongsTo(DesignVersion::class);
    }

    /** @return HasMany<QuoteItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort')->orderBy('id');
    }

    /** @return HasOne<Contract, $this> */
    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function discountAuthorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discount_authorized_by');
    }

    /** @return BelongsTo<User, $this> */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }
}
