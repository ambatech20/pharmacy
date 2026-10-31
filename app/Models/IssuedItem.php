<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IssuedItem extends Model
{
    use LogsActivity;

    protected $fillable = [
        'order_id',
        'purchase_batch_id', 'product_id', 'customer_id', 'branch_id',
        'quantity_issued', 'selling_price_per_unit', 'unit_discount', 'total_amount', 'issue_date'
    ];

    protected $casts = [
        'issue_date' => 'datetime',
        'quantity_issued' => 'integer',
        'selling_price_per_unit' => 'decimal:2',
        'unit_discount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function purchaseBatch(): BelongsTo { return $this->belongsTo(PurchaseBatch::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }

    public function customerReturns(): HasMany
    {
        return $this->hasMany(CustomerReturn::class);
    }

    public function quantityReturned(): int
    {
        return (int) $this->customerReturns()->sum('quantity');
    }

    public function quantityReturnable(): int
    {
        return max(0, (int) $this->quantity_issued - $this->quantityReturned());
    }
}
