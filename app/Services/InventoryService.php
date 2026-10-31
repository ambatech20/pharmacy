<?php
namespace App\Services;
use App\Models\PurchaseBatch;
use App\Models\StockCard;
use App\Models\IssuedItem;
use App\Models\StockAdjustment;
use App\Models\CustomerReturn;
use App\Models\SupplierReturn;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryService {
    public function selectBatchesForDispensing(string $itemType, int $itemId, $branchId, $qtyNeeded) {
        // CONCURRENCY FIX: lockForUpdate() locks these rows for the
        // duration of the enclosing DB transaction (already provided by
        // every caller — DispenseController::store, TransferService).
        // If two requests try to sell from the same batch at once, the
        // second one now waits until the first commits, instead of both
        // reading the same "5 remaining" and overselling.
        $batches = PurchaseBatch::where($itemType === 'product' ? 'product_id' : 'drug_id', $itemId)
            ->where('branch_id', $branchId)
            ->where('quantity_remaining', '>', 0)
            ->where(fn ($query) => $query->whereNull('expiry_date')->orWhere('expiry_date', '>=', Carbon::today()))
            ->orderBy('expiry_date', 'asc')
            ->orderBy('purchase_date', 'asc')
            ->orderBy('id', 'asc') // deterministic: ties on expiry+purchase go oldest-first
            ->lockForUpdate()
            ->get();

        if ($batches->sum('quantity_remaining') < $qtyNeeded) {
            throw new \Exception("Insufficient stock available.");
        }

        $allocations = [];
        $remaining = $qtyNeeded;
        foreach ($batches as $batch) {
            if ($remaining <= 0) break;
            $take = min($batch->quantity_remaining, $remaining);
            $allocations[] = ['batch' => $batch, 'qty' => $take];
            $remaining -= $take;
        }
        return $allocations;
    }

    public function calculateCOGS(array $allocations): float
    {
        $total = 0;
        foreach ($allocations as $item) {
            $total += $item['qty'] * $item['batch']->cost_per_unit;
        }
        return round($total, 2);
    }

    /**
     * The one shelf price for an item: the base-unit selling price of the
     * NEWEST batch that still has usable stock (most recent purchase first,
     * id breaking a same-day tie).
     *
     * Deliberately the newest batch, not the batch FEFO will consume. A sale
     * can span two batches bought at different prices, and the customer must
     * see one price for one item - otherwise the same tablet is sold at two
     * prices on one ticket. Taking the newest (the most recent markup) also
     * makes the price independent of the quantity typed, which is what allows
     * the price box to be locked.
     *
     * FEFO still decides which batch leaves the shelf; this only decides what
     * it is charged at.
     *
     * @param  string  $itemType  'drug' or 'product'
     * @param  array<int,int>  $itemIds
     * @return array<int, float>  base-unit price keyed by item id
     */
    public function shelfPrices(string $itemType, array $itemIds, $branchId): array
    {
        if (empty($itemIds)) {
            return [];
        }

        $foreignKey = $itemType === 'product' ? 'product_id' : 'drug_id';

        $batches = PurchaseBatch::query()
            ->select('id', $foreignKey, 'selling_price_per_unit')
            ->whereIn($foreignKey, $itemIds)
            ->where('branch_id', $branchId)
            ->where('quantity_remaining', '>', 0)
            ->where(fn ($query) => $query->whereNull('expiry_date')->orWhere('expiry_date', '>=', Carbon::today()))
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->get();

        $prices = [];
        foreach ($batches as $batch) {
            $itemId = (int) $batch->{$foreignKey};
            // Rows arrive newest-first, so the first hit for an item wins.
            if (! array_key_exists($itemId, $prices)) {
                $prices[$itemId] = (float) $batch->selling_price_per_unit;
            }
        }

        return $prices;
    }

    public function shelfPrice(string $itemType, int $itemId, $branchId): ?float
    {
        return $this->shelfPrices($itemType, [$itemId], $branchId)[$itemId] ?? null;
    }

    /**
     * The dispensing marker shown next to an item on the sale screen: which
     * batch goes out first (#1) and how much is left on it.
     *
     * Recomputed from the batches on every call — nothing is stored, so there
     * is nothing to renumber when a batch is deleted or written off. The
     * ORDER BY is deliberately identical to selectBatchesForDispensing(),
     * including the id tiebreaker, so the number the seller sees is always the
     * batch that will actually be consumed.
     *
     * @param  string  $itemType  'drug' or 'product'
     * @param  array<int,int>  $itemIds
     * @return array<int, array{rank:int, quantity:int}>  keyed by item id
     */
    public function dispenseMarkers(string $itemType, array $itemIds, $branchId): array
    {
        if (empty($itemIds)) {
            return [];
        }

        $foreignKey = $itemType === 'product' ? 'product_id' : 'drug_id';

        $batches = PurchaseBatch::query()
            ->select('id', $foreignKey, 'quantity_remaining')
            ->selectRaw('ROW_NUMBER() OVER (ORDER BY expiry_date ASC, purchase_date ASC, id ASC) AS dispense_rank')
            ->whereIn($foreignKey, $itemIds)
            ->where('branch_id', $branchId)
            ->where('quantity_remaining', '>', 0)
            ->where(fn ($query) => $query->whereNull('expiry_date')->orWhere('expiry_date', '>=', Carbon::today()))
            ->get()
            ->groupBy($foreignKey);

        $markers = [];
        foreach ($batches as $itemId => $itemBatches) {
            // The window numbers rows across every item at once, so take this
            // item's own lowest rank.
            $next = $itemBatches->sortBy('dispense_rank')->first();
            $markers[(int) $itemId] = [
                'rank' => (int) $next->dispense_rank,
                'quantity' => (int) $next->quantity_remaining,
            ];
        }

        return $markers;
    }

    public function recordIssuance($allocations, $customerId, $branchId, $sellingPricePerUnit, $orderId = null, $itemType = 'drug', $itemId = null, $unitDiscount = 0.0) {
        return DB::transaction(function () use ($allocations, $customerId, $branchId, $sellingPricePerUnit, $orderId, $itemType, $itemId, $unitDiscount) {
            $totalAmount = 0;
            foreach ($allocations as $item) {
                $batch = $item['batch'];
                $qty = $item['qty'];
                $batch->decrement('quantity_remaining', $qty);

                $lineTotal = round($qty * $sellingPricePerUnit, 2);

                $issued = IssuedItem::create([
                    'order_id' => $orderId,
                    'product_id' => $itemType === 'product' ? $itemId : null,
                    'purchase_batch_id' => $batch->id,
                    'customer_id' => $customerId,
                    'branch_id' => $branchId,
                    'quantity_issued' => $qty,
                    'selling_price_per_unit' => $sellingPricePerUnit,
                    'unit_discount' => round((float) $unitDiscount, 2),
                    'total_amount' => $lineTotal,
                    'issue_date' => now(),
                ]);
                $totalAmount += $lineTotal;

                StockCard::create([
                    'purchase_batch_id' => $batch->id,
                    'branch_id' => $branchId,
                    'transaction_type' => 'out',
                    'quantity' => $qty,
                    'unit_cost' => $batch->cost_per_unit,
                    'total_amount' => $qty * $batch->cost_per_unit,
                    'reference_type' => 'sale',
                    'reference_id' => $issued->id,
                    'transaction_date' => now(),
                ]);
            }
            return $totalAmount;
        });
    }

    // Phase 2: a formal write-off, not a silent edit of quantity_remaining.
    // Expired batches are allowed here (dispense FEFO skips them on purpose).
    public function writeOff(int $batchId, int $qty, string $reason, ?string $notes, int $userId)
    {
        return DB::transaction(function () use ($batchId, $qty, $reason, $notes, $userId) {
            $batch = PurchaseBatch::lockForUpdate()->findOrFail($batchId);

            if ($qty < 1) {
                throw new \Exception('Write-off quantity must be at least 1.');
            }
            if ($batch->quantity_remaining < $qty) {
                throw new \Exception('Cannot write off more than the remaining batch quantity (' . $batch->quantity_remaining . ').');
            }

            $batch->decrement('quantity_remaining', $qty);
            $batch->refresh();

            $unitCost = $batch->cost_per_unit;
            $total = round($qty * (float) $unitCost, 2);

            $adjustment = StockAdjustment::create([
                'purchase_batch_id' => $batch->id,
                'branch_id' => $batch->branch_id,
                'user_id' => $userId,
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'total_amount' => $total,
                'reason' => $reason,
                'notes' => $notes,
            ]);

            StockCard::create([
                'purchase_batch_id' => $batch->id,
                'branch_id' => $batch->branch_id,
                'transaction_type' => 'out',
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'total_amount' => $total,
                'reference_type' => 'adjustment',
                'reference_id' => $adjustment->id,
                'transaction_date' => now(),
            ]);

            return $adjustment;
        });
    }

    // Puts sold units back on the original batch. Does not edit the
    // original IssuedItem — the return row is the offsetting record.
    public function restockCustomerReturn(IssuedItem $item, int $qty, string $refundMethod, ?string $notes, int $userId): CustomerReturn
    {
        return DB::transaction(function () use ($item, $qty, $refundMethod, $notes, $userId) {
            $item = IssuedItem::lockForUpdate()->findOrFail($item->id);

            if ($qty < 1) {
                throw new \Exception('Return quantity must be at least 1.');
            }

            $already = (int) CustomerReturn::where('issued_item_id', $item->id)->sum('quantity');
            $returnable = (int) $item->quantity_issued - $already;
            if ($qty > $returnable) {
                throw new \Exception('Cannot return more than the remaining sold quantity (' . $returnable . ').');
            }

            $batch = PurchaseBatch::lockForUpdate()->findOrFail($item->purchase_batch_id);
            $batch->increment('quantity_remaining', $qty);
            $batch->refresh();

            $unitCost = (float) $batch->cost_per_unit;
            $costAmount = round($qty * $unitCost, 2);
            $refundAmount = round($qty * (float) $item->selling_price_per_unit, 2);

            $return = CustomerReturn::create([
                'order_id' => $item->order_id,
                'issued_item_id' => $item->id,
                'purchase_batch_id' => $batch->id,
                'branch_id' => $item->branch_id,
                'user_id' => $userId,
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'cost_amount' => $costAmount,
                'refund_amount' => $refundAmount,
                'refund_method' => $refundMethod,
                'notes' => $notes,
            ]);

            StockCard::create([
                'purchase_batch_id' => $batch->id,
                'branch_id' => $item->branch_id,
                'transaction_type' => 'in',
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'total_amount' => $costAmount,
                'reference_type' => 'customer_return',
                'reference_id' => $return->id,
                'transaction_date' => now(),
            ]);

            return $return;
        });
    }

    public function returnToSupplier(int $batchId, int $qty, ?string $notes, int $userId): SupplierReturn
    {
        return DB::transaction(function () use ($batchId, $qty, $notes, $userId) {
            $batch = PurchaseBatch::lockForUpdate()->findOrFail($batchId);

            if ($qty < 1) {
                throw new \Exception('Return quantity must be at least 1.');
            }
            if ($batch->quantity_remaining < $qty) {
                throw new \Exception('Cannot return more than remaining stock on this batch (' . $batch->quantity_remaining . ').');
            }

            $batch->decrement('quantity_remaining', $qty);
            $batch->refresh();

            $unitCost = (float) $batch->cost_per_unit;
            $total = round($qty * $unitCost, 2);

            $return = SupplierReturn::create([
                'purchase_batch_id' => $batch->id,
                'supplier_id' => $batch->supplier_id,
                'branch_id' => $batch->branch_id,
                'user_id' => $userId,
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'total_amount' => $total,
                'notes' => $notes,
            ]);

            StockCard::create([
                'purchase_batch_id' => $batch->id,
                'branch_id' => $batch->branch_id,
                'transaction_type' => 'out',
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'total_amount' => $total,
                'reference_type' => 'supplier_return',
                'reference_id' => $return->id,
                'transaction_date' => now(),
            ]);

            return $return;
        });
    }
}
