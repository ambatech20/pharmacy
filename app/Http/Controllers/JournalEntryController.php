<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function index()
    {
        // Branch scope: users with a branch see only their branch;
        // owner/admin (branch_id null) sees all branches. A plain
        // ->where('branch_id', null) would compile to "branch_id IS NULL"
        // and show an empty list to Owner/Admin.
        $branchId = auth()->user()->branch_id;

        $entries = JournalEntry::with('ledgers.account')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->latest()
            ->paginate(20);

        return view('journal-entries.index', compact('entries'));
    }

    public function create()
    {
        $accounts = Account::where('active', true)->orderBy('code')->get();

        if ($accounts->isEmpty()) {
            return redirect()->route('accounts.create')
                ->with('error', 'Add at least one account before posting a journal entry.');
        }

        return view('journal-entries.create', compact('accounts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.debit' => 'nullable|numeric|min:0',
            'lines.*.credit' => 'nullable|numeric|min:0',
        ]);

        // journal_entries.branch_id is NOT NULL, so a single-branch install
        // stamps the entry with the pharmacy's one branch.
        $branchId = ($validated['branch_id'] ?? null)
            ?: auth()->user()->branch_id
            ?: Branch::query()->orderBy('id')->value('id');

        if (!$branchId) {
            return back()
                ->withErrors(['branch_id' => 'No branch is set up yet — run the installer first.'])
                ->withInput();
        }

        // Build the [account_code => ..., debit => ..., credit => ...] shape
        // AccountingService::postEntry() expects, resolving each selected
        // account_id to its code.
        $lines = [];
        foreach ($validated['lines'] as $line) {
            $account = Account::findOrFail($line['account_id']);
            $lines[] = [
                'account_code' => $account->code,
                'debit' => $line['debit'] ?? 0,
                'credit' => $line['credit'] ?? 0,
                'desc' => $validated['description'],
            ];
        }

        try {
            $this->accountingService->postEntry(
                $validated['description'],
                $branchId,
                'manual',
                null,
                $lines
            );

            return redirect()->route('journal-entries.index')->with('success', 'Journal entry posted.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }
}
