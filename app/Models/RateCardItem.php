<?php

namespace App\Models;

use Database\Factories\RateCardItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $rate_card_id
 * @property string $code
 * @property string $description
 * @property string $unit
 * @property string $unit_price
 * @property int $sort
 */
class RateCardItem extends Model
{
    /** @use HasFactory<RateCardItemFactory> */
    use HasFactory;

    protected $fillable = ['rate_card_id', 'code', 'description', 'unit', 'unit_price', 'sort'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'sort' => 'integer'];
    }

    /** @return BelongsTo<RateCard, $this> */
    public function rateCard(): BelongsTo
    {
        return $this->belongsTo(RateCard::class);
    }
}
