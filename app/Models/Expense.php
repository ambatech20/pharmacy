<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

/**
 * PART C — a single expense (rent, electricity, ...). Posting happens in
 * ExpenseController inside DB::transaction: expense account up, the paying
 * method's account down. Soft delete + reversal journal entry follows the
 * system's existing safe-delete pattern (see write-offs).
 */
class Expense extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'expense_date', 'expense_category_id', 'branch_id', 'amount', 'monthly_amount',
        'payment_method', 'payment_reference',
        'check_number', 'check_date', 'check_bank',
        'note', 'repeat_monthly', 'next_due_date', 'paid_months',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'check_date' => 'date',
        'next_due_date' => 'date',
        'amount' => 'decimal:2',
        'monthly_amount' => 'decimal:2',
        'repeat_monthly' => 'boolean',
        'paid_months' => 'integer',
    ];

    /** The "paid for" options offered on the expense form. */
    public const PAID_MONTH_OPTIONS = [1, 3, 6, 12];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Plain-language payment label for lists (never accounting terms).
     */
    public function payment_label(): string
    {
        return Payment::METHODS[$this->payment_method] ?? ucfirst($this->payment_method);
    }

    /**
     * The date this bill's reminder counts for: a monthly bill keeps its day
     * of the month and rolls forward to the current cycle, so a bill that was
     * never re-recorded reminds once a month instead of every day after its
     * first due date passed.
     */
    public function reminderDate(): ?\Carbon\Carbon
    {
        $due = $this->next_due_date?->copy();

        if (!$due || !$this->repeat_monthly) {
            return $due;
        }

        $today = \Carbon\Carbon::today();
        while ($due->lt($today)) {
            $due->addMonthNoOverflow();
        }

        return $due;
    }

    /**
     * What the reminder quotes. `amount` is the money that was paid (paying
     * three months of rent records 18,000), so a repeating bill shows its
     * monthly rate instead - the figure the next payment will be.
     */
    public function reminderAmount(): float
    {
        return (float) ($this->monthly_amount ?? $this->amount);
    }

    /**
     * A bill whose next due date has already passed. The reminder still rolls
     * forward to the current cycle, so this is the flag that says "you are
     * behind on this one" rather than "it is coming up".
     */
    public function isOverdue(): bool
    {
        return (bool) ($this->next_due_date
            && $this->repeat_monthly
            && $this->next_due_date->lt(\Carbon\Carbon::today()));
    }

    /**
     * The repeating bills that need attention within the window, soonest
     * first. Shared by the Alerts page, the dashboard and the sidebar badge so
     * the three can never disagree.
     */
    public static function dueBills($branchId = null, int $withinDays = 7): \Illuminate\Support\Collection
    {
        $today = \Carbon\Carbon::today();
        $windowEnd = $today->copy()->addDays(max(0, $withinDays));

        return static::query()
            ->with('category')
            ->where('repeat_monthly', true)
            ->whereNotNull('next_due_date')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->get()
            ->filter(function ($bill) use ($windowEnd) {
                // An overdue bill always shows. The monthly cycle rolls its
                // date forward to the current month, so without this a bill
                // that was missed would quietly roll into next month and
                // disappear from the list instead of showing as late.
                if ($bill->isOverdue()) {
                    return true;
                }

                return $bill->reminderDate()?->lte($windowEnd);
            })
            ->sortBy(fn ($bill) => $bill->reminderDate()->timestamp)
            ->values();
    }

    /**
     * Same list, cached for a minute. The dashboard and the sidebar badge ask
     * for it in the same request; without this they each run the query.
     */
    public static function dueBillsCached($branchId = null, int $withinDays = 7): \Illuminate\Support\Collection
    {
        $key = 'bills_due:' . ($branchId ?? 'all') . ':' . $withinDays;
        $cached = Cache::get($key);

        // Store plain attribute arrays, not Eloquent objects: the default
        // cache config does not unserialize classes, so a cached model comes
        // back as __PHP_Incomplete_Class on a real (file/database) cache.
        if (is_array($cached)) {
            return collect($cached)
                ->map(fn ($row) => (new static)->newFromBuilder($row))
                ->values();
        }

        if ($cached instanceof \Illuminate\Support\Collection) {
            return $cached;
        }

        if ($cached !== null) {
            Cache::forget($key);
        }

        $bills = static::dueBills($branchId, $withinDays);
        Cache::put($key, $bills->map->getAttributes()->all(), 60);

        return $bills;
    }
}
