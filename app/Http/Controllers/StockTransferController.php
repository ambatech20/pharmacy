<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Drug;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Services\TransferService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StockTransferController extends Controller
{
    protected $transferService;

    public function __construct(TransferService $transferService)
    {
        $this->transferService = $transferService;
    }

    public function index()
    {
        // BUG FIX: previously showed every transfer across every branch
        // to every user. A branch's relevant transfers are ones where it
        // is either the source OR the destination — filtered for
        // non-admin users, unfiltered (all branches) for Owner/Admin.
        $user = auth()->user();
        $query = StockTransfer::with(['fromBranch', 'toBranch', 'purchaseBatch.drug', 'purchaseBatch.product']);
        if ($user->branch_id) {
            $query->where(function ($q) use ($user) {
                $q->where('from_branch_id', $user->branch_id)
                  ->orWhere('to_branch_id', $user->branch_id);
            });
        }
        $transfers = $query->latest()->paginate(10);

        return view('transfers.index', compact('transfers'));
    }

    public function create()
    {
        // Branches list intentionally shows all branches (not just the
        // user's own) — you need to see every branch to pick a transfer
        // destination. The source is locked to the user's own branch unless
        // they are an Owner/Admin with no branch assignment.
        $branches = Branch::orderBy('name')->get();
        $drugs = Drug::orderBy('name')->get();
        $products = Product::where('active', true)->orderBy('name')->get();
        $sourceBranchId = auth()->user()->branch_id;

        $drugLevels = $this->levels($drugs);
        $productLevels = $this->levels($products);

        return view('transfers.create', compact(
            'branches',
            'drugs',
            'products',
            'drugLevels',
            'productLevels',
            'sourceBranchId'
        ));
    }

    public function store(Request $request)
    {
        if (! $request->filled('item_type') && $request->filled('drug_id')) {
            $request->merge(['item_type' => 'drug', 'item_id' => $request->drug_id]);
        }

        $validated = $request->validate([
            'from_branch_id' => 'required|exists:branches,id',
            'to_branch_id' => 'required|different:from_branch_id|exists:branches,id',
            'item_type' => 'required|in:drug,product',
            'item_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
            'quantity_unit' => 'required|string|max:20',
        ]);

        $user = $request->user();

        // A branch manager may only move stock out of their own branch.
        // Owner/Admin with no branch assignment can move from any branch.
        if ($user->branch_id && (int) $validated['from_branch_id'] !== (int) $user->branch_id) {
            throw ValidationException::withMessages([
                'from_branch_id' => 'You can only transfer stock out of your own branch.',
            ]);
        }

        $item = $validated['item_type'] === 'product'
            ? Product::findOrFail($validated['item_id'])
            : Drug::findOrFail($validated['item_id']);

        // New forms post the rung key (top / middle / base). Keep accepting
        // the old pack / strip / tablet words so cached forms still work.
        $levelKey = $item->exactLevelKeyFor($validated['quantity_unit']);

        if ($levelKey === null && in_array($validated['quantity_unit'], ['pack', 'strip', 'tablet'], true)) {
            $levelKey = $item->levelKeyFor($validated['quantity_unit']);
        }

        if ($levelKey === null) {
            throw ValidationException::withMessages([
                'quantity_unit' => 'That unit is not part of this item\'s packing.',
            ]);
        }

        // Products use the same packing trait as drugs, so both convert to
        // the base unit before TransferService touches the batches.
        $qtyBase = $item->toBaseQuantity((int) $validated['quantity'], $levelKey);

        try {
            $this->transferService->transfer(
                $validated['item_type'],
                $validated['item_id'],
                (int) $validated['from_branch_id'],
                (int) $validated['to_branch_id'],
                $qtyBase,
                $user->id
            );
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        return redirect()->route('transfers.index')->with('success', 'Transfer completed!');
    }

    /**
     * The rungs each item has, biggest first, so the unit picker can offer
     * the item's own words instead of three fixed ones.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Drug|\App\Models\Product>  $items
     * @return array<int, array<int, array{key:string,name:string,factor:int,sellable:bool}>>
     */
    private function levels($items): array
    {
        return $items->mapWithKeys(fn ($item) => [
            $item->id => array_map(fn (array $level) => [
                'key' => $level['key'],
                'name' => $level['name'],
                'factor' => max(1, (int) $level['factor']),
                'sellable' => (bool) $level['sellable'],
            ], $item->packingLevels()),
        ])->all();
    }
}
