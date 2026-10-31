<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use LogsActivity;

    const METHODS = [
        'cash' => 'Cash',
        'telebirr' => 'Telebirr',
        'cbe_birr' => 'CBE Birr',
        'bank_transfer' => 'Bank Transfer',
        'card' => 'Card',
        'check' => 'Check',
    ];

    // "Pay later" is not a payment method with an account of its own — it is
    // a purchase-only choice meaning "no money moved yet", which posts to the
    // single liability account below instead of one of the asset accounts.
    const PAY_LATER = 'pay_later';
    const OWED_TO_SUPPLIERS_ACCOUNT = '2000';

    // Each method posts to its own asset account so till-closing (Phase 1)
    // can later compare counted cash against cash-paid orders only —
    // Telebirr/CBE/bank/card sit in separate books, not in the till.
    // Checks share the Bank account: a check drawn on our bank reduces the
    // same book as any other bank movement (no separate clearing account).
    const ACCOUNT_CODES = [
        'cash' => '1001',
        'telebirr' => '1010',
        'cbe_birr' => '1011',
        'bank_transfer' => '1012',
        'card' => '1013',
        'check' => '1012',
    ];

    protected $fillable = [
        'order_id',
        'payment_method',
        'amount',
        'transaction_reference',
        'verification_status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->payment_method] ?? ucfirst($this->payment_method);
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function isDigital(): bool
    {
        return in_array($this->payment_method, ['telebirr', 'cbe_birr', 'bank_transfer', 'card']);
    }

    public static function accountCodeFor(string $method): string
    {
        if (!isset(self::ACCOUNT_CODES[$method])) {
            throw new \InvalidArgumentException("Unknown payment method: {$method}");
        }

        return self::ACCOUNT_CODES[$method];
    }
}
