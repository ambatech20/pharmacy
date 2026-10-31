@extends('layouts.app')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-[#1E2530]">Inventory Report</h1>
    <p class="text-sm text-[#64748b] mt-1">Current drug and product stock on hand.</p>
</div>

<div class="flex gap-2 mb-4 text-sm">
    <a href="{{ route('reports.inventory') }}" class="px-3 py-1.5 rounded-lg font-medium bg-[var(--brand-primary)]/10 text-[var(--brand-primary)]">Inventory</a>
    <a href="{{ route('reports.financial') }}" class="px-3 py-1.5 rounded-lg font-medium text-[#64748b] hover:bg-gray-100">Financial</a>
    <a href="{{ route('reports.sales') }}" class="px-3 py-1.5 rounded-lg font-medium text-[#64748b] hover:bg-gray-100">Sales</a>
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-gray-50/50">
            <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Drug</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Qty Remaining</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Cost/Unit</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Value</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Expiry</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($stock as $batch)
                @php
                    $isExpired = $batch->expiry_date && \Carbon\Carbon::parse($batch->expiry_date)->isPast();
                    $isNearExpiry = $batch->expiry_date && !$isExpired && now()->diffInDays($batch->expiry_date, false) <= 30;
                @endphp
                <tr class="hover:bg-gray-50/50">
                    <td class="px-5 py-3.5 text-sm font-medium text-[#1E2530]">{{ $batch->drug->name ?? $batch->product->name ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-sm text-right text-[#1E2530]">{{ $batch->quantity_remaining }}</td>
                    <td class="px-5 py-3.5 text-sm text-right text-[#64748b]">ETB {{ number_format($batch->cost_per_unit, 2) }}</td>
                    <td class="px-5 py-3.5 text-sm text-right font-semibold text-[#1E2530]">ETB {{ number_format($batch->quantity_remaining * $batch->cost_per_unit, 2) }}</td>
                    <td class="px-5 py-3.5 text-sm">
                        @if($isExpired)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-600">Expired</span>
                        @elseif($isNearExpiry)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-600">{{ \Carbon\Carbon::parse($batch->expiry_date)->format('M d, Y') }}</span>
                        @elseif($batch->expiry_date)
                            <span class="text-[#64748b]">{{ \Carbon\Carbon::parse($batch->expiry_date)->format('M d, Y') }}</span>
                        @else
                            <span class="text-[#64748b]">Not tracked</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-12 text-center text-sm text-[#64748b]">No stock on hand.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
