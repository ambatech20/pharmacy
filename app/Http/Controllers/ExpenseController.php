<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * PART C — Expenses. Owner/Admin only (routes enforce the role).
 * Plain language in the UI: no "debit/credit/journal" wording anywhere.
 * Posting behind the scenes: expense account up, paying method's account down.
 */
class ExpenseController extends Controller
{
    public function __construct(protected AccountingService $accounting)
    {
    }

    /**
     * Branch scope: same pattern as the rest of the app — users with a
     * branch see only their branch; owner/admin (branch_id null) sees all.
     */
    protected function scopedQuery()
    {
        $query = Expense::with(['category', 'branch']);
        if ($branchId = auth()->user()->branch_id) {
            $query->where('branch_id', $branchId);
        }

        return $query;
    }

    public function index(Request $request)
    {
        $query = $this->scopedQuery();

        if ($request->filled('date_from')) {
            $query->whereDate('expense_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('expense_date', '<=', $request->date_to);
        }
        if ($request->filled('category_id')) {
            $query->where('expense_category_id', $request->category_id);
        }

        $expenses = $query->latest('expense_date')->latest('id')->paginate(20)->withQueryString();

        // Monthly total by category for the filtered range (defaults to this month).
        $monthFrom = $request->date('date_from') ?? now()->startOfMonth();
        $monthTo = $request->date('date_to') ?? now()->endOfMonth();
        $totalsQuery = Expense::query();
        if ($branchId = auth()->user()->branch_id) {
            $totalsQuery->where('branch_id', $branchId);
        }
        $monthlyTotals = $totalsQuery
            ->whereBetween('expense_date', [$monthFrom->copy()->startOfDay(), $monthTo->copy()->endOfDay()])
            ->selectRaw('expense_category_id, SUM(amount) as total')
            ->groupBy('expense_category_id')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->expense_category_id => round((float) $r->total, 2)]);

        $categories = ExpenseCategory::orderBy('name')->get();

