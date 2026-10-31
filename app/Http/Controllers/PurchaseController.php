<?php

namespace App\Http\Controllers;

use App\Models\PurchaseBatch;
use App\Models\Drug;
use App\Models\Supplier;
use App\Models\Branch;
use App\Models\IssuedItem;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockCard;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PurchaseController extends Controller
{
    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function index()
    {
        // BUG FIX: previously showed every branch's purchases to every
        // user, regardless of which branch they belong to.
        $user = auth()->user();
        $query = PurchaseBatch::with(['drug', 'product', 'supplier', 'branch']);
        if ($user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }
        $purchases = $query->latest()->paginate(10);

        return view('purchases.index', compact('purchases'));
    }

    public function create()
    {
        $drugs = Drug::all();
        $products = Product::where('active', true)->orderBy('name')->get();
        $suppliers = Supplier::all();
        $branches = Branch::orderBy('name')->get();

        // The rungs each drug has, biggest first, so a line can offer the
        // invoice's own unit ("2 boxes") instead of three fixed words.
        $drugLevels = $drugs->mapWithKeys(fn (Drug $drug) => [
            $drug->id => array_map(fn (array $level) => [
                'key' => $level['key'],
                'name' => $level['name'],
                'factor' => max(1, (int) $level['factor']),
                'sellable' => (bool) $level['sellable'],
            ], $drug->packingLevels()),
        ]);

        // Products now describe packing too: a box of 12 bottles, a pack of
        // 50 masks, or a single piece sold whole.
        $productLevels = $products->mapWithKeys(fn (Product $product) => [
            $product->id => array_map(fn (array $level) => [
                'key' => $level['key'],
                'name' => $level['name'],
                'factor' => max(1, (int) $level['factor']),
                'sellable' => (bool) $level['sellable'],
            ], $product->packingLevels()),
        ]);

        return view('purchases.create', compact('drugs', 'products', 'suppliers', 'branches', 'drugLevels', 'productLevels'));
    }

    public function store(Request $request)
    {
        if (!$request->has('items')) {
            $request->merge(['items' => [[
                'item_type' => $request->item_type ?: 'drug',
                'item_id' => $request->item_id ?: $request->drug_id,
                'batch_number' => $request->batch_number ?: ('B-' . now()->format('ymd') . '-' . mt_rand(1000, 9999)),
                'quantity_received' => $request->quantity_received,
                'quantity_unit' => $request->quantity_unit,
                'cost_per_unit' => $request->cost_per_unit,
                'selling_price_per_unit' => $request->selling_price_per_unit,
                'expiry_date' => $request->expiry_date,
            ]]]);
        }

        $paymentMethod = $request->input('payment_method');
        $isPayLater = $paymentMethod === Payment::PAY_LATER;
        $isCheck = $paymentMethod === 'check';

        // Legacy single-line callers (tests, old cached forms) post without a
        // payment block. Defaulting to cash keeps their behaviour identical
        // to before Part A instead of failing validation.
        if (!$request->has('payment_method')) {
            $request->merge(['payment_method' => 'cash']);
            $paymentMethod = 'cash';
        }

        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'branch_id' => 'nullable|exists:branches,id',
            'purchase_date' => 'required|date',
            // Part A — "How are you paying?" (existing methods + Pay later).
            'payment_method' => ['required', Rule::in(array_merge(array_keys(Payment::METHODS), [Payment::PAY_LATER]))],
            'payment_reference' => [
                'nullable', 'string', 'max:255',
                Rule::requiredIf(fn () => !$isPayLater && !in_array($paymentMethod, ['cash', 'check'], true)),
            ],
            'check_number' => ['nullable', 'string', 'max:100', Rule::requiredIf(fn () => $isCheck)],
            'check_date' => ['nullable', 'date', Rule::requiredIf(fn () => $isCheck)],
            'check_bank' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $isCheck)],
            'due_date' => ['nullable', 'date', Rule::requiredIf(fn () => $isPayLater)],
            'supplier_invoice_no' => 'nullable|string|max:100',
            'items' => 'required|array|min:1',
            'items.*.item_type' => 'required|in:drug,product',
            'items.*.item_id' => 'required|integer',
            // Batch numbers are optional: callers that don't send one get an
            // auto-generated value below. A batch number is a manufacturer lot
            // number, so the same value legitimately recurs across receipts —
            // it is intentionally not unique on this table.
            'items.*.batch_number' => ['nullable', 'string', 'max:255'],
            'items.*.quantity_received' => 'required|integer|min:1',
            // Checked per line below: a drug may only be bought in a unit it
            // actually has, and the old tablet/strip/pack words still mean
            // biggest/middle/smallest for forms cached before this change.
            'items.*.quantity_unit' => 'required|string|max:20',
            'items.*.cost_per_unit' => 'required|numeric|min:0',
            'items.*.selling_price_per_unit' => 'required|numeric|min:0',
            // Expiry is mandatory on every line. FEFO dispensing, the
            // expiring-soon alerts and the "batch has no expiry on file"
            // edge case all depend on a date being present. Retail lines
            // that genuinely never expire still need one — use a far-future
            // date such as 2099-12-31 so it never surfaces in the alerts.
            'items.*.expiry_date' => 'required|date|after:today',
        ], [
            'payment_reference.required' => 'Please enter the transaction number for this payment.',
            'check_number.required' => 'Please enter the check number.',
            'check_date.required' => 'Please enter the check date.',
            'check_bank.required' => 'Please enter the bank the check is drawn on.',
            'due_date.required' => 'For "Pay later" please set a due date.',
            'items.*.expiry_date.required' => 'Every item line needs an expiry date.',
        ], [
            'items.*.expiry_date' => 'expiry date',
        ]);

        // The form offers a branch picker on a multi-branch install. If it is
        // left blank (or an older cached form posts without it), fall back to
        // the signed-in user's branch, then to the first branch.
        $branchId = $request->input('branch_id')
            ?: $request->user()->branch_id
            ?: Branch::query()->orderBy('id')->value('id');

        // One ref shared by every line created in this submission, so the
        // purchase behaves as one invoice in "Owed to suppliers" (Part B).
        // Honour a client-supplied ref when present (tests / integrations);
        // otherwise generate one. uniqid() keeps submissions made within the
        // same second distinct.
        $purchaseRef = trim((string) $request->input('purchase_ref'))
            ?: ('PUR-' . now()->format('Ymd-His') . '-' . strtoupper(Str::random(4)) . '-' . substr(uniqid(), -5));

        DB::beginTransaction();
        try {
            foreach ($request->items as $line) {
                $item = $line['item_type'] === 'product' ? Product::findOrFail($line['item_id']) : Drug::findOrFail($line['item_id']);
                $isDrug = $item instanceof Drug;

                // Buying must name a rung the item really has - the same
                // rule the till applies to selling. A stale form that posts
                // "strip" for a pack of loose tablets is refused rather than
                // silently recorded as tablets.
                $unitKey = $item->exactLevelKeyFor((string) $line['quantity_unit']);

                if ($unitKey === null) {
                    throw new \Exception(
                        $item->name . ' is not bought by the ' . $line['quantity_unit']
                        . '. It comes as ' . implode(', ', array_column($item->packingLevels(), 'name')) . '.'
                    );
                }

                $qtyBase = $item->toBaseQuantity((int) $line['quantity_received'], $unitKey);
                $totalCost = round((float) $line['quantity_received'] * (float) $line['cost_per_unit'], 2);
                $costPerBase = $item->pricePerBase((float) $line['cost_per_unit'], $unitKey);
                $sellingPerBase = $item->pricePerBase((float) $line['selling_price_per_unit'], $unitKey);
                $batch = PurchaseBatch::create([
                    'drug_id' => $isDrug ? $item->id : null,
                    'product_id' => $isDrug ? null : $item->id,
                    'supplier_id' => $request->supplier_id,
                    'branch_id' => $branchId,
                'batch_number' => $line['batch_number'] ?: ('B-' . now()->format('ymdHis') . '-' . mt_rand(1000, 9999)),
                    'quantity_received' => $qtyBase,
                    'quantity_remaining' => $qtyBase,
                    'cost_per_unit' => round($costPerBase, 4),
                    'selling_price_per_unit' => round($sellingPerBase, 4),
                    'expiry_date' => $line['expiry_date'] ?? null,
                    'purchase_date' => $request->purchase_date,
                    // Part A — payment fields. amount_paid uses the same
                    // receipt-quantity basis as $totalCost below.
                    'payment_status' => $isPayLater ? 'unpaid' : 'paid',
                    'amount_paid' => $isPayLater ? 0 : $totalCost,
                    'due_date' => $isPayLater ? $request->due_date : null,
                    'payment_method' => $paymentMethod,
                    // For a check, the check number doubles as the reference.
                    'payment_reference' => $isPayLater
                        ? null
                        : ($isCheck ? ($request->payment_reference ?: $request->check_number) : $request->payment_reference),
                    'check_number' => $isCheck ? $request->check_number : null,
                    'check_date' => $isCheck ? $request->check_date : null,
                    'check_bank' => $isCheck ? $request->check_bank : null,
                    'purchase_ref' => $purchaseRef,
                    'supplier_invoice_no' => $request->supplier_invoice_no,
                ]);
                StockCard::create([
                    'purchase_batch_id' => $batch->id, 'branch_id' => $branchId,
                    'transaction_type' => 'in', 'quantity' => $qtyBase, 'unit_cost' => $batch->cost_per_unit,
                    'total_amount' => $totalCost, 'reference_type' => 'purchase', 'reference_id' => $batch->id,
                    'transaction_date' => now(),
                ]);

                // Behind-the-scenes posting (plain language in the UI):
                //  paid now   -> stock up, chosen method's account down
                //  Pay later  -> stock up, single "Owed to Suppliers" (2000) up
                $creditCode = $isPayLater
                    ? Payment::OWED_TO_SUPPLIERS_ACCOUNT
                    : Payment::accountCodeFor($paymentMethod);
                $creditLabel = $isPayLater ? 'Owed to supplier' : (Payment::METHODS[$paymentMethod] ?? $paymentMethod);

                $this->accountingService->postEntry('Purchase: ' . $item->name, $branchId, 'purchase', $batch->id, [
                    ['account_code' => '1200', 'debit' => $totalCost, 'credit' => 0, 'desc' => 'Inventory'],
                    ['account_code' => $creditCode, 'debit' => 0, 'credit' => $totalCost, 'desc' => $creditLabel],
                ]);
            }

            DB::commit();
            return redirect()->route('purchases.index')->with('success', 'Purchase recorded.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove a purchase that was entered by mistake.
     *
     * Only an untouched batch may go: the moment any of it has been sold,
     * written off or sent back, deleting the row would orphan those records.
     * Such a batch is corrected with a supplier return instead.
     *
     * The ledger entry is reversed rather than erased, so the books keep the
     * pair and still balance.
     */
    public function destroy(PurchaseBatch $batch)
    {
        $user = auth()->user();

        if ($user->branch_id && (int) $batch->branch_id !== (int) $user->branch_id) {
            abort(403, 'You do not have access to this purchase.');
        }

        $touched = (int) $batch->quantity_remaining !== (int) $batch->quantity_received
            || StockCard::where('purchase_batch_id', $batch->id)
                ->where('transaction_type', '!=', 'in')
                ->exists()
            || IssuedItem::where('purchase_batch_id', $batch->id)->exists()
            || StockAdjustment::where('purchase_batch_id', $batch->id)->exists();

        if ($touched) {
            return back()->withErrors([
                'error' => 'This purchase has already moved stock (sold, written off or returned). '
                    . 'Use Supplier return for what is left instead of deleting it.',
            ]);
        }

        DB::transaction(function () use ($batch) {
            $label = $batch->drug?->name ?? $batch->product?->name ?? 'item';

            $entries = JournalEntry::where('reference_type', 'purchase')
                ->where('reference_id', $batch->id)
                ->get();

            foreach ($entries as $entry) {
                $this->accountingService->reverseEntry($entry, 'Deleted purchase: ' . $label);
            }

            StockCard::where('purchase_batch_id', $batch->id)->delete();
            $batch->delete();
        });

        return redirect()->route('purchases.index')
            ->with('success', 'Purchase deleted. Its stock and ledger entry were reversed.');
    }
}
