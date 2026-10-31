<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DrugCategory extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name', 'description',
    ];

    public function drugs(): HasMany
    {
        return $this->hasMany(Drug::class, 'category_id');
    }
}
