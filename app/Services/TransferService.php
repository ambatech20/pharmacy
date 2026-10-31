<?php
namespace App\Services;
use App\Models\StockTransfer;
use App\Models\StockCard;
use App\Models\PurchaseBatch;
use Illuminate\Support\Facades\DB;

class TransferService {
    public function transfer($itemType, $itemId, $sourceId, $destId, $qty, $userId) {
        if ($sourceId === $destId) {
            throw new \Exception("Source and destination must differ.");
        }

        return DB::transaction(function () use ($itemType, $itemId, $sourceId, $destId, $qty, $userId) {
            // CONCURRENCY FIX: same lockForUpdate() protection as
            // InventoryService — prevents a transfer and a dispense (or
            // two transfers) from racing on the same batch's stock.
            $sourceBatches = PurchaseBatch::where($itemType === 'product' ? 'product_id' : 'drug_id', $itemId)
                ->where('branch_id', $sourceId)
                ->where('quantity_remaining', '>', 0)
                ->where(fn ($query) => $query->whereNull('expiry_date')->orWhere('expiry_date', '>=', now()->toDateString()))
                ->orderBy('expiry_date', 'asc')
                ->orderBy('purchase_date', 'asc')
                ->lockForUpdate()
                ->get();

            if ($sourceBatches->sum('quantity_remaining') < $qty) {
                throw new \Exception("Insufficient stock available at source branch for this transfer.");
            }

            $remaining = $qty;
            $lastTransfer = null;

            foreach ($sourceBatches as $batch) {
                if ($remaining <= 0) break;

                $take = min($batch->quantity_remaining, $remaining);
                $remaining -= $take;

                $batch->decrement('quantity_remaining', $take);

                StockCard::create([
                    'purchase_batch_id' => $batch->id,
                    'branch_id' => $sourceId,
                    'transaction_type' => 'transfer_out',
                    'quantity' => $take,
                    'unit_cost' => $batch->cost_per_unit,
                    'total_amount' => $take * $batch->cost_per_unit,
                    'reference_type' => 'transfer',
                    'reference_id' => 0,
                    'transaction_date' => now(),
                ]);

                $destBatch = PurchaseBatch::create([
                    'drug_id' => $batch->drug_id,
                    'product_id' => $batch->product_id,
                    'supplier_id' => $batch->supplier_id,
                    'branch_id' => $destId,
                    'batch_number' => $batch->batch_number . '-T' . now()->format('YmdHis'),
                    'quantity_received' => $take,
                    'quantity_remaining' => $take,
                    'cost_per_unit' => $batch->cost_per_unit,
                    'selling_price_per_unit' => $batch->selling_price_per_unit,
                    'expiry_date' => $batch->expiry_date,
                    'purchase_date' => $batch->purchase_date,
                ]);

                StockCard::create([
                    'purchase_batch_id' => $destBatch->id,
                    'branch_id' => $destId,
                    'transaction_type' => 'transfer_in',
                    'quantity' => $take,
                    'unit_cost' => $batch->cost_per_unit,
                    'total_amount' => $take * $batch->cost_per_unit,
                    'reference_type' => 'transfer',
                    'reference_id' => 0,
                    'transaction_date' => now(),
                ]);

                $lastTransfer = StockTransfer::create([
                    'from_branch_id' => $sourceId,
                    'to_branch_id' => $destId,
                    'purchase_batch_id' => $batch->id,
                    'quantity' => $take,
                    'unit_cost' => $batch->cost_per_unit,
                    'total_amount' => $take * $batch->cost_per_unit,
                    'transfer_date' => now(),
                    'status' => 'completed',
                ]);
            }

            return $lastTransfer;
        });
    }
}
