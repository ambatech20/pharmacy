<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerReturn extends Model
{
    use LogsActivity;

    protected $fillable = [
        'order_id',
        'issued_item_id',
        'purchase_batch_id',
        'branch_id',
        'user_id',
        'quantity',
        'unit_cost',
        'cost_amount',
        'refund_amount',
        'refund_method',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'cost_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function issuedItem(): BelongsTo
    {
        return $this->belongsTo(IssuedItem::class);
    }

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

    public function refundMethodLabel(): string
    {
        return Payment::METHODS[$this->refund_method] ?? ucfirst($this->refund_method);
    }
}
