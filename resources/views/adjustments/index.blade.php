@extends('layouts.app')

@section('content')
<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-[#1E2530]">Stock Adjustments</h1>
        <p class="text-sm text-[#64748b] mt-1">Write-offs for damaged, lost, or expired stock — never a silent quantity edit.</p>
    </div>
    <a href="{{ route('adjustments.create') }}"
       class="inline-flex items-center gap-2 bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-medium text-sm py-2.5 px-4 rounded-lg transition-colors">
        Write off stock
    </a>
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-gray-50/50">
            <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Date</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Drug / Batch</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Reason</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Qty</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">At cost</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">By</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($adjustments as $adjustment)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-5 py-3.5 text-sm text-[#64748b]">{{ $adjustment->created_at->format('M d, Y g:i A') }}</td>
                    <td class="px-5 py-3.5 text-sm">
                        <span class="font-medium text-[#1E2530]">{{ $adjustment->purchaseBatch->drug->name ?? $adjustment->purchaseBatch->product->name ?? '—' }}</span>
                        <span class="block text-xs text-[#64748b]">Batch {{ $adjustment->purchaseBatch->batch_number ?? '—' }}</span>
                    </td>
                    <td class="px-5 py-3.5 text-sm text-[#1E2530]">
                        {{ $adjustment->reasonLabel() }}
                        @if($adjustment->notes)
                            <span class="block text-xs text-[#64748b]">{{ $adjustment->notes }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-sm text-right text-[#1E2530]">{{ $adjustment->quantity }}</td>
                    <td class="px-5 py-3.5 text-sm text-right text-[#64748b]">${{ number_format($adjustment->total_amount, 2) }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#64748b]">{{ $adjustment->user->name ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-12 text-center text-sm text-[#64748b]">No write-offs recorded yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $adjustments->links() }}
</div>
@endsection
