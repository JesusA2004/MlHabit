<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Database\Factories\SupplierPayoutFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pago a proveedor (folio S) por una orden de instalación aprobada o una
 * orden de fabricación. Separación de funciones: quien solicita o ejecuta
 * la orden nunca autoriza (PayoutService).
 *
 * @property int $id
 * @property string $folio
 * @property int $supplier_id
 * @property int|null $installation_order_id
 * @property int|null $fabrication_order_id
 * @property string $amount
 * @property PayoutStatus $status
 * @property int|null $requested_by
 * @property int|null $authorized_by
 * @property Carbon|null $authorized_at
 * @property int|null $paid_by
 * @property Carbon|null $paid_at
 * @property string|null $payment_reference
 * @property string|null $rejection_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SupplierPayout extends Model
{
    /** @use HasFactory<SupplierPayoutFactory> */
    use HasFactory;

    protected $fillable = [
        'folio', 'supplier_id', 'installation_order_id', 'fabrication_order_id', 'amount', 'status',
        'requested_by', 'authorized_by', 'authorized_at', 'paid_by', 'paid_at', 'payment_reference', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'amount' => 'decimal:2',
            'authorized_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<InstallationOrder, $this> */
    public function installationOrder(): BelongsTo
    {
        return $this->belongsTo(InstallationOrder::class);
    }

    /** @return BelongsTo<FabricationOrder, $this> */
    public function fabricationOrder(): BelongsTo
    {
        return $this->belongsTo(FabricationOrder::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    /** @return BelongsTo<User, $this> */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
