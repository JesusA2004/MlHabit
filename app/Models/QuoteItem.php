<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Partida de cotización. Importes como string decimal (bcmath).
 *
 * @property int $id
 * @property int $quote_id
 * @property int $sort
 * @property string|null $code
 * @property string $description
 * @property string $quantity
 * @property string $unit
 * @property string $unit_price
 * @property string $discount_amount
 * @property string $line_total
 */
class QuoteItem extends Model
{
    protected $fillable = ['quote_id', 'sort', 'code', 'description', 'quantity', 'unit', 'unit_price', 'discount_amount', 'line_total'];

    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
