<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PurchaseBatch;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\AccountingService;
use App\Services\SupplierOwedService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * PART B — "Owed to suppliers" and "Pay supplier".
 * Owner/Admin only (routes are behind role:owner_admin). All UI language is
 * plain: "Pay later", "Owed to suppliers", "Due date" — never accounting
 * words. The journal entries post correctly behind the scenes.
 */
class SupplierPaymentController extends Controller
{
    public function __construct(
        protected AccountingService $accounting,
        protected SupplierOwedService $owedService,
    ) {}

    /** Owed to suppliers: one row per supplier still owed money. */
    public function index()
    {
        $branchId = auth()->user()->branch_id;
        $suppliers = $this->owedService->supplierSummary($branchId, $this->soonDays());

        return view('supplier-payments.index', [
            'suppliers' => $suppliers,
            'totalOwed' => round(array_sum(array_column($suppliers, 'total_owed')), 2),
            'soonDays' => $this->soonDays(),
        ]);
    }

    /** One supplier's open invoices (purchase_ref groups) with lines. */
    public function show(Supplier $supplier)
    {
        $invoices = $this->owedService->supplierInvoices($supplier->id, auth()->user()->branch_id);

        return view('supplier-payments.show', [
            'supplier' => $supplier,
            'invoices' => $invoices,
            'totalOwed' => round(array_sum(array_column($invoices, 'balance')), 2),
        ]);
    }

    /** Pay supplier form: tick invoices or enter an amount (oldest-first). */
    public function create(Request $request, Supplier $supplier)
    {
        $invoices = $this->owedService->supplierInvoices($supplier->id, auth()->user()->branch_id);

        return view('supplier-payments.create', [
            'supplier' => $supplier,
            'invoices' => $invoices,
            'totalOwed' => round(array_sum(array_column($invoices, 'balance')), 2),
            'prefillInvoice' => $request->query('invoice'),
        ]);
    }

