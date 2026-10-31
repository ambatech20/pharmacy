<?php

namespace App\Http\Controllers;

use App\Models\IssuedItem;
use App\Models\PurchaseBatch;
use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Models\Account;
use App\Exports\MonthlyAccountingExport;
use App\Services\FinancialSummaryService;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(
        protected FinancialSummaryService $summary,
    ) {}

    public function inventory()
    {
        $user = auth()->user();
        $query = PurchaseBatch::with(['drug', 'branch'])->where('quantity_remaining', '>', 0);
        $query->with('product');
        if ($request = request()) {
            if ($request->input('type') === 'drug') $query->whereNotNull('drug_id');
            if ($request->input('type') === 'product') $query->whereNotNull('product_id');
        }
        if ($user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }
        $stock = $query->with('product')->orderBy('expiry_date')->get();

        return view('reports.inventory', compact('stock'));
    }

    public function financial(Request $request)
    {
        $user = auth()->user();
        $period = ReportPeriod::fromRequest($request, $user->branch_id);

        $query = JournalEntry::query();
        if ($user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }
        $this->applyPeriod($query, $period, 'date');

        $entries = $query->latest()->paginate(20)->withQueryString();

        // Totals for the collapsed summary bar - the whole period, not the
        // twenty rows on screen, or the bar would contradict the table.
        $totals = (clone $query)
            ->reorder()
            ->selectRaw('COALESCE(SUM(total_debit), 0) as debit, COALESCE(SUM(total_credit), 0) as credit, COUNT(*) as entries')
            ->first();

        $summary = $this->summary;
        $profitAndLoss = $summary->profitAndLoss($user->branch_id, $period->ranges());
        $balanceSheet = $summary->balanceSheet($user->branch_id, $period->end());
        $terms = FinancialSummaryService::terms();

        return view('reports.financial', compact(
            'entries', 'totals', 'period', 'profitAndLoss', 'balanceSheet', 'terms'
        ));
    }

    public function sales(Request $request)
    {
        $user = auth()->user();
        $period = ReportPeriod::fromRequest($request, $user->branch_id);

        $query = IssuedItem::with(['purchaseBatch.drug', 'purchaseBatch.product', 'customer', 'order.payments']);
        $totalQuery = IssuedItem::query();
        if ($user->branch_id) {
            $query->where('branch_id', $user->branch_id);
            $totalQuery->where('branch_id', $user->branch_id);
        }
        $this->applyPeriod($query, $period, 'issue_date');
        $this->applyPeriod($totalQuery, $period, 'issue_date');

        // The grand total has to cover every matching row, not just the page.
        $total = (float) $totalQuery->sum('total_amount');
        $sales = $query->latest()->paginate(20)->withQueryString();

        return view('reports.sales', compact('sales', 'total', 'period'));
    }

    /**
     * Restrict a query to the ticked months. One OR-ed between per month, so
     * January + March does not pick up February on the way through.
     */
    private function applyPeriod($query, ReportPeriod $period, string $column): void
    {
        $ranges = $period->ranges();

        if ($ranges === null) {
            return;
        }

        $query->where(function ($query) use ($ranges, $column) {
            foreach ($ranges as $range) {
                $query->orWhereBetween($column, $range);
            }
        });
    }

    public function exportForm()
    {
        return view('reports.export');
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'months' => 'nullable|array|min:1',
            'months.*' => 'integer|min:1|max:12',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $months = array_values(array_unique(array_map('intval', $validated['months'] ?? [])));
        if ($months === [] && isset($validated['month'])) {
            $months = [(int) $validated['month']];
        }
        sort($months);

        if ($months === []) {
            return back()->withErrors(['months' => 'Tick at least one month to export.']);
        }

        // Null for Owner/Admin, meaning "all branches". Every sheet in
        // MonthlyAccountingExport treats a null branch as a consolidated
        // view rather than a literal "branch_id IS NULL" match.
        $branchId = auth()->user()->branch_id;
        $year = (int) $validated['year'];

        if (count($months) === 1) {
            return Excel::download(
                new MonthlyAccountingExport($branchId, $months[0], $year),
                $this->exportName($year, $months[0])
            );
        }

        // One workbook per ticked month, bundled so the download is a single
        // file. Excel's own binary is read straight from the exporter.
        // tempnam() creates the base name; the .zip suffix is what lets the
        // response advertise the right content type, so the empty base file
        // is removed rather than left behind.
        $temp = tempnam(sys_get_temp_dir(), 'pmas-reports-');
        $zipPath = $temp . '.zip';
        @unlink($temp);

        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Could not build the report archive.');
        }

        foreach ($months as $month) {
            $zip->addFromString(
                $this->exportName($year, $month),
                Excel::raw(new MonthlyAccountingExport($branchId, $month, $year), \Maatwebsite\Excel\Excel::XLSX)
            );
        }

        $zip->close();

        return response()
            ->download($zipPath, 'pmas-accounting-' . $year . '-' . count($months) . '-months.zip')
            ->deleteFileAfterSend(true);
    }

    private function exportName(int $year, int $month): string
    {
        return 'pmas-accounting-' . $year . '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . '.xlsx';
    }
}
