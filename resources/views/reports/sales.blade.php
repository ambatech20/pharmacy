@extends('layouts.app')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-[#1E2530]">Sales Report</h1>
    <p class="text-sm text-[#64748b] mt-1">Every dispensing transaction, filterable by month or year.</p>
</div>

<div class="flex gap-2 mb-4 text-sm">
    <a href="{{ route('reports.inventory') }}" class="px-3 py-1.5 rounded-lg font-medium text-[#64748b] hover:bg-gray-100">Inventory</a>
    <a href="{{ route('reports.financial') }}" class="px-3 py-1.5 rounded-lg font-medium text-[#64748b] hover:bg-gray-100">Financial</a>
    <a href="{{ route('reports.sales') }}" class="px-3 py-1.5 rounded-lg font-medium bg-[var(--brand-primary)]/10 text-[var(--brand-primary)]">Sales</a>
</div>

@include('partials.period-filter', [
    'action' => route('reports.sales'),
    'hint' => 'Picking a month without a year uses ' . now()->year . '. Leaving both on "All" shows every sale on record.',
])

<div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-4 inline-block">
    <p class="text-xs font-medium text-[#64748b] uppercase tracking-wide mb-1">Total Sales &middot; {{ $period->label() }}</p>
    <p class="text-2xl font-bold text-[var(--brand-primary)]">ETB {{ number_format($total, 2) }}</p>
    <p class="text-xs text-[#64748b] mt-1">{{ number_format($sales->total()) }} transaction{{ $sales->total() === 1 ? '' : 's' }} in this period</p>
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-gray-50/50">
            <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Date</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Item</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Customer</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Qty</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Total</th>
                <th class="px-5 py-3 text-center text-xs font-semibold text-[#64748b] uppercase tracking-wide">Verified</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($sales as $sale)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-5 py-3.5 text-sm text-[#64748b]">{{ \Carbon\Carbon::parse($sale->issue_date)->format('M d, Y') }}</td>
                    <td class="px-5 py-3.5 text-sm font-medium text-[#1E2530]">{{ $sale->purchaseBatch->drug->name ?? $sale->purchaseBatch->product->name ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#64748b]">{{ $sale->customer->name ?? 'Walk-in' }}</td>
                    <td class="px-5 py-3.5 text-sm text-right text-[#1E2530]">{{ $sale->quantity_issued }}</td>
                    <td class="px-5 py-3.5 text-sm text-right font-semibold text-[#1E2530]">ETB {{ number_format($sale->total_amount, 2) }}</td>
                    <td class="px-5 py-3.5 text-sm text-center">
                        @if($sale->order && $sale->order->payments->where('verification_status', 'verified')->isNotEmpty())
                            <span class="inline-flex items-center text-emerald-600" title="Payment verified">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                        @elseif($sale->order && $sale->order->payments->where('verification_status', 'declined')->isNotEmpty())
                            <span class="inline-flex items-center text-red-500" title="Verification declined">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                        @else
                            <span class="text-gray-300">&mdash;</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-12 text-center text-sm text-[#64748b]">No sales recorded for this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $sales->links() }}
</div>
@endsection
