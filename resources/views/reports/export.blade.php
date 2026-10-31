@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-1">Export Accounting Report</h1>
    <p class="text-sm text-[#64748b] mb-6">Download an Excel workbook for each ticked month — sales, purchases, expenses and a profit summary, ready to hand to the owner or an accountant.</p>

    <form method="GET" action="{{ route('reports.export.download') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Year</label>
            <select name="year" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30" required>
                @foreach(range(now()->year, now()->year - 5) as $y)
                    <option value="{{ $y }}" @selected($y == now()->year)>{{ $y }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <span class="block text-sm font-medium text-[#1E2530] mb-1.5">Months</span>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                @foreach(\App\Support\ReportPeriod::MONTH_NAMES as $number => $name)
                    <label class="inline-flex items-center gap-2 min-h-[44px] px-3 rounded-lg border border-gray-200 bg-gray-50/60 text-sm text-[#1E2530] cursor-pointer hover:border-[var(--brand-primary)]/40">
                        <input type="checkbox"
                               name="months[]"
                               value="{{ $number }}"
                               @checked($number === (int) now()->month)
                               class="rounded border-gray-300 text-[var(--brand-primary)] focus:ring-[var(--brand-primary)]">
                        <span>{{ $name }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <p class="text-xs text-[#64748b]">
            One month downloads a single workbook. Two or more months download one ZIP containing one workbook per month.
            Each workbook has four tabs: Summary, Sales, Purchases and Expenses.
        </p>

        <button type="submit"
                class="inline-flex items-center gap-2 bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-medium text-sm py-2.5 px-5 rounded-lg transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" />
            </svg>
            Download Report
        </button>
    </form>
</div>
@endsection
