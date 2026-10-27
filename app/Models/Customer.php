<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Persona (prospecto o cliente). Puede tener cuenta de portal (user_id).
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $user_id
 * @property string $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $preferred_contact
 * @property string|null $notes
 * @property Carbon|null $privacy_accepted_at
 * @property-read string $full_name
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'user_id', 'first_name', 'last_name', 'email', 'phone',
        'preferred_contact', 'notes', 'privacy_accepted_at', 'created_by',
    ];

    protected $appends = ['full_name'];

    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            $customer->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return ['privacy_accepted_at' => 'datetime'];
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.($this->last_name ?? ''));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Address, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /** @return HasMany<Prequalification, $this> */
    public function prequalifications(): HasMany
    {
        return $this->hasMany(Prequalification::class);
    }
}
