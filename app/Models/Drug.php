<?php

namespace App\Models;

use App\Models\Concerns\HasPackingLevels;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A medicine, and the packing it is bought and sold in.
 *
 * Stock is stored in exactly one unit - the smallest rung (unit_base) - in
 * purchase_batches.quantity_received / quantity_remaining. Everything else in
 * this class is about translating between that number and the way the shop
 * talks: "2 packs", "3 strips", "47.30 a capsule".
 *
 * The rungs are described per drug rather than assumed, because they genuinely
 * differ: a box of 100 strips of 10 tablets, a box of 20 sachets, a bottle of
 * syrup sold whole. See the migration that added these columns.
 */
class Drug extends Model
{
    use HasPackingLevels, LogsActivity;

    // Kept for older call-sites. New code should use packing levels by key.
    public const UNIT_TABLET = 'tablet';
    public const UNIT_STRIP = 'strip';
    public const UNIT_PACK = 'pack';

    /**
     * What kind of thing this row is. Medical supplies, supplements and infant
     * formula live on this side of the catalogue because they are tax-relieved;
     * the Products side is cosmetics and hygiene only.
     */
    public const KINDS = [
        'medicine' => 'Medicine',
        'medical_supply' => 'Medical supply',
        'supplement' => 'Supplement',
        'infant_formula' => 'Infant formula',
    ];

    protected $fillable = [
        'name', 'generic_name', 'strength', 'form', 'kind', 'sku',
        'category_id', 'unit_of_measure', 'units_per_strip',
        'strips_per_pack', 'minimum_stock_level',
        'unit_top', 'unit_middle', 'unit_base',
        'top_holds', 'middle_holds', 'units_per_top',
        'sell_top', 'sell_middle', 'sell_base',
    ];

    protected $casts = [
        'minimum_stock_level' => 'decimal:2',
        'units_per_strip' => 'integer',
        'strips_per_pack' => 'integer',
        'top_holds' => 'integer',
        'middle_holds' => 'integer',
        'units_per_top' => 'integer',
        'sell_top' => 'boolean',
        'sell_middle' => 'boolean',
        'sell_base' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Drug $drug) {
            if ($drug->unit_top) {
                $drug->normalisePacking();
                $drug->mirrorLegacyPackingColumns();
            }

            // Derived either way, so the low-stock queries can multiply in SQL.
            $drug->units_per_top = $drug->unitsPerTop();
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DrugCategory::class, 'category_id');
    }

    public function purchaseBatches(): HasMany
    {
        return $this->hasMany(PurchaseBatch::class);
    }

    public function getCurrentStockAttribute()
    {
        // Lists use withSum(..., as current_stock), which puts the answer on
        // the row in the same query. Use it instead of asking the database
        // once per row.
        if (array_key_exists('current_stock', $this->attributes)) {
            return (float) ($this->attributes['current_stock'] ?? 0);
        }

        // Expired stock is still on the shelf but cannot be sold, so it must
        // not make a drug look healthy. It stays visible on the Expired Stock
        // alert and in the inventory report, which read the batches directly.
        return $this->purchaseBatches()
            ->where(fn ($query) => $query
                ->whereNull('expiry_date')
                ->orWhere('expiry_date', '>=', now()->toDateString()))
            ->sum('quantity_remaining');
    }
}
