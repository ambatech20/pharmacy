<?php

namespace App\Exports\Sheets;

use App\Models\PurchaseBatch;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PurchasesSheetExport implements FromCollection, WithTitle, WithHeadings, WithMapping
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

    // Same v4 fix as SalesSheetExport — explicit Enumerable return type.
    public function collection(): Enumerable
    {
        return PurchaseBatch::with(['drug', 'supplier'])
            // Null branch = Owner/Admin consolidated view across all branches.
            ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))
            ->whereMonth('purchase_date', $this->month)
            ->whereYear('purchase_date', $this->year)
            ->orderBy('purchase_date')
            ->get();
    }

    public function map($batch): array
    {
        return [
            \Carbon\Carbon::parse($batch->purchase_date)->format('Y-m-d'),
            $batch->drug->name ?? '—',
            $batch->supplier->name ?? '—',
            $batch->batch_number,
            $batch->quantity_received,
            round($batch->cost_per_unit, 2),
            round($batch->quantity_received * $batch->cost_per_unit, 2),
            // Part A — how it was paid and whether anything is still owed.
            $batch->payment_label(),
            $batch->status_label(),
            (float) $batch->amount_paid,
            (float) $batch->balance_owed,
            $batch->due_date ? \Carbon\Carbon::parse($batch->due_date)->format('Y-m-d') : '',
        ];
    }

    public function headings(): array
    {
        return ['Date', 'Drug', 'Supplier', 'Batch #', 'Quantity Received', 'Cost/Unit', 'Total Cost',
                'How Paid', 'Payment Status', 'Amount Paid', 'Balance Owed', 'Due Date'];
    }

    public function title(): string
    {
        return 'Purchases';
    }
}
