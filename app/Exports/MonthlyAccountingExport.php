<?php

namespace App\Exports;

use App\Exports\Sheets\SummarySheetExport;
use App\Exports\Sheets\SalesSheetExport;
use App\Exports\Sheets\PurchasesSheetExport;
use App\Exports\Sheets\ExpensesSheetExport;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

// BUG FIX (Laravel Excel v4): WithMultipleSheets alone no longer satisfies
// Excel::download()'s type requirement. v4 introduced a new marker
// interface, Maatwebsite\Excel\Concerns\Export, which data-source concerns
// like FromCollection/FromArray now extend automatically — but
// WithMultipleSheets does not, since it isn't itself a data source. The
// top-level multi-sheet export class has to implement Export explicitly.
class MonthlyAccountingExport implements Export, WithMultipleSheets
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

    public function sheets(): array
    {
        return [
            new SummarySheetExport($this->branchId, $this->month, $this->year),
            new SalesSheetExport($this->branchId, $this->month, $this->year),
            new PurchasesSheetExport($this->branchId, $this->month, $this->year),
            // PART C — Expenses sheet.
            new ExpensesSheetExport($this->branchId, $this->month, $this->year),
        ];
    }
}
