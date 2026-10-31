<?php

namespace App\Http\Controllers;

use App\Models\CustomerReturn;
use App\Models\IssuedItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PurchaseBatch;
use App\Models\SupplierReturn;
use App\Services\AccountingService;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected AccountingService $accountingService
    ) {}

    public function index()
    {
        $user = auth()->user();

        $customerQuery = CustomerReturn::with(['purchaseBatch.drug', 'purchaseBatch.product', 'order', 'user'])->latest();
        $supplierQuery = SupplierReturn::with(['purchaseBatch.drug', 'purchaseBatch.product', 'supplier', 'user'])->latest();
        if ($user->branch_id) {
            $customerQuery->where('branch_id', $user->branch_id);
            $supplierQuery->where('branch_id', $user->branch_id);
        }

        $customerReturns = $customerQuery->limit(25)->get();
        $supplierReturns = $supplierQuery->limit(25)->get();

        return view('returns.index', compact('customerReturns', 'supplierReturns'));
    }

    public function createCustomer(Request $request)
    {
        $user = auth()->user();
        $order = null;
        $items = collect();

        if ($request->filled('order_id')) {
            $order = Order::with(['items.purchaseBatch.drug', 'items.customerReturns', 'customer', 'payments'])->findOrFail($request->order_id);
            if ($user->branch_id && $order->branch_id !== $user->branch_id) {
                abort(403, 'You do not have access to this order.');
            }
            $items = $order->items;
        }

        $ordersQuery = Order::with('customer')->latest();
        if ($user->branch_id) {
            $ordersQuery->where('branch_id', $user->branch_id);
        }
        $orders = $ordersQuery->limit(50)->get();
        $paymentMethods = Payment::METHODS;
        $defaultMethod = $order?->payments->first()?->payment_method ?? 'cash';

        return view('returns.customer-create', compact('order', 'items', 'orders', 'paymentMethods', 'defaultMethod'));
    }

    public function storeCustomer(Request $request)
    {
        $request->validate([
            'issued_item_id' => 'required|exists:issued_items,id',
            'quantity' => 'required|integer|min:1',
            'quantity_unit' => 'required|string|max:32',
            'refund_method' => 'required|in:' . implode(',', array_keys(Payment::METHODS)),
            'notes' => 'nullable|string|max:255',
        ]);

        $user = auth()->user();
        $item = IssuedItem::with(['purchaseBatch.drug', 'purchaseBatch.product'])->findOrFail($request->issued_item_id);

        if (!$item->order_id) {
            return back()->withErrors(['error' => 'This sale line is not tied to an order, so it cannot be returned here.'])->withInput();
        }
        if ($user->branch_id && $item->branch_id !== $user->branch_id) {
            abort(403, 'You do not have access to this sale.');
        }

        $catalogItem = $item->purchaseBatch?->drug ?? $item->purchaseBatch?->product;
        if (!$catalogItem) {
            return back()->withErrors(['error' => 'This sale line has no catalog item.'])->withInput();
        }

        try {
            $unitKey = $catalogItem->exactLevelKeyFor((string) $request->quantity_unit);
            if ($unitKey === null) {
                return back()->withErrors([
                    'quantity_unit' => 'That unit is not part of how this item is packed.',
                ])->withInput();
            }

            $qtyBase = $catalogItem->toBaseQuantity((int) $request->quantity, $unitKey);

            $return = DB::transaction(function () use ($request, $user, $item, $qtyBase) {
                $return = $this->inventoryService->restockCustomerReturn(
                    $item,
                    $qtyBase,
                    $request->refund_method,
                    $request->notes,
                    $user->id
                );

                $this->accountingService->recordCustomerReturn(
                    $return->refund_amount,
                    $return->cost_amount,
                    $return->branch_id,
                    $return->id,
                    $return->refund_method
                );

                return $return;
            }, 3); // retry up to 3x on a transient deadlock / lock-wait timeout

            return redirect()->route('returns.index')->with('success', 'Customer return recorded. Stock restored to the original batch.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function createSupplier(Request $request)
    {
        $user = auth()->user();
        $query = PurchaseBatch::with(['drug', 'product', 'supplier', 'branch'])
            ->where('quantity_remaining', '>', 0)
            ->orderBy('expiry_date');

        if ($user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }

        $batches = $query->get();
        $selectedBatchId = $request->integer('batch_id') ?: null;

        return view('returns.supplier-create', compact('batches', 'selectedBatchId'));
    }

    public function storeSupplier(Request $request)
    {
        $request->validate([
            'purchase_batch_id' => 'required|exists:purchase_batches,id',
            'quantity' => 'required|integer|min:1',
            'quantity_unit' => 'required|string|max:32',
            'notes' => 'nullable|string|max:255',
        ]);

        $user = auth()->user();
        $batch = PurchaseBatch::with(['drug', 'product'])->findOrFail($request->purchase_batch_id);

        if ($user->branch_id && $batch->branch_id !== $user->branch_id) {
            abort(403, 'You do not have access to this batch.');
        }
        $catalogItem = $batch->drug ?? $batch->product;
        if (!$catalogItem) {
            return back()->withErrors(['error' => 'This batch has no catalog item.'])->withInput();
        }

        try {
            $unitKey = $catalogItem->exactLevelKeyFor((string) $request->quantity_unit);
            if ($unitKey === null) {
                return back()->withErrors([
                    'quantity_unit' => 'That unit is not part of how this item is packed.',
                ])->withInput();
            }

            $qtyBase = $catalogItem->toBaseQuantity((int) $request->quantity, $unitKey);

            $return = DB::transaction(function () use ($request, $user, $batch, $qtyBase) {
                $return = $this->inventoryService->returnToSupplier(
                    $batch->id,
                    $qtyBase,
                    $request->notes,
                    $user->id
                );

                $this->accountingService->recordSupplierReturn(
                    $return->total_amount,
                    $return->branch_id,
                    $return->id
                );

                return $return;
            }, 3); // retry up to 3x on a transient deadlock / lock-wait timeout

            return redirect()->route('returns.index')->with('success', 'Supplier return recorded. Stock and AP were reversed.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }
}
