<?php

namespace App\Services;

use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\IssuedItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Move medical supplies, vitamins and infant formula from the Products side to
 * the Drugs side.
 *
 * They are tax-relieved the same way medicines are, so they belong in the drug
 * catalogue. The move keeps the thing that matters: the batches (and therefore
 * the stock, cost and expiry), the sales history, and the SKU. It does not
 * invent a purchase or a price - the batches already carry those.
 *
 * `preview()` is read-only. `apply()` is the only method that writes, and it
 * merges into an existing drug of the same name rather than duplicating it.
 */
class CatalogueMoveService
{
    /** Product categories whose whole contents move. */
    public const MOVE_CATEGORIES = [
        'Medical Supplies',
        'Vitamins and Supplements',
        'Infant Formula',
    ];

    /** Antiseptics and alcohol filed under Hygiene / Personal Care by mistake. */
    public const MOVE_SKUS = [
        'HY-ALC-130', 'HY-HP2-130', 'HY-DET-250', 'HY-SAV-250', 'PC-SAN-500',
    ];

    /** Name fallbacks for rows whose SKU was typed differently on the live DB. */
    private const MOVE_NAMES = [
        'Dettol Antiseptic%',
        'Savlon Antiseptic%',
        'Hand Sanitizer%',
        'Denatured Alcohol%',
        'Hydrogen Peroxide%',
    ];

    /** @return Collection<int, Product> */
    public function candidates(): Collection
    {
        return Product::with('category')
            ->where(function ($query) {
                $query->whereHas('category', fn ($category) => $category
                    ->whereIn('name', self::MOVE_CATEGORIES))
                    ->orWhereIn('sku', self::MOVE_SKUS);

                foreach (self::MOVE_NAMES as $name) {
                    $query->orWhere('name', 'like', $name);
                }
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Read-only list for approval. Nothing here writes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function preview(): array
    {
        return $this->candidates()->map(function (Product $product) {
            $batches = $product->purchaseBatches();
            $kind = $this->kindFor($product);
            $packing = $this->packingFor($product, $kind);
            $factor = max(1, (int) ($packing['top_holds'] ?? 1));

            $clash = Drug::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($product->name))])->first();
            if (! $clash && $product->sku) {
                $clash = Drug::where('sku', $product->sku)->first();
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'category' => $product->category->name ?? '-',
                'kind' => $kind,
                'packing' => $this->describePacking($packing),
                'sells' => $this->sellDescription($packing, $kind),
                'minimum' => $this->minimumFor($product, $factor),
                'stock' => (int) (clone $batches)->sum('quantity_remaining'),
                'expired' => (int) (clone $batches)
                    ->whereNotNull('expiry_date')
                    ->where('expiry_date', '<', now()->toDateString())
                    ->sum('quantity_remaining'),
                'batches' => (clone $batches)->count(),
                'sales' => IssuedItem::where('product_id', $product->id)->count(),
                'clash' => $clash?->name,
            ];
        })->all();
    }

    /**
     * Do the move. Idempotent: a product already gone is simply not a candidate.
     *
     * @return array{moved:int, merged:int, skipped:int, warnings:array<int, string>}
     */
    public function apply(): array
    {
        $moved = 0;
        $merged = 0;
        $skipped = 0;
        $warnings = [];

        DB::transaction(function () use (&$moved, &$merged, &$skipped, &$warnings) {
            foreach ($this->candidates() as $product) {
                $name = trim($product->name);
                $kind = $this->kindFor($product);
                $packing = $this->packingFor($product, $kind);
                $factor = max(1, (int) ($packing['top_holds'] ?? 1));

                $drug = Drug::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->orderBy('id')->first();
                if (! $drug && $product->sku) {
                    $drug = Drug::where('sku', $product->sku)->first();
                }

                if ($drug) {
                    // Stock is stored in the base unit. Merging two records
                    // whose base units differ would silently add bottles to
                    // tablets, so leave that pair for a person to resolve.
                    if (strcasecmp($drug->baseUnitLabel(), $packing['base']) !== 0) {
                        $warnings[] = $name . ': existing drug counts in '
                            . $drug->baseUnitLabel() . ', this product in '
                            . $packing['base'] . ' - left alone.';
                        $skipped++;
                        continue;
                    }

                    if (! $drug->sku && $product->sku) {
                        $drug->sku = $product->sku;
                        $drug->save();
                    }

                    $merged++;
                } else {
                    $category = DrugCategory::firstOrCreate(
                        ['name' => $this->categoryNameFor($kind)],
                        ['description' => $this->categoryDescriptionFor($kind)]
                    );

                    $drug = Drug::create([
                        'name' => $name,
                        'generic_name' => null,
                        'strength' => null,
                        'form' => $this->formFor($product, $kind),
                        'kind' => $kind,
                        'sku' => $product->sku,
                        'category_id' => $category->id,
                        'unit_top' => $packing['top'],
                        'top_holds' => $packing['top_holds'] ?? null,
                        'unit_middle' => null,
                        'middle_holds' => null,
                        'unit_base' => $packing['base'],
                        'sell_top' => true,
                        'sell_middle' => false,
                        'sell_base' => $this->sellBaseFor($kind, $packing),
                        'minimum_stock_level' => $this->minimumFor($product, $factor),
                    ]);

                    $moved++;
                }

                // The batch keeps its stock, cost, selling price and expiry;
                // only the side of the catalogue it belongs to changes.
                PurchaseBatch::where('product_id', $product->id)->update([
                    'drug_id' => $drug->id,
                    'product_id' => null,
                ]);

                // Sales history points at the batch, and the batch now points
                // at the drug, so the history follows automatically. The
                // product reference is cleared so the product row can go.
                IssuedItem::where('product_id', $product->id)->update([
                    'product_id' => null,
                ]);

                $product->delete();
            }

            // An empty "Medical Supplies" / "Vitamins" product category would
            // otherwise linger on the Products screen forever.
            foreach (self::MOVE_CATEGORIES as $name) {
                $category = ProductCategory::where('name', $name)->first();

                if ($category && ! Product::where('category_id', $category->id)->exists()) {
                    $category->delete();
                }
            }
        });

        return compact('moved', 'merged', 'skipped', 'warnings');
    }

