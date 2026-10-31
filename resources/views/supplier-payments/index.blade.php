@extends('layouts.app')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-[#1E2530]">Owed to suppliers</h1>
    <p class="text-sm text-[#64748b] mt-1">Purchases marked "Pay later" that are still not fully paid. Totals update automatically as you record payments.</p>
</div>

@if(session('success'))
    <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-100 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
@endif

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-4 flex items-center justify-between">
        <div><h2 class="font-bold text-[#1E2530]">Total owed to suppliers</h2><p class="text-xs text-[#64748b] mt-0.5">Across all open purchases</p></div>
        <p class="text-xl font-bold text-[#1E2530]">ETB {{ number_format($totalOwed, 2) }}</p>
    </div>
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    @if(count($suppliers))
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50/60">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[#64748b]">Supplier</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-[#64748b]">Total owed</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[#64748b]">Nearest due date</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[#64748b]">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($suppliers as $s)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-5 py-3 text-sm font-medium text-[#1E2530]">
                                {{ $s['supplier_name'] }}
                                <span class="block text-xs text-[#64748b]">{{ $s['invoice_count'] }} open purchase{{ $s['invoice_count'] === 1 ? '' : 's' }}</span>
                            </td>
                            <td class="px-5 py-3 text-sm text-right font-semibold text-[#1E2530]">ETB {{ number_format($s['total_owed'], 2) }}</td>
                            <td class="px-5 py-3 text-sm text-[#64748b]">{{ $s['nearest_due'] ? \Carbon\Carbon::parse($s['nearest_due'])->format('d M Y') : '—' }}</td>
                            <td class="px-5 py-3">
                                @if($s['status'] === 'overdue')
                                    <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700">Overdue</span>
                                @elseif($s['status'] === 'soon')
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">Due within {{ $soonDays }} days</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">OK</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('supplier-owed.show', $s['supplier_id']) }}" class="text-xs font-medium text-[var(--brand-primary)] hover:underline">View purchases</a>
                                <a href="{{ route('supplier-payments.create', $s['supplier_id']) }}" class="ml-3 inline-flex items-center rounded-lg bg-[var(--brand-primary)] px-3 py-2 text-xs font-semibold text-white hover:bg-[var(--brand-primary-dark)] min-h-[44px]">Pay supplier</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="px-5 py-6 text-sm text-[#64748b]">Nothing is owed to any supplier right now. Purchases paid with cash, bank, mobile money or card never appear here.</p>
    @endif
</div>
@endsection