    /** Record the payment. Money + stock-safe: transaction, row locks, logging. */
    public function store(Request $request, Supplier $supplier)
    {
        $branchId = auth()->user()->branch_id;

        $paymentMethod = $request->input('payment_method');
        $isCheck = $paymentMethod === 'check';

        $request->validate([
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => ['required', Rule::in(array_keys(Payment::METHODS))], // no "Pay later" when paying
            'payment_reference' => [
                'nullable', 'string', 'max:255',
                Rule::requiredIf(fn () => !in_array($paymentMethod, ['cash', 'check'], true)),
            ],
            'check_number' => ['nullable', 'string', 'max:100', Rule::requiredIf(fn () => $isCheck)],
            'check_date' => ['nullable', 'date', Rule::requiredIf(fn () => $isCheck)],
            'check_bank' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $isCheck)],
            'note' => 'nullable|string|max:1000',
            'invoices' => 'nullable|array',
            'invoices.*' => 'nullable|numeric|min:0',
        ], [
            'amount.required' => 'Please enter how much you are paying.',
            'payment_reference.required' => 'Please enter the transaction number for this payment.',
            'check_number.required' => 'Please enter the check number.',
            'check_date.required' => 'Please enter the check date.',
            'check_bank.required' => 'Please enter the bank the check is drawn on.',
        ]);

        $amount = round((float) $request->amount, 2);

        DB::beginTransaction();
        try {
            // Lock the supplier's open purchase lines so two clerks paying
            // at the same time can't both apply the same balance (MySQL
            // SELECT ... FOR UPDATE; harmless no-op on sqlite tests).
            $lines = PurchaseBatch::where('supplier_id', $supplier->id)
                ->where('payment_method', Payment::PAY_LATER)
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->lockForUpdate()
                ->orderBy('due_date')
                ->orderBy('purchase_date')
                ->orderBy('id')
                ->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['amount' => 'This supplier is not owed anything right now.']);
            }

            $totalOwed = round($lines->sum(fn ($l) => max(0, round((float) $l->total_cost - (float) $l->amount_paid, 2))), 2);
            if ($amount > $totalOwed + 0.001) {
                throw ValidationException::withMessages([
                    'amount' => "You can't pay more than what is owed (ETB " . number_format($totalOwed, 2) . ').',
                ]);
            }

            // Chosen invoices (by purchase_ref) or all open ones oldest-first.
            $chosenRefs = $request->has('invoices')
                ? collect($request->input('invoices'))->filter(fn ($v) => (float) $v > 0)->keys()->all()
                : null;

            $candidates = $chosenRefs && count($chosenRefs)
                ? $lines->filter(fn ($l) => in_array($l->purchase_ref ?? ('BATCH-' . $l->id), $chosenRefs, true))
                : $lines;

            // Oldest-first allocation across lines of the chosen invoices.
            $remaining = $amount;
            $allocations = [];
            foreach ($candidates as $line) {
                if ($remaining <= 0.004) {
                    break;
                }
                $balance = round(max(0, (float) $line->total_cost - (float) $line->amount_paid), 2);
                if ($balance <= 0) {
                    continue;
                }
                $apply = round(min($balance, $remaining), 2);
                $allocations[] = ['line' => $line, 'amount' => $apply];
                $remaining = round($remaining - $apply, 2);
            }

            $applied = round($amount - $remaining, 2);
            if ($applied <= 0) {
                throw ValidationException::withMessages(['invoices' => 'The selected purchases are already fully paid.']);
            }

            $payment = SupplierPayment::create([
                'payment_number' => 'SP-' . now()->format('Ymd-His') . '-' . rand(1000, 9999),
                'supplier_id' => $supplier->id,
                'branch_id' => $branchId ?? $lines->first()->branch_id,
                'payment_date' => $request->payment_date,
                'amount' => $amount,
                'amount_applied' => $applied,
                'overpayment' => round($amount - $applied, 2), // always 0 — overpay is rejected above; kept for audit clarity
                'payment_method' => $paymentMethod,
                'payment_reference' => $isCheck ? ($request->payment_reference ?: $request->check_number) : ($paymentMethod === 'cash' ? null : $request->payment_reference),
                'check_number' => $isCheck ? $request->check_number : null,
                'check_date' => $isCheck ? $request->check_date : null,
                'check_bank' => $isCheck ? $request->check_bank : null,
                'note' => $request->note,
            ]);

            foreach ($allocations as $alloc) {
                $line = $alloc['line'];
                \App\Models\SupplierPaymentAllocation::create([
                    'supplier_payment_id' => $payment->id,
                    'purchase_batch_id' => $line->id,
                    'purchase_ref' => $line->purchase_ref,
                    'amount' => $alloc['amount'],
                ]);

                $newPaid = round((float) $line->amount_paid + $alloc['amount'], 2);
                $line->amount_paid = $newPaid;
                $line->payment_status = $newPaid >= round((float) $line->total_cost, 2) - 0.004 ? 'paid' : 'partial';
                $line->save();
            }

            // Behind the scenes: the "Owed to Suppliers" liability goes down
            // and the money actually leaves through the chosen method's
            // account (cash / bank / mobile money / card / check→bank).
            $entry = $this->accounting->postEntry('Supplier payment: ' . $supplier->name, $payment->branch_id, 'supplier_payment', $payment->id, [
                ['account_code' => Payment::OWED_TO_SUPPLIERS_ACCOUNT, 'debit' => $applied, 'credit' => 0, 'desc' => 'Owed to suppliers'],
                ['account_code' => Payment::accountCodeFor($paymentMethod), 'debit' => 0, 'credit' => $applied, 'desc' => Payment::METHODS[$paymentMethod]],
            ]);
            $payment->journal_entry_id = $entry->id;
            $payment->save();

            DB::commit();

            $remainingOwed = round(array_sum(array_column($this->owedService->supplierInvoices($supplier->id, $branchId), 'balance')), 2);

            return redirect()->route('supplier-owed.show', $supplier)
                ->with('success', 'Payment recorded. ' . $supplier->name . ' is now owed ETB ' . number_format($remainingOwed, 2) . '.');
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['error' => 'Could not record the payment: ' . $e->getMessage()]);
        }
    }

    /** History of payments made to a supplier (plain-language list). */
    public function history(Supplier $supplier)
    {
        $branchId = auth()->user()->branch_id;

        $payments = SupplierPayment::with(['supplier', 'allocations.batch.drug', 'allocations.batch.product'])
            ->where('supplier_id', $supplier->id)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->latest('payment_date')->latest('id')
            ->paginate(15);

        return view('supplier-payments.history', compact('supplier', 'payments'));
    }

    /** The reminder window (days) — a setting, default 7. */
    protected function soonDays(): int
    {
        $raw = \App\Models\Setting::value('supplier_alert_days', '7');

        return max(1, (int) $raw);
    }
}