    private function kindFor(Product $product): string
    {
        $category = $product->category->name ?? '';

        if ($category === 'Infant Formula' || stripos($product->name, 'formula') !== false) {
            return 'infant_formula';
        }

        if ($category === 'Vitamins and Supplements' || str_starts_with((string) $product->sku, 'VS-')) {
            return 'supplement';
        }

        return 'medical_supply';
    }

    private function categoryNameFor(string $kind): string
    {
        return match ($kind) {
            'supplement' => 'Vitamins and Supplements',
            'infant_formula' => 'Infant Formula',
            default => 'Medical Supplies',
        };
    }

    private function categoryDescriptionFor(string $kind): string
    {
        return match ($kind) {
            'supplement' => 'Vitamins, minerals and supplements, including syrups and softgels.',
            'infant_formula' => 'Infant formula and follow-on formula.',
            default => 'Consumables, dressings, devices and antiseptics used in care.',
        };
    }

    private function formFor(Product $product, string $kind): string
    {
        if ($kind === 'supplement') {
            return stripos($product->name, 'syrup') !== false ? 'syrup' : 'capsule';
        }

        if ($kind === 'infant_formula') {
            return 'formula';
        }

        if (preg_match('/thermometer|meter|apparatus|glucometer/i', $product->name)) {
            return 'device';
        }

        if (preg_match('/bandage|gauze|cotton|plaster|sling|pouch/i', $product->name)) {
            return 'dressing';
        }

        if (preg_match('/alcohol|peroxide|antiseptic|saline|chloride|dextrose/i', $product->name)) {
            return 'solution';
        }

        return 'consumable';
    }

    /**
     * @return array{top:string, top_holds:?int, base:string}
     */
    private function packingFor(Product $product, string $kind): array
    {
        $name = $product->name;

        if (preg_match('/\bof\s+(\d+)\b/i', $name, $matches)) {
            $count = max(1, (int) $matches[1]);
            $base = 'piece';

            if (stripos($name, 'capsule') !== false) {
                $base = 'capsule';
            } elseif (stripos($name, 'sachet') !== false || stripos($name, 'lyte mix') !== false) {
                $base = 'sachet';
            } elseif (stripos($name, 'dextrose') !== false) {
                $base = 'ampoule';
            } elseif (stripos($name, 'plaster') !== false || stripos($name, 'bandage') !== false) {
                $base = 'roll';
            } elseif ($kind === 'supplement') {
                $base = 'capsule';
            }

            return ['top' => 'pack', 'top_holds' => $count, 'base' => $base];
        }

        if (stripos($name, 'syrup') !== false) {
            return $this->oneRung('bottle');
        }

        // Antiseptics, alcohol, peroxide and hand sanitiser arrive in bottles
        // and leave the shelf whole. They are not counted as loose pieces.
        if (preg_match('/antiseptic|alcohol|peroxide|sanitizer/i', $name)) {
            return $this->oneRung('bottle');
        }

        if (stripos($name, 'bandage') !== false) {
            return $this->oneRung('roll');
        }

        if (stripos($name, 'sodium chloride') !== false) {
            return $this->oneRung('bag');
        }

        if (stripos($name, 'cotton wool') !== false || stripos($name, 'gauze swabs') !== false) {
            return $this->oneRung('pack');
        }

        return $this->oneRung('piece');
    }

    /** @return array{top:string, top_holds:null, base:string} */
    private function oneRung(string $unit): array
    {
        return ['top' => $unit, 'top_holds' => null, 'base' => $unit];
    }

    private function describePacking(array $packing): string
    {
        if ($packing['top_holds'] === null) {
            return $packing['top'] . ' (sold whole)';
        }

        return $packing['top'] . ' ' . $packing['top_holds'] . ' -> ' . $packing['base'];
    }

    /**
     * A sealed pack of capsules, or a tin of formula, is sold whole. Loose
     * capsules out of a sealed pack are not a sale unit. Medical consumables
     * (masks, syringes, strips) may be sold either way, so both rungs stay on.
     */
    private function sellBaseFor(string $kind, array $packing): bool
    {
        if (in_array($kind, ['supplement', 'infant_formula'], true)
            && $packing['top_holds'] !== null) {
            return false;
        }

        return true;
    }

    private function sellDescription(array $packing, string $kind): string
    {
        if ($packing['top_holds'] === null) {
            return $packing['top'];
        }

        return $this->sellBaseFor($kind, $packing)
            ? $packing['top'] . ' + ' . $packing['base']
            : $packing['top'] . ' only';
    }

    /**
     * The old product minimum was counted in pieces. The drug minimum is in
     * the upper unit, so divide and round up: at least one whole pack.
     */
    private function minimumFor(Product $product, int $factor): int
    {
        $minimum = (float) $product->minimum_stock;

        if ($minimum <= 0) {
            return 0;
        }

        return max(1, (int) ceil($minimum / max(1, $factor)));
    }
}
