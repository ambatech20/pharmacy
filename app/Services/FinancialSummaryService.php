<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Ledger;
use Carbon\Carbon;

/**
 * Profit & loss and balance sheet, read straight out of the ledger.
 *
 * Nothing here is typed in by hand or cached in a report table - every figure
 * is the sum of the ledger lines PMAS wrote when the money actually moved, so
 * the two statements can never disagree with the voucher list underneath them.
 *
 * Sign convention: a balance is positive when the account holds a normal
 * balance for its type (assets and expenses go up on the debit side, revenue,
 * liabilities and equity on the credit side).
 *
 * A null branch id means the consolidated Owner/Admin view across every
 * branch, matching MonthlyAccountingExport.
 */
class FinancialSummaryService
{
    /**
     * Cost of goods sold. Everything else of type "expense" is treated as an
     * operating expense: shrinkage, till differences and the 60xx running
     * costs recorded on the Expenses page.
     */
    protected const COGS_CODE = '5000';

    /** Cash on hand plus everything collected through Telebirr, bank and card. */
    protected const CASH_CODES = ['1001', '1010', '1011', '1012', '1013'];

    protected const RECEIVABLE_CODES = ['1002'];
    protected const INVENTORY_CODES = ['1200'];
    protected const PAYABLE_CODES = ['2000'];
    protected const EQUITY_CODES = ['3000'];

    /**
     * Profit and loss for one period.
     *
     * Accepts either a single [start, end] pair or a list of pairs (one per
     * ticked month). The list form keeps the gaps out: January + March must
     * not quietly include February.
     *
     * @param  array{0: Carbon, 1: Carbon}|array<int, array{0: Carbon, 1: Carbon}>|null  $ranges  null = all time
     */
    public function profitAndLoss(?int $branchId, ?array $ranges): array
    {
        $balances = $this->balances($branchId, $ranges);

        $revenue = $balances->where('type', 'revenue')->sum('balance');
        $cogs = $balances->where('code', self::COGS_CODE)->sum('balance');
        $operating = $balances
            ->where('type', 'expense')
            ->where('code', '!=', self::COGS_CODE)
            ->sum('balance');

        $gross = $revenue - $cogs;

        return [
            'revenue' => $this->round($revenue),
            'cogs' => $this->round($cogs),
            'gross_profit' => $this->round($gross),
            'operating_expenses' => $this->round($operating),
            'net_profit' => $this->round($gross - $operating),
        ];
    }

    /**
     * Balance sheet as at the end of the period - a snapshot, so it is
     * cumulative from the beginning of time up to $asOf rather than covering
     * the period on its own.
     */
    public function balanceSheet(?int $branchId, ?Carbon $asOf = null): array
    {
        $balances = $this->balances($branchId, null, $asOf);

        $cash = $balances->whereIn('code', self::CASH_CODES)->sum('balance');
        $receivables = $balances->whereIn('code', self::RECEIVABLE_CODES)->sum('balance');
        $inventory = $balances->whereIn('code', self::INVENTORY_CODES)->sum('balance');

        $payables = $balances->whereIn('code', self::PAYABLE_CODES)->sum('balance');
        $equity = $balances->whereIn('code', self::EQUITY_CODES)->sum('balance');

        // Profit earned and never withdrawn. Because every journal entry
        // balances, assets must equal payables + equity + this - which is what
        // makes the totalAssets / totalLiabilitiesAndEquity comparison below a
        // real check rather than decoration.
        $profit = $balances->where('type', 'revenue')->sum('balance')
            - $balances->where('type', 'expense')->sum('balance');

        $assets = $cash + $receivables + $inventory;

        return [
            'cash' => $this->round($cash),
            'receivables' => $this->round($receivables),
            'inventory' => $this->round($inventory),
            'total_assets' => $this->round($assets),
            'payables' => $this->round($payables),
            'equity' => $this->round($equity),
            'retained_profit' => $this->round($profit),
            'total_liabilities_and_equity' => $this->round($payables + $equity + $profit),
            'as_of' => $asOf ?? now(),
        ];
    }

    /**
     * Every account balance, keyed by code.
     *
     * One grouped query rather than one per account: this runs on every
     * financial report load and the voucher list behind it is already paged.
     *
     * @param  array{0: Carbon, 1: Carbon}|array<int, array{0: Carbon, 1: Carbon}>|null  $ranges  bounded period(s), or null
     * @param  Carbon|null  $asOf  inclusive upper bound when unbounded
     * @return \Illuminate\Support\Collection<string, array{code: string, name: string, type: string, balance: float}>
     */
    protected function balances(?int $branchId, ?array $ranges, ?Carbon $asOf = null)
    {
        $rows = Ledger::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'ledgers.journal_entry_id')
            ->when($branchId, fn ($query) => $query->where('ledgers.branch_id', $branchId))
            ->when(
                $ranges,
                function ($query) use ($ranges) {
                    $ranges = $this->normaliseRanges($ranges);

                    $query->where(function ($query) use ($ranges) {
                        foreach ($ranges as $range) {
                            $query->orWhereBetween('journal_entries.date', $range);
                        }
                    });
                },
                fn ($query) => $query->when($asOf, fn ($q) => $q->where('journal_entries.date', '<=', $asOf))
            )
            ->groupBy('ledgers.account_id')
            ->selectRaw('ledgers.account_id, SUM(ledgers.debit) as debit, SUM(ledgers.credit) as credit')
            ->get();

