@extends('layouts.app')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-[#1E2530]">Financial Report</h1>
    <p class="text-sm text-[#64748b] mt-1">What the pharmacy earned, what it is owed, and every entry behind those figures.</p>
</div>

<div class="flex gap-2 mb-4 text-sm">
    <a href="{{ route('reports.inventory') }}" class="px-3 py-1.5 rounded-lg font-medium text-[#64748b] hover:bg-gray-100">Inventory</a>
    <a href="{{ route('reports.financial') }}" class="px-3 py-1.5 rounded-lg font-medium bg-[var(--brand-primary)]/10 text-[var(--brand-primary)]">Financial</a>
    <a href="{{ route('reports.sales') }}" class="px-3 py-1.5 rounded-lg font-medium text-[#64748b] hover:bg-gray-100">Sales</a>
</div>

@include('partials.period-filter', [
    'action' => route('reports.financial'),
    'hint' => 'This filter applies to both statements and to the debit and credit list. Picking a month without a year uses '
        . now()->year . '. Leaving both on "All" reports everything since the books were opened.',
])

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
    {{-- Profit and loss: covers the period. --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100">
            <thead>
                <tr class="bg-[#dcfce7]">
                    <th colspan="2" class="px-5 py-2.5 text-left text-xs font-bold text-[#14532d] uppercase tracking-wide">
                        Profit and loss &middot; {{ $period->label() }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <tr>
                    <td class="px-5 py-3 text-sm text-[#1E2530]">Sales revenue</td>
                    <td class="px-5 py-3 text-sm text-right font-mono text-[#1E2530]">{{ number_format($profitAndLoss['revenue'], 2) }}</td>
                </tr>
                <tr>
                    <td class="px-5 py-3 text-sm text-[#1E2530]">Cost of goods sold</td>
                    <td class="px-5 py-3 text-sm text-right font-mono text-[#1E2530]">{{ number_format($profitAndLoss['cogs'], 2) }}</td>
                </tr>
                <tr class="bg-gray-50/50">
                    <td class="px-5 py-3 text-sm font-semibold text-[#1E2530]">Gross profit</td>
                    <td class="px-5 py-3 text-sm text-right font-mono font-semibold {{ $profitAndLoss['gross_profit'] < 0 ? 'text-red-600' : 'text-[#1E2530]' }}">{{ number_format($profitAndLoss['gross_profit'], 2) }}</td>
                </tr>
                <tr>
                    <td class="px-5 py-3 text-sm text-[#1E2530]">Operating expenses</td>
                    <td class="px-5 py-3 text-sm text-right font-mono text-[#1E2530]">{{ number_format($profitAndLoss['operating_expenses'], 2) }}</td>
                </tr>
                <tr class="bg-gray-50/50">
                    <td class="px-5 py-3 text-sm font-bold text-[#1E2530]">Net profit</td>
                    <td class="px-5 py-3 text-sm text-right font-mono font-bold {{ $profitAndLoss['net_profit'] < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ number_format($profitAndLoss['net_profit'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Balance sheet: a snapshot at the end of the period. --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100">
            <thead>
                <tr class="bg-gray-100">
                    <th colspan="2" class="px-5 py-2.5 text-left text-xs font-bold text-[#1E2530] uppercase tracking-wide">
                        Balance sheet &middot; as at {{ \Carbon\Carbon::parse($balanceSheet['as_of'])->format('M d, Y') }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <tr>
                    <td class="px-5 py-3 text-sm text-[#1E2530]">Cash, Telebirr and bank</td>
                    <td class="px-5 py-3 text-sm text-right font-mono text-[#1E2530]">{{ number_format($balanceSheet['cash'], 2) }}</td>
                </tr>
                <tr>
                    <td class="px-5 py-3 text-sm text-[#1E2530]">Accounts receivable</td>
                    <td class="px-5 py-3 text-sm text-right font-mono text-[#1E2530]">{{ number_format($balanceSheet['receivables'], 2) }}</td>
                </tr>
                <tr>
                    <td class="px-5 py-3 text-sm text-[#1E2530]">Inventory</td>
                    <td class="px-5 py-3 text-sm text-right font-mono text-[#1E2530]">{{ number_format($balanceSheet['inventory'], 2) }}</td>
                </tr>
                <tr class="bg-gray-50/50">
                    <td class="px-5 py-3 text-sm font-semibold text-[#1E2530]">Total assets</td>
                    <td class="px-5 py-3 text-sm text-right font-mono font-semibold text-[#1E2530]">{{ number_format($balanceSheet['total_assets'], 2) }}</td>
                </tr>
                <tr>
                    <td class="px-5 py-3 text-sm text-[#1E2530]">Accounts payable <span class="text-[#64748b]">(owed to suppliers)</span></td>
                    <td class="px-5 py-3 text-sm text-right font-mono text-[#1E2530]">{{ number_format($balanceSheet['payables'], 2) }}</td>
                </tr>
                <tr>
                    <td class="px-5 py-3 text-sm text-[#1E2530]">Owner equity</td>
                    <td class="px-5 py-3 text-sm text-right font-mono text-[#1E2530]">{{ number_format($balanceSheet['equity'], 2) }}</td>
                </tr>
                <tr>
                    <td class="px-5 py-3 text-sm text-[#1E2530]">Profit kept in the business</td>
                    <td class="px-5 py-3 text-sm text-right font-mono text-[#1E2530]">{{ number_format($balanceSheet['retained_profit'], 2) }}</td>
                </tr>
                <tr class="bg-gray-50/50">
                    <td class="px-5 py-3 text-sm font-semibold text-[#1E2530]">Total liabilities and equity</td>
                    <td class="px-5 py-3 text-sm text-right font-mono font-semibold text-[#1E2530]">{{ number_format($balanceSheet['total_liabilities_and_equity'], 2) }}</td>
                </tr>
            </tbody>
        </table>

        @php
            // Because every journal entry balances, these two must agree. If
            // they ever stop, something has been written to the ledger by hand.
            $outBy = round($balanceSheet['total_assets'] - $balanceSheet['total_liabilities_and_equity'], 2);
        @endphp
        @if(abs($outBy) >= 0.01)
            <p class="px-5 py-2.5 text-xs bg-amber-50 text-amber-800 border-t border-gray-100">
                Assets and liabilities do not match by {{ number_format($outBy, 2) }}. Check the manual entries below.
            </p>
        @endif
    </div>
</div>

{{-- The voucher list. Collapsed by default: the two statements are what the
     page is for, and twenty rows of vouchers bury them. --}}
<details class="group bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <summary class="flex items-center justify-between gap-3 px-5 py-4 cursor-pointer min-h-[44px] hover:bg-gray-50/50">
        <span>
            <span class="block text-sm font-semibold text-[#1E2530]">Debit and credit list</span>
            <span class="block text-xs text-[#64748b] mt-0.5">
                {{ number_format((int) $totals->entries) }} voucher{{ (int) $totals->entries === 1 ? '' : 's' }}
                &middot; {{ $period->label() }}
                &middot; ETB {{ number_format((float) $totals->debit, 2) }} debit / ETB {{ number_format((float) $totals->credit, 2) }} credit
            </span>
        </span>
        <svg class="w-5 h-5 text-[#64748b] shrink-0 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
    </summary>

    <div class="overflow-x-auto border-t border-gray-100">
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50/50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Voucher #</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Date</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Description</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Source</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Debit</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Credit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($entries as $entry)
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-5 py-3.5 text-sm font-mono text-[#64748b]">{{ $entry->voucher_number }}</td>
                        <td class="px-5 py-3.5 text-sm text-[#64748b]">{{ \Carbon\Carbon::parse($entry->date)->format('M d, Y') }}</td>
                        <td class="px-5 py-3.5 text-sm text-[#1E2530]">{{ $entry->description }}</td>
                        <td class="px-5 py-3.5 text-sm">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-[#64748b]">
                                {{ ucfirst($entry->reference_type ?? 'manual') }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-right font-mono text-[#1E2530]">{{ number_format($entry->total_debit, 2) }}</td>
                        <td class="px-5 py-3.5 text-sm text-right font-mono text-[#1E2530]">{{ number_format($entry->total_credit, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-sm text-[#64748b]">No journal entries for this period.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-5 py-4 border-t border-gray-100">
        {{ $entries->links() }}
    </div>
</details>

{{-- Kept at the bottom: it is a glossary for the figures above, not part of
     the report. Open on arrival because there is nothing below it to hide. --}}
<details open class="group bg-white rounded-xl border border-gray-100 shadow-sm mt-4 overflow-hidden">
    <summary class="flex items-center justify-between gap-3 px-5 py-4 cursor-pointer min-h-[44px] hover:bg-gray-50/50">
        <span>
            <span class="block text-sm font-semibold text-[#1E2530]">What these terms mean</span>
            <span class="block text-xs text-[#64748b] mt-0.5">Every line above, in plain words</span>
        </span>
        <svg class="w-5 h-5 text-[#64748b] shrink-0 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
    </summary>

    <div class="overflow-x-auto border-t border-gray-100">
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50/50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide w-56">Term</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">What it means</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($terms as $term)
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-5 py-3 text-sm font-medium text-[#1E2530] align-top">{{ $term['line'] }}</td>
                        <td class="px-5 py-3 text-sm text-[#64748b] leading-relaxed">{{ $term['meaning'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</details>
@endsection