        return view('expenses.index', compact('expenses', 'categories', 'monthlyTotals'));
    }

    public function create(Request $request)
    {
        $categories = ExpenseCategory::active()->orderBy('name')->get();

        // Arriving from a reminder ("Record payment") pre-fills the form with
        // the bill being paid, so the payment does not have to be retyped.
        $bill = null;
        if ($request->filled('bill')) {
            $bill = Expense::find($request->integer('bill'));
            if ($bill) {
                $this->authorizeBranch($bill);
            }
        }

        return view('expenses.create', [
            'categories' => $categories,
            'methods' => Payment::METHODS, // no "Pay later" here — expenses are paid when recorded
            'paidMonthOptions' => Expense::PAID_MONTH_OPTIONS,
            'bill' => $bill,
            'repeatingCategoryIds' => $this->repeatingCategoryIds(),
        ]);
    }

    /**
     * The categories that already have a repeating bill. The form uses this
     * to tick "every month" by itself when one of them is chosen, so paying
     * rent from the Expenses screen still moves the reminder on.
     */
    protected function repeatingCategoryIds(): array
    {
        return Expense::query()
            ->where('repeat_monthly', true)
            ->whereNotNull('next_due_date')
            ->distinct()
            ->pluck('expense_category_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $expense = Expense::create($this->withNextDue($data));
            $this->postExpense($expense);
        });

        return redirect()->route('expenses.index')->with('success', 'Expense recorded.');
    }

    public function edit(Expense $expense)
    {
        $this->authorizeBranch($expense);

        $categories = ExpenseCategory::orderBy('name')->get();

        return view('expenses.edit', [
            'expense' => $expense,
            'categories' => $categories,
            'methods' => Payment::METHODS,
            'paidMonthOptions' => Expense::PAID_MONTH_OPTIONS,
        ]);
    }

    /**
     * Safe edit: reverse the old posting and post the new one inside one
     * transaction so the books always stay in step with the record.
     */
    public function update(Request $request, Expense $expense)
    {
        $this->authorizeBranch($expense);
        $data = $this->validated($request);

        DB::transaction(function () use ($expense, $data) {
            $this->reverseExpense($expense);
            // Exclude this row when looking for the repeating bill to move on,
            // otherwise editing a reminder would push its own date.
            $expense->update($this->withNextDue($data, $expense->id));
            $expense->refresh();
            $this->postExpense($expense);
        });

        return redirect()->route('expenses.index')->with('success', 'Expense updated.');
    }

    /**
     * Safe delete: reversal entry + soft delete, activity logged via trait.
     */
    public function destroy(Expense $expense)
    {
        $this->authorizeBranch($expense);

        DB::transaction(function () use ($expense) {
            $this->reverseExpense($expense);
            $expense->delete();
        });

        return back()->with('success', 'Expense removed.');
    }

    protected function validated(Request $request): array
    {
        $paymentMethod = $request->input('payment_method');
        $isCheck = $paymentMethod === 'check';

        $data = $request->validate([
            'expense_date' => 'required|date',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'branch_id' => 'nullable|exists:branches,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'payment_reference' => [
                'nullable', 'string', 'max:255',
                Rule::requiredIf(fn () => !in_array($paymentMethod, ['cash', 'check'], true)),
            ],
            'check_number' => ['nullable', 'string', 'max:100', Rule::requiredIf(fn () => $isCheck)],
            'check_date' => ['nullable', 'date', Rule::requiredIf(fn () => $isCheck)],
            'check_bank' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $isCheck)],
            'note' => 'nullable|string|max:1000',
            'repeat_monthly' => 'nullable|boolean',
            'paid_months' => 'nullable|integer|in:' . implode(',', Expense::PAID_MONTH_OPTIONS),
        ], [
            'payment_reference.required' => 'Please enter the transaction number for this payment.',
            'check_number.required' => 'Please enter the check number.',
            'check_date.required' => 'Please enter the check date.',
            'check_bank.required' => 'Please enter the bank the check is drawn on.',
            'amount.min' => 'Please enter an amount greater than zero.',
        ]);

        // Single-branch install: staff post to their own branch; an
        // Owner/Admin with no branch gets the pharmacy's one branch.
        $data['branch_id'] = ($data['branch_id'] ?? null) ?: auth()->user()->branch_id ?: Branch::query()->orderBy('id')->value('id');
        abort_if(!$data['branch_id'], 400, 'No branch is set up yet — run the installer first.');

        $data['payment_reference'] = $isCheck
            ? ($data['payment_reference'] ?? ($data['check_number'] ?? null))
            : ($data['payment_reference'] ?? null);
        $data['repeat_monthly'] = $request->boolean('repeat_monthly');
        // Check details only mean something when the method is a check.
        $data['check_number'] = $isCheck ? $request->check_number : null;
        $data['check_date'] = $isCheck ? $request->check_date : null;
        $data['check_bank'] = $isCheck ? $request->check_bank : null;

        return $data;
    }

    protected function withNextDue(array $data, ?int $exceptId = null): array
    {
        $months = (int) ($data['paid_months'] ?? 1);
        $data['paid_months'] = $months;

        // The amount on the form is the rate for ONE period. Paying three
        // months at 6,000 moves 18,000, because that is the money that left
        // the account - so that is what the books record.
        $monthly = (float) $data['amount'];
        $total = $monthly * $months;

        // A one-off expense never touches a repeating bill.
        if (empty($data['repeat_monthly'])) {
            $data['amount'] = $total;
            $data['monthly_amount'] = null;
            $data['next_due_date'] = null;

            return $data;
        }

        $data['amount'] = $total;
        $data['monthly_amount'] = $monthly;

        // Is this category already a repeating bill? If so, this recording IS
        // a payment for that bill: push its next due date on by however many
        // months were paid (rent is often paid 3, 6 or 12 months ahead) and
        // let this row take over as the one reminder. That is what stops a
        // paid bill from reminding you a second time.
        $existing = Expense::query()
            ->where('repeat_monthly', true)
            ->whereNotNull('next_due_date')
            ->where('expense_category_id', $data['expense_category_id'])
            ->when($exceptId, fn ($query, $id) => $query->where('id', '!=', $id))
            ->when($data['branch_id'] ?? null, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->orderBy('id')
            ->first();

        if ($existing) {
            $newDue = $this->pushedDueDate($existing, $months);

            $existing->update([
                'repeat_monthly' => false,
                'next_due_date' => null,
            ]);

            $data['next_due_date'] = $newDue;

            return $data;
        }

        $data['next_due_date'] = \Carbon\Carbon::parse($data['expense_date'])
            ->addMonthsNoOverflow($months);

        return $data;
    }

    /**
     * Where a bill's next reminder lands after a payment. The bill keeps its
     * day of the month; a bill that was already late lands on the next future
     * cycle rather than in the past.
     */
    protected function pushedDueDate(Expense $bill, int $months): \Carbon\Carbon
    {
        $today = \Carbon\Carbon::today();
        $base = $bill->next_due_date?->copy() ?: $bill->expense_date->copy();

        $new = $base->addMonthsNoOverflow(max(1, $months));
        while ($new->lt($today)) {
            $new->addMonthNoOverflow();
        }

        return $new;
    }

    protected function authorizeBranch(Expense $expense): void
    {
        if ($branchId = auth()->user()->branch_id) {
            abort_unless($expense->branch_id === $branchId, 403);
        }
    }

    /**
     * Behind-the-scenes posting: expense account increased, the paying
     * method's account reduced. Balances by construction.
     */
    protected function postExpense(Expense $expense): void
    {
        $account = Account::where('code', $expense->category->account_code)->firstOrFail();

        $this->accounting->postEntry(
            'Expense: ' . $expense->category->name,
            $expense->branch_id,
            'expense',
            $expense->id,
            [
                ['account_code' => $account->code, 'debit' => (float) $expense->amount, 'credit' => 0, 'desc' => $expense->category->name],
                ['account_code' => Payment::accountCodeFor($expense->payment_method), 'debit' => 0, 'credit' => (float) $expense->amount, 'desc' => Payment::METHODS[$expense->payment_method]],
            ]
        );
    }

    protected function reverseExpense(Expense $expense): void
    {
        $account = Account::where('code', $expense->category->account_code)->firstOrFail();

        $this->accounting->postEntry(
            'Reversal of expense: ' . $expense->category->name,
            $expense->branch_id,
            'expense_reversal',
            $expense->id,
            [
                ['account_code' => $account->code, 'debit' => 0, 'credit' => (float) $expense->amount, 'desc' => $expense->category->name],
                ['account_code' => Payment::accountCodeFor($expense->payment_method), 'debit' => (float) $expense->amount, 'credit' => 0, 'desc' => Payment::METHODS[$expense->payment_method]],
            ]
        );
    }
}
