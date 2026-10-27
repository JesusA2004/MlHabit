<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Enums\BudgetRange;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\LostReason;
use App\Enums\PaymentPreference;
use App\Enums\Timeframe;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Prospecto comercial. El estado SOLO cambia con
 * App\Services\Crm\LeadWorkflow::transition().
 * Fotos privadas del espacio: adjuntos con rol `photo`.
 *
 * @property int $id
 * @property string $uuid
 * @property string $folio
 * @property int $customer_id
 * @property LeadStatus $status
 * @property int|null $assigned_to
 * @property string|null $postal_code
 * @property string|null $city
 * @property string|null $state
 * @property int|null $coverage_zone_id
 * @property BudgetRange|null $budget_range
 * @property PaymentPreference|null $payment_preference
 * @property Timeframe|null $timeframe
 * @property string|null $measurements_note
 * @property string|null $comments
 * @property LeadSource $source
 * @property string|null $form
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string|null $utm_term
 * @property string|null $utm_content
 * @property string|null $referrer
 * @property string|null $landing_url
 * @property string|null $device_type
 * @property Carbon|null $first_contacted_at
 * @property Carbon|null $next_follow_up_at
 * @property LostReason|null $lost_reason
 * @property string|null $lost_notes
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer $customer
 * @property-read User|null $assignee
 * @property-read CoverageZone|null $coverageZone
 */
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasAttachments, HasFactory, SoftDeletes;

    public const PHOTO_ROLE = 'photo';

    protected $fillable = [
        'uuid', 'folio', 'customer_id', 'status', 'assigned_to', 'postal_code', 'city', 'state',
        'coverage_zone_id', 'budget_range', 'payment_preference', 'timeframe', 'measurements_note',
        'comments', 'source', 'form', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term',
        'utm_content', 'referrer', 'landing_url', 'device_type', 'first_contacted_at',
        'next_follow_up_at', 'lost_reason', 'lost_notes', 'closed_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Lead $lead) {
            $lead->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'source' => LeadSource::class,
            'budget_range' => BudgetRange::class,
            'payment_preference' => PaymentPreference::class,
            'timeframe' => Timeframe::class,
            'lost_reason' => LostReason::class,
            'first_contacted_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<CoverageZone, $this> */
    public function coverageZone(): BelongsTo
    {
        return $this->belongsTo(CoverageZone::class);
    }

    /** @return HasMany<LeadActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /** @return HasMany<Consultation, $this> */
    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    /** @return HasMany<Prequalification, $this> */
    public function prequalifications(): HasMany
    {
        return $this->hasMany(Prequalification::class);
    }

    /** @return HasOne<Project, $this> */
    public function project(): HasOne
    {
        return $this->hasOne(Project::class);
    }

    /**
     * IDs de categorías de producto de interés (tabla lead_product_category).
     * Se consulta por tabla para no acoplarse al modelo del catálogo.
     *
     * @return list<int>
     */
    public function categoryIds(): array
    {
        return array_values(DB::table('lead_product_category')->where('lead_id', $this->id)->pluck('product_category_id')->map(fn ($id) => (int) $id)->all());
    }

    /**
     * @return list<array{id: int, name: string, slug: string}>
     */
    public function categorySummaries(): array
    {
        return array_values(DB::table('lead_product_category')
            ->join('product_categories', 'product_categories.id', '=', 'lead_product_category.product_category_id')
            ->where('lead_product_category.lead_id', $this->id)
            ->orderBy('product_categories.sort')
            ->get(['product_categories.id', 'product_categories.name', 'product_categories.slug'])
            ->map(fn ($row) => ['id' => (int) $row->id, 'name' => (string) $row->name, 'slug' => (string) $row->slug])
            ->all());
    }

    /** @param list<int> $categoryIds */
    public function syncCategories(array $categoryIds): void
    {
        DB::table('lead_product_category')->where('lead_id', $this->id)->delete();
        $rows = array_map(fn (int $id) => ['lead_id' => $this->id, 'product_category_id' => $id], array_values(array_unique($categoryIds)));
        if ($rows !== []) {
            DB::table('lead_product_category')->insert($rows);
        }
    }

    /**
     * @param  Builder<Lead>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', array_map(fn (LeadStatus $s) => $s->value, LeadStatus::open()));
    }

    /**
     * Prospectos nuevos sin primer contacto después de N horas.
     *
     * @param  Builder<Lead>  $query
     */
    public function scopeAwaitingContact(Builder $query, int $hours): void
    {
        $query->where('status', LeadStatus::New->value)
            ->whereNull('first_contacted_at')
            ->where('created_at', '<=', now()->subHours($hours));
    }

    /**
     * @param  Builder<Lead>  $query
     */
    public function scopeInCategory(Builder $query, int $categoryId): void
    {
        $query->whereExists(fn ($q) => $q->select(DB::raw(1))
            ->from('lead_product_category')
            ->whereColumn('lead_product_category.lead_id', 'leads.id')
            ->where('lead_product_category.product_category_id', $categoryId));
    }
}
