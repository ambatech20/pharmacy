<?php

namespace App\Models;

use App\Models\Payment;
use App\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseBatch extends Model
{
    use LogsActivity;

    protected $fillable = [
        'drug_id', 'product_id', 'supplier_id', 'branch_id', 'batch_number',
        'quantity_received', 'quantity_remaining', 'cost_per_unit',
        'selling_price_per_unit', 'expiry_date', 'purchase_date',
        // Part A — how the purchase is being paid.
        'payment_status', 'amount_paid', 'due_date', 'payment_method',
        'payment_reference', 'check_number', 'check_date', 'check_bank',
        'purchase_ref', 'supplier_invoice_no',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'purchase_date' => 'date',
        'quantity_received' => 'integer',
        'quantity_remaining' => 'integer',
        'cost_per_unit' => 'decimal:4',
        'selling_price_per_unit' => 'decimal:4',
        'amount_paid' => 'decimal:2',
        'due_date' => 'date',
        'check_date' => 'date',
    ];

    public function getTotalCostAttribute(): float
    {
        return round((float) $this->quantity_received * (float) $this->cost_per_unit, 2);
    }

    public function getBalanceOwedAttribute(): float
    {
        return max(0, round($this->total_cost - (float) $this->amount_paid, 2));
    }

    /**
     * Plain-language label for the purchases list / reports. Never shows
     * accounting words to the owner — "Pay later" not "accounts payable".
     */
    public function payment_label(): string
    {
        if ($this->payment_method === Payment::PAY_LATER) {
            return 'Pay later';
        }

        return Payment::METHODS[$this->payment_method] ?? '—';
    }

    public function status_label(): string
    {
        return match ($this->payment_status) {
            'unpaid' => 'Not paid yet',
            'partial' => 'Partly paid',
            default => 'Paid',
        };
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function stockCards(): HasMany
    {
        return $this->hasMany(StockCard::class);
    }

    public function issuedItems(): HasMany
    {
        return $this->hasMany(IssuedItem::class);
    }

    public function getAvailableQuantityAttribute()
    {
        $in = $this->stockCards()->where('transaction_type', 'in')->sum('quantity');
        $out = $this->stockCards()->whereIn('transaction_type', ['out', 'transfer_out'])->sum('quantity');
        return $in - $out;
    }

    public function isExpired()
    {
        return $this->expiry_date !== null && Carbon::today()->gte($this->expiry_date);
    }

    public function isNearExpiry($days = 30)
    {
        return $this->expiry_date !== null && $this->expiry_date->diffInDays(Carbon::today(), false) <= $days && !$this->isExpired();
    }
}
