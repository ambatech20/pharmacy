<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One cashier's shift, from the drawer count that started it to the till
 * closing that ended it.
 */
class ShiftOpening extends Model
{
    protected $fillable = [
        'user_id', 'branch_id', 'opened_at', 'opening_cash',
        'closed_at', 'till_closing_id',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_cash' => 'decimal:2',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }

    public function tillClosing(): BelongsTo { return $this->belongsTo(TillClosing::class); }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * The shift this cashier is currently on, or null if they need to count
     * the drawer before they can carry on.
     */
    public static function currentFor(User $user, ?int $branchId = null): ?self
    {
        return static::query()
            ->where('user_id', $user->id)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->open()
            ->latest('opened_at')
            ->first();
    }
}
