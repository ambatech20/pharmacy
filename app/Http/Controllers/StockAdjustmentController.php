<?php

namespace App\Http\Controllers;

use App\Models\PurchaseBatch;
use App\Models\StockAdjustment;
use App\Services\AccountingService;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockAdjustmentController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected AccountingService $accountingService
    ) {}

    public function index()
    {
        $user = auth()->user();
        $query = StockAdjustment::with(['purchaseBatch.drug', 'purchaseBatch.product', 'branch', 'user'])->latest();
        if ($user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }
        $adjustments = $query->paginate(10);

        return view('adjustments.index', compact('adjustments'));
    }

    public function create(Request $request)
    {
        $user = auth()->user();
        $query = PurchaseBatch::with(['drug', 'product', 'branch'])
            ->where('quantity_remaining', '>', 0)
            ->orderBy('expiry_date');

        if ($user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }

        $batches = $query->get();
        $selectedBatchId = $request->integer('batch_id') ?: null;
        $reasons = StockAdjustment::REASONS;

        return view('adjustments.create', compact('batches', 'selectedBatchId', 'reasons'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_batch_id' => 'required|exists:purchase_batches,id',
            'quantity' => 'required|integer|min:1',
            'quantity_unit' => 'required|string|max:32',
            'reason' => 'required|in:' . implode(',', array_keys(StockAdjustment::REASONS)),
            'notes' => 'required_if:reason,other|nullable|string|max:255',
        ]);

        $user = auth()->user();
        $batch = PurchaseBatch::with(['drug', 'product'])->findOrFail($request->purchase_batch_id);

        // BUG FIX pattern: a clerk must not write off another branch's batch
        // by posting a guessed purchase_batch_id.
        if ($user->branch_id && $batch->branch_id !== $user->branch_id) {
            abort(403, 'You do not have access to this batch.');
        }

        $item = $batch->drug ?? $batch->product;
        if (!$item) {
            return back()->withErrors(['error' => 'This batch has no catalog item.'])->withInput();
        }

        try {
            // The unit is one of the rungs this item is actually packed in
            // (pack, strip, capsule, bottle...), not a fixed tablet/strip/pack.
            $unitKey = $item->exactLevelKeyFor((string) $request->quantity_unit);
            if ($unitKey === null) {
                return back()->withErrors([
                    'quantity_unit' => 'That unit is not part of how this item is packed.',
                ])->withInput();
            }

            $qtyBase = $item->toBaseQuantity((int) $request->quantity, $unitKey);

            $adjustment = DB::transaction(function () use ($request, $user, $batch, $qtyBase) {
                $adjustment = $this->inventoryService->writeOff(
                    $batch->id,
                    $qtyBase,
                    $request->reason,
                    $request->notes,
                    $user->id
                );

                $this->accountingService->recordWriteOff(
                    $adjustment->total_amount,
                    $adjustment->branch_id,
                    $adjustment->id,
                    $adjustment->reasonLabel()
                );

                return $adjustment;
            }, 3); // retry up to 3x on a transient deadlock / lock-wait timeout

            return redirect()->route('adjustments.index')->with('success', 'Stock written off.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }
}
