@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="flex justify-between items-start mb-6">
        <div>
            <h1 class="text-2xl font-bold text-[#1E2530]">Order #{{ $order->id }}</h1>
            <p class="text-sm text-[#64748b] mt-1">{{ $order->created_at->format('M d, Y g:i A') }}</p>
        </div>
        <div class="flex flex-col items-end gap-2">
            <a href="{{ route('dispense.index') }}" class="text-sm text-[var(--brand-primary)] hover:underline">← Back to Dispense</a>
            <a href="{{ route('dispense.receipt', ['order' => $order->id, 'autoprint' => 1]) }}"
               class="inline-flex items-center bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-medium text-sm py-2 px-4 rounded-lg transition-colors">
                Print Receipt
            </a>
            <a href="{{ route('returns.customer.create', ['order_id' => $order->id]) }}"
               class="text-sm text-[#64748b] hover:text-[#1E2530] hover:underline">
                Return items
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <div class="flex justify-between text-sm mb-4 pb-4 border-b border-gray-100">
            <div>
                <p class="text-[#64748b]">Customer</p>
                <p class="font-medium text-[#1E2530]">{{ $order->customer->name ?? 'Walk-in' }}</p>
            </div>
            <div class="text-right">
                <p class="text-[#64748b]">Sold by</p>
                <p class="font-medium text-[#1E2530]">{{ $order->user->name ?? '—' }}</p>
            </div>
        </div>

        <table class="w-full text-sm mb-4">
            <thead>
                <tr class="text-xs text-[#64748b] uppercase tracking-wide">
                    <th class="text-left pb-2">Drug</th>
                    <th class="text-right pb-2">Qty</th>
                    <th class="text-right pb-2">Unit Price</th>
                    <th class="text-right pb-2">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($order->items as $item)
                    <tr>
                        <td class="py-2 text-[#1E2530]">{{ $item->purchaseBatch->drug->name ?? '—' }}</td>
                        <td class="py-2 text-right text-[#64748b]">{{ $item->quantity_issued }}</td>
                        <td class="py-2 text-right text-[#64748b]">${{ number_format($item->selling_price_per_unit, 2) }}</td>
                        <td class="py-2 text-right text-[#1E2530]">${{ number_format($item->total_amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="flex justify-between items-center pt-3 border-t border-gray-100">
            <span class="font-bold text-[#1E2530]">Total</span>
            <span class="font-bold text-lg text-[var(--brand-primary)]">${{ number_format($order->total_amount, 2) }}</span>
        </div>

        @if($order->payments->isNotEmpty())
            <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-xs font-semibold text-[#64748b] uppercase tracking-wide mb-2">Payments</p>
                <ul class="space-y-1 text-sm">
                    @foreach($order->payments as $payment)
                        <li class="flex justify-between items-center">
                            <span class="text-[#64748b]">{{ $payment->methodLabel() }}</span>
                            <span class="flex items-center gap-2">
                                @if($payment->isVerified())
                                    <span class="inline-flex items-center gap-1 text-emerald-600" title="Verified by P-Check">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </span>
                                @elseif($payment->verification_status === 'declined')
                                    <span class="inline-flex items-center gap-1 text-red-500" title="Verification declined">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </span>
                                @endif
                                <span class="text-[#1E2530]">${{ number_format($payment->amount, 2) }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
@endsection
