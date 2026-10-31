<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TillClosing extends Model
{
    use LogsActivity;

    protected $fillable = [
        'user_id',
        'branch_id',
        'period_start',
        'closed_at',
        'expected_cash',
        'counted_cash',
        'variance',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'closed_at' => 'datetime',
        'expected_cash' => 'decimal:2',
        'counted_cash' => 'decimal:2',
        'variance' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
