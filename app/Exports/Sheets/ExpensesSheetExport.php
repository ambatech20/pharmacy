<?php

namespace App\Exports\Sheets;

use App\Models\Expense;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * PART C — Expenses sheet in the monthly Excel accounting export.
 * Same shape/branch+month scoping as PurchasesSheetExport. Soft-deleted
 * (removed) expenses are excluded because their posting was reversed.
 */
class ExpensesSheetExport implements FromCollection, WithTitle, WithHeadings, WithMapping
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

    public function collection(): Enumerable
    {
        return Expense::with('category')
            // Null branch = Owner/Admin consolidated view across all branches.
            ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))
            ->whereMonth('expense_date', $this->month)
            ->whereYear('expense_date', $this->year)
            ->orderBy('expense_date')
            ->get();
    }

    public function map($expense): array
    {
        return [
            \Carbon\Carbon::parse($expense->expense_date)->format('Y-m-d'),
            $expense->category->name ?? '—',
            round((float) $expense->amount, 2),
            $expense->payment_label(),
            $expense->payment_reference ?? '',
            $expense->check_date ? \Carbon\Carbon::parse($expense->check_date)->format('Y-m-d') : '',
            $expense->check_bank ?? '',
            $expense->note ?? '',
            $expense->repeat_monthly ? 'Yes' : 'No',
        ];
    }

    public function headings(): array
    {
        return ['Date', 'Category', 'Amount', 'How Paid', 'Reference', 'Check Date', 'Check Bank', 'Note', 'Repeats Monthly'];
    }

    public function title(): string
    {
        return 'Expenses';
    }
}
