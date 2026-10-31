<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    use LogsActivity;

    const REASONS = [
        'damaged' => 'Damaged',
        'lost' => 'Lost / missing',
        'expired' => 'Expired',
        'other' => 'Other',
    ];

    protected $fillable = [
        'purchase_batch_id',
        'branch_id',
        'user_id',
        'quantity',
        'unit_cost',
        'total_amount',
        'reason',
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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst($this->reason);
    }
}
