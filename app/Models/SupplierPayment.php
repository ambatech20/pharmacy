<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Part B — a payment made to a supplier (cash / bank / mobile money / check).
 * The amount is spread over the supplier's open purchases through
 * SupplierPaymentAllocation rows; balances themselves live on
 * purchase_batches.amount_paid so they can never drift.
 */
class SupplierPayment extends Model
{
    use LogsActivity;

    protected $fillable = [
        'payment_number', 'supplier_id', 'branch_id', 'payment_date',
        'amount', 'amount_applied', 'overpayment',
        'payment_method', 'payment_reference',
        'check_number', 'check_date', 'check_bank',
        'note', 'journal_entry_id',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'check_date' => 'date',
        'amount' => 'decimal:2',
        'amount_applied' => 'decimal:2',
        'overpayment' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    public function methodLabel(): string
    {
        return Payment::METHODS[$this->payment_method] ?? ucfirst($this->payment_method);
    }
}