        $accountIds = $rows->pluck('account_id')->all();

        $accounts = $accountIds
            ? Account::whereIn('id', $accountIds)->get()->keyBy('id')
            : collect();

        return $rows->map(function ($row) use ($accounts) {
            $account = $accounts[$row->account_id] ?? null;
            $debit = (float) $row->debit;
            $credit = (float) $row->credit;

            $net = match ($account?->type) {
                // Debit-natured: a positive balance means the account holds more.
                'asset', 'expense' => $debit - $credit,
                default => $credit - $debit,
            };

            return [
                'code' => (string) ($account?->code ?? ''),
                'name' => (string) ($account?->name ?? ''),
                'type' => (string) ($account?->type ?? ''),
                'balance' => $net,
            ];
        })->keyBy('code');
    }

    /**
     * Accept either [start, end] or [[start, end], ...] and always return the
     * list form, so the query builder only has one shape to deal with.
     *
     * @param  array{0: Carbon, 1: Carbon}|array<int, array{0: Carbon, 1: Carbon}>  $ranges
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    protected function normaliseRanges(array $ranges): array
    {
        if (isset($ranges[0]) && $ranges[0] instanceof Carbon) {
            return [$ranges];
        }

        return array_values(array_filter(
            $ranges,
            fn ($range) => is_array($range) && count($range) === 2,
        ));
    }

    protected function round(float $value): float
    {
        return round($value, 2);
    }

    /**
     * What every line on these two statements means in PMAS terms.
     *
     * This is a glossary, not a translation: "accounts payable" on its own
     * means nothing to a pharmacist, but "owed to suppliers" is something they
     * can act on. Ordered the way the statements read, so the table and the
     * figures above it match line for line.
     *
     * @return array<int, array{line: string, meaning: string}>
     */
    public static function terms(): array
    {
        return [
            ['line' => 'Sales revenue', 'meaning' => 'Everything you took over the counter for drugs, after any discount you gave. What the customers paid you - not what the drugs cost you.'],
            ['line' => 'Cost of goods sold', 'meaning' => 'What those same drugs cost you when you bought them in. Not what you owe the supplier today: the cost of the stock you actually handed over.'],
            ['line' => 'Gross profit', 'meaning' => 'Sales revenue minus cost of goods sold. What is left from selling drugs before a single bill is paid.'],
            ['line' => 'Operating expenses', 'meaning' => 'The running costs you record on the Expenses page: rent, electricity, water, salaries, transport, internet and phone. It also picks up damaged or expired stock and any difference found at till close.'],
            ['line' => 'Net profit', 'meaning' => 'Gross profit minus operating expenses. What the pharmacy actually earned for the period.'],
            ['line' => 'Cash, Telebirr and bank', 'meaning' => 'Money in the till, plus everything collected by Telebirr, CBE Birr, bank transfer and card.'],
            ['line' => 'Accounts receivable', 'meaning' => 'Money customers owe you - the credit you allowed when someone bought but had not paid yet.'],
            ['line' => 'Inventory', 'meaning' => 'The drugs still on your shelf, valued at what you paid for them. Not at the selling price.'],
            ['line' => 'Accounts payable', 'meaning' => 'Owed to suppliers. Stock or costs you have already taken but have not paid for yet.'],
            ['line' => 'Owner equity', 'meaning' => 'Your own money put into the pharmacy, plus the opening balances you entered when you set it up.'],
            ['line' => 'Profit kept in the business', 'meaning' => 'Profit you have earned but not taken out. It stays in the pharmacy until you withdraw it.'],
            ['line' => 'Debit', 'meaning' => 'The left-hand side of an entry: money coming in, or a cost being charged to the pharmacy.'],
            ['line' => 'Credit', 'meaning' => 'The right-hand side of an entry: money going out, or something the pharmacy is owed. Every entry has equal debits and credits.'],
            ['line' => 'Journal entry', 'meaning' => 'One recorded movement of money. PMAS writes one automatically for every sale, purchase, expense, return and till closing - you never add these by hand.'],
            ['line' => 'Voucher #', 'meaning' => 'The number of that entry in the books. Quote it when you want to trace or correct one transaction.'],
            ['line' => 'Source', 'meaning' => 'Which screen the entry came from: sale, purchase, expense, return, till closing, or a manual entry.'],
        ];
    }
}
