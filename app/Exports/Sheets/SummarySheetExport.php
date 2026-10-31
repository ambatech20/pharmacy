<?php

namespace App\Exports\Sheets;

use App\Models\IssuedItem;
use App\Models\PurchaseBatch;
use App\Models\Expense;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SummarySheetExport implements FromArray, WithTitle, WithHeadings, WithStyles
{
    protected $branchId;
    protected $month;
    protected $year;

    public function __construct($branchId, $month, $year)
    {
        $this->branchId = $branchId;
        $this->month = $month;
        $this->year = $year;
    }

    public function array(): array
    {
        // Null branch = Owner/Admin consolidated view across all branches.
        $sales = IssuedItem::with('purchaseBatch')
            ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))
            ->whereMonth('issue_date', $this->month)
            ->whereYear('issue_date', $this->year)
            ->get();

        $purchases = PurchaseBatch::query()
            ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))
            ->whereMonth('purchase_date', $this->month)
            ->whereYear('purchase_date', $this->year)
            ->get();

        $totalSalesRevenue = $sales->sum('total_amount');

        // COGS is derived from each sale's originating batch cost — batches
        // don't change cost after purchase, so this is accurate even though
        // issued_items itself doesn't store a unit_cost column.
        $totalCogs = $sales->sum(fn ($sale) => $sale->quantity_issued * ($sale->purchaseBatch->cost_per_unit ?? 0));

        $totalPurchaseCost = $purchases->sum(fn ($batch) => $batch->quantity_received * $batch->cost_per_unit);

        $grossProfit = $totalSalesRevenue - $totalCogs;

        // PART C — total expenses for the period (soft-deleted ones are
        // excluded because their posting was reversed).
        $totalExpenses = (float) Expense::query()
            ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))
            ->whereMonth('expense_date', $this->month)
            ->whereYear('expense_date', $this->year)
            ->sum('amount');

        return [
            ['Period', \Carbon\Carbon::create($this->year, $this->month, 1)->format('F Y')],
            ['', ''],
            ['Total Sales Revenue', round($totalSalesRevenue, 2)],
            ['Total Cost of Goods Sold (COGS)', round($totalCogs, 2)],
            ['Gross Profit', round($grossProfit, 2)],
            ['', ''],
            ['Total Purchases (New Stock Cost)', round($totalPurchaseCost, 2)],
            ['Total Expenses', round($totalExpenses, 2)],
            ['Net (Gross Profit - Expenses)', round($grossProfit - $totalExpenses, 2)],
            ['Number of Sales Transactions', $sales->count()],
            ['Number of Purchase Batches Received', $purchases->count()],
        ];
    }

    public function headings(): array
    {
        return ['Metric', 'Amount'];
    }

    public function title(): string
    {
        return 'Summary';
    }

    // BUG FIX (Laravel Excel v4): WithStyles::styles() now declares an
    // explicit ?array return type on the interface itself. The
    // implementing method has to match exactly or PHP throws a fatal
    // "Declaration must be compatible" error.
    public function styles(Worksheet $sheet): ?array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
