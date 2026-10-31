<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntry extends Model
{
    use LogsActivity;

    protected $fillable = [
        'voucher_number', 'date', 'description', 'reference_type',
        'reference_id', 'branch_id', 'total_debit', 'total_credit'
    ];

    protected $casts = [
        'date' => 'date',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
    ];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function ledgers(): HasMany { return $this->hasMany(Ledger::class); }
}
