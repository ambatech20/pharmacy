<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PART C — expense category. Each maps to an expense account in the chart
 * of accounts (created by the seeder/migration if missing). The owner can
 * add, rename and deactivate categories; deleting is discouraged because
 * expenses keep referring to them (restrictOnDelete).
 */
class ExpenseCategory extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['name', 'account_code', 'active', 'allow_recurring'];

    protected $casts = [
        'active' => 'boolean',
        'allow_recurring' => 'boolean',
    ];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
