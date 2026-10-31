<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Part B — one slice of a supplier payment applied to one purchase line.
 */
class SupplierPaymentAllocation extends Model
{
    protected $fillable = [
        'supplier_payment_id', 'purchase_batch_id', 'purchase_ref', 'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class, 'supplier_payment_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PurchaseBatch::class, 'purchase_batch_id');
    }
}
