<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'code', 'type', 'description', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function ledgers(): HasMany { return $this->hasMany(Ledger::class); }
}
