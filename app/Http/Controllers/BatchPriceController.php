<?php

namespace App\Http\Controllers;

use App\Models\PurchaseBatch;
use Illuminate\Http\Request;

/**
 * Batch selling prices.
 *
 * A batch's selling_price_per_unit is set when the purchase is recorded and
 * there is no other way to change it, so without this screen a price could
 * never be raised. It also backs the locked price box on the sale screen: the
 * price charged is taken from the newest batch that still has stock, so a
 * price change here changes what every sale charges.
 *
 * Every edit is picked up automatically by the LogsActivity trait.
 */
class BatchPriceController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q'));

        $batches = PurchaseBatch::query()
            ->with(['drug:id,name,strength', 'product:id,name,sku', 'branch:id,name'])
            ->where('quantity_remaining', '>', 0)
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . $search . '%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('batch_number', 'like', $like)
                        ->orWhereHas('drug', fn ($q) => $q->where('name', 'like', $like))
                        ->orWhereHas('product', fn ($q) => $q->where('name', 'like', $like));
                });
            })
            ->orderBy('drug_id')
            ->orderBy('product_id')
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('stock-prices.index', compact('batches', 'search'));
    }

    /** Set one batch's selling price. */
    public function update(Request $request, PurchaseBatch $batch)
    {
        $this->authorizeBatch($batch);

        $validated = $request->validate([
            'selling_price_per_unit' => 'required|numeric|min:0|max:1000000',
        ]);

        $batch->update([
            'selling_price_per_unit' => round((float) $validated['selling_price_per_unit'], 4),
        ]);

        return back()->with('success', sprintf(
            'Price for batch %s set to ETB %s.',
            $batch->batch_number,
            rtrim(rtrim(number_format((float) $validated['selling_price_per_unit'], 4, '.', ''), '0'), '.')
        ));
    }

    /**
     * Push one batch's price to every open batch of the same item, so one
     * price rise does not mean editing row after row.
     */
    public function applyToItem(PurchaseBatch $batch)
    {
        $this->authorizeBatch($batch);

        $price = round((float) $batch->selling_price_per_unit, 4);
        $column = $batch->drug_id ? 'drug_id' : 'product_id';

        $affected = PurchaseBatch::where($column, $batch->{$column})
            ->where('quantity_remaining', '>', 0)
            ->update(['selling_price_per_unit' => $price]);

        return back()->with('success', sprintf(
            'ETB %s now applies to all %d open batch(es) of this item.',
            rtrim(rtrim(number_format($price, 4, '.', ''), '0'), '.'),
            $affected
        ));
    }

    /**
     * Single-branch install: the branch_id column is still stamped on every
     * row so the scoping checks elsewhere keep working, but there is only ever
     * one branch, so a mismatch means a stray id rather than a real second
     * branch worth blocking on.
     */
    private function authorizeBatch(PurchaseBatch $batch): void
    {
        $userBranchId = auth()->user()->branch_id;

        if ($userBranchId && $batch->branch_id !== $userBranchId) {
            abort(403, 'That batch belongs elsewhere.');
        }
    }
}
