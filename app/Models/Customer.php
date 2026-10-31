<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name', 'phone', 'email', 'address',
        'is_recurring', 'reminder_interval_days', 'condition_note',
    ];

    protected $casts = [
        'is_recurring' => 'boolean',
    ];

    public function issuedItems(): HasMany
    {
        return $this->hasMany(IssuedItem::class);
    }

    public function isDueForReminder(): bool
    {
        if (!$this->is_recurring || !$this->reminder_interval_days) {
            return false;
        }

        $lastVisit = $this->issuedItems()->max('issue_date');
        if (!$lastVisit) {
            return false;
        }

        return \Carbon\Carbon::parse($lastVisit)
            ->addDays($this->reminder_interval_days)
            ->isPast();
    }
}
