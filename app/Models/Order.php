<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use LogsActivity;

    protected $fillable = [
        'branch_id',
        'customer_id',
        'user_id',
        'total_amount',
        'status',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(IssuedItem::class);
    }

    // NEW: one order can have multiple payment rows (a normal sale has
    // one; a split payment has two or more, summing to total_amount).
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function amountPaid(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function customerReturns(): HasMany
    {
        return $this->hasMany(CustomerReturn::class);
    }

    public function hasVerifiedPayment(): bool
    {
        return $this->payments->contains(fn (Payment $p) => $p->isVerified());
    }

    public function hasDeclinedPayment(): bool
    {
        return $this->payments->contains(fn (Payment $p) => $p->verification_status === 'declined');
    }

    public function digitalPayments()
    {
        return $this->payments->filter(fn (Payment $p) => $p->isDigital());
    }

    /**
     * What the customer paid already includes VAT, so VAT is taken back out of
     * each amount (amount x rate / (100 + rate)) for the receipt. Per-line VAT
     * is rounded first and the totals are then made to add back up to the real
     * grand total, so the printed figures never miss a cent. Null when the
     * owner has not set a VAT rate — display only, it never changes what is
     * charged or posted to the accounts.
     */
    public function vatBreakdown(): ?array
    {
        $rate = Setting::vatRatePercent();

        if ($rate <= 0) {
            return null;
        }

        $items = [];
        $vatTotal = 0.0;

        foreach ($this->items as $item) {
            $gross = round((float) $item->total_amount, 2);
            $net = round($gross * 100 / (100 + $rate), 2);
            $vat = round($gross - $net, 2);
            $items[$item->id] = ['net' => $net, 'vat' => $vat];
            $vatTotal += $vat;
        }

        $total = round((float) $this->total_amount, 2);
        $vatTotal = round($vatTotal, 2);
        $decimals = fmod($rate, 1) > 0 ? 2 : 0;

        return [
            'rate' => $rate,
            'rate_label' => number_format($rate, $decimals),
            'divisor_label' => number_format(100 + $rate, $decimals),
            'net' => round($total - $vatTotal, 2),
            'vat' => $vatTotal,
            'total' => $total,
            'items' => $items,
        ];
    }
}
