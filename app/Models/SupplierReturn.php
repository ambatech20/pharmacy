<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierReturn extends Model
{
    use LogsActivity;

    protected $fillable = [
        'purchase_batch_id',
        'supplier_id',
        'branch_id',
        'user_id',
        'quantity',
        'unit_cost',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function purchaseBatch(): BelongsTo
    {
        return $this->belongsTo(PurchaseBatch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
