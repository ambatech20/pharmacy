<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransfer extends Model
{
    use LogsActivity;

    protected $fillable = [
        'from_branch_id', 'to_branch_id', 'purchase_batch_id',
        'quantity', 'unit_cost', 'total_amount', 'transfer_date', 'status'
    ];

    protected $casts = [
        'transfer_date' => 'datetime',
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function fromBranch(): BelongsTo { return $this->belongsTo(Branch::class, 'from_branch_id'); }
    public function toBranch(): BelongsTo { return $this->belongsTo(Branch::class, 'to_branch_id'); }
    public function purchaseBatch(): BelongsTo { return $this->belongsTo(PurchaseBatch::class); }
}
