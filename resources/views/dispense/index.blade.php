@extends('layouts.app')

@section('content')
<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-[#1E2530]">Dispense</h1>
        <p class="text-sm text-[#64748b] mt-1">Sales history — one row per checkout.</p>
    </div>
    <a href="{{ route('dispense.create') }}"
       class="inline-flex items-center gap-2 bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-medium text-sm py-2.5 px-4 rounded-lg transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        Dispense Drug
    </a>
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-gray-50/50">
            <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Order #</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Date</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Customer</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Items</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Total</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Paid by</th>
                <th class="px-5 py-3 text-center text-xs font-semibold text-[#64748b] uppercase tracking-wide">Verified</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($orders as $order)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-5 py-3.5 text-sm font-mono text-[#64748b]">#{{ $order->id }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#64748b]">{{ $order->created_at->format('M d, Y g:i A') }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#1E2530]">{{ $order->customer->name ?? 'Walk-in' }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#64748b]">{{ $order->items->count() }} item{{ $order->items->count() === 1 ? '' : 's' }}</td>
                    <td class="px-5 py-3.5 text-sm text-right font-semibold text-[#1E2530]">ETB {{ number_format($order->total_amount, 2) }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#64748b]">
                        @if($order->payments->isEmpty())
                            —
                        @else
                            {{ $order->payments->map->methodLabel()->unique()->join(', ') }}
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-sm text-center">
                        @if($order->payments->where('verification_status', 'verified')->isNotEmpty())
                            <span class="inline-flex items-center gap-1 text-emerald-600" title="Payment verified">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                        @elseif($order->payments->where('verification_status', 'declined')->isNotEmpty())
                            <span class="inline-flex items-center gap-1 text-red-500" title="Verification declined">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                        @elseif($order->digitalPayments()->isNotEmpty())
                            <span class="inline-flex items-center gap-1 text-amber-500" title="Awaiting verification">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                        @else
                            <span class="text-gray-300" title="Cash payment">&mdash;</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-sm text-right">
                        <a href="{{ route('dispense.show', $order->id) }}" class="text-[var(--brand-primary)] hover:underline">View</a>
                        <a href="{{ route('dispense.receipt', $order->id) }}" class="text-[#64748b] hover:underline ml-3">Receipt</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-5 py-12 text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-gray-300 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p class="text-sm text-[#64748b]">No sales recorded yet.</p>
                        <a href="{{ route('dispense.create') }}" class="text-sm font-medium text-[var(--brand-primary)] hover:underline mt-1 inline-block">Dispense your first drug</a>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $orders->links() }}
</div>
@endsection
