<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCard extends Model
{
    protected $fillable = [
        'purchase_batch_id', 'branch_id', 'transaction_type',
        'quantity', 'unit_cost', 'total_amount',
        'reference_type', 'reference_id', 'transaction_date'
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function purchaseBatch(): BelongsTo { return $this->belongsTo(PurchaseBatch::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
}
