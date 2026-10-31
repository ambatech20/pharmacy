<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name', 'address', 'contact_person', 'phone', 'email',
        'default_credit_days', // Part A — optional credit period for "Pay later"
    ];

    public function purchaseBatches(): HasMany
    {
        return $this->hasMany(PurchaseBatch::class);
    }
}
