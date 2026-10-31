<?php

namespace App\Models;

use App\Models\Concerns\HasPackingLevels;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasPackingLevels, LogsActivity;

    protected $fillable = [
        'name', 'sku', 'category_id', 'brand', 'description',
        'purchase_price', 'selling_price', 'minimum_stock', 'active',
        'unit_of_measure', 'units_per_strip', 'strips_per_pack',
        'unit_top', 'unit_middle', 'unit_base',
        'top_holds', 'middle_holds', 'units_per_top',
        'sell_top', 'sell_middle', 'sell_base',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'minimum_stock' => 'decimal:2',
        'active' => 'boolean',
        'units_per_strip' => 'integer',
        'strips_per_pack' => 'integer',
        'top_holds' => 'integer',
        'middle_holds' => 'integer',
        'units_per_top' => 'integer',
        'sell_top' => 'boolean',
        'sell_middle' => 'boolean',
        'sell_base' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            // A product that has not been described yet is one piece sold
            // whole, which is what every product was before packing existed.
            if (! $product->unit_top) {
                $product->unit_top = 'piece';
                $product->unit_base = 'piece';
                $product->unit_middle = null;
                $product->top_holds = null;
                $product->middle_holds = null;
                $product->sell_top = true;
                $product->sell_middle = false;
                $product->sell_base = true;
            }

            $product->normalisePacking();
            $product->mirrorLegacyPackingColumns();

            // Derived either way, so the low-stock queries can multiply in SQL.
            $product->units_per_top = $product->unitsPerTop();
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function purchaseBatches(): HasMany
    {
        return $this->hasMany(PurchaseBatch::class);
    }

    public function getCurrentStockAttribute(): int
    {
        // Lists use withSum(..., as current_stock); read that instead of
        // querying once per product.
        if (array_key_exists('current_stock', $this->attributes)) {
            return (int) ($this->attributes['current_stock'] ?? 0);
        }

        // Same rule as drugs: expired stock is not sellable stock, so it does
        // not count towards the product's on-hand figure or its minimum.
        return (int) $this->purchaseBatches()
            ->where(fn ($query) => $query
                ->whereNull('expiry_date')
                ->orWhere('expiry_date', '>=', now()->toDateString()))
            ->sum('quantity_remaining');
    }

    public function getMinimumStockLevelAttribute(): mixed
    {
        return $this->minimum_stock;
    }

    /**
     * Products call the column minimum_stock rather than minimum_stock_level,
     * but the packing trait compares in the same base units for both.
     */
    public function minimumInBaseUnits(): float
    {
        return (float) $this->minimum_stock * (float) $this->unitsPerTop();
    }
}
