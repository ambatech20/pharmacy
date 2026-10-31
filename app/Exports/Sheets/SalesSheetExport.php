<?php

namespace App\Exports\Sheets;

use App\Models\IssuedItem;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SalesSheetExport implements FromCollection, WithTitle, WithHeadings, WithMapping
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

    // BUG FIX (Laravel Excel v4): FromCollection::collection() now
    // declares an explicit Illuminate\Support\Enumerable return type on
    // the interface itself (Eloquent's Collection already implements
    // Enumerable, so this is purely a type-declaration fix, no behavior
    // change) — the implementing method has to match exactly.
    public function collection(): Enumerable
    {
        return IssuedItem::with(['purchaseBatch.drug', 'customer'])
            // Null branch = Owner/Admin consolidated view across all branches.
            ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))
            ->whereMonth('issue_date', $this->month)
            ->whereYear('issue_date', $this->year)
            ->orderBy('issue_date')
            ->get();
    }

    public function map($sale): array
    {
        return [
            \Carbon\Carbon::parse($sale->issue_date)->format('Y-m-d'),
            $sale->purchaseBatch->drug->name ?? '—',
            $sale->customer->name ?? 'Walk-in',
            $sale->quantity_issued,
            round($sale->selling_price_per_unit, 2),
            round($sale->total_amount, 2),
        ];
    }

    public function headings(): array
    {
        return ['Date', 'Drug', 'Customer', 'Quantity', 'Unit Price', 'Total'];
    }

    public function title(): string
    {
        return 'Sales';
    }
}
