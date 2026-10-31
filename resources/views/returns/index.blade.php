@extends('layouts.app')

@section('content')
<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-[#1E2530]">Returns</h1>
        <p class="text-sm text-[#64748b] mt-1">Offsetting entries — original sales and purchases are not edited.</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('returns.customer.create') }}"
           class="inline-flex items-center bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-medium text-sm py-2.5 px-4 rounded-lg transition-colors">
            Customer return
        </a>
        <a href="{{ route('returns.supplier.create') }}"
           class="inline-flex items-center bg-white border border-gray-200 hover:bg-gray-50 text-[#1E2530] font-medium text-sm py-2.5 px-4 rounded-lg transition-colors">
            Supplier return
        </a>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-[#1E2530]">Customer returns</h2>
        </div>
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50/50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Date</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Order / Drug</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Qty</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Refund</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($customerReturns as $return)
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-5 py-3.5 text-sm text-[#64748b]">{{ $return->created_at->format('M d, Y g:i A') }}</td>
                        <td class="px-5 py-3.5 text-sm">
                            <span class="font-medium text-[#1E2530]">{{ $return->purchaseBatch->drug->name ?? $return->purchaseBatch->product->name ?? '—' }}</span>
                            <span class="block text-xs text-[#64748b]">Order #{{ $return->order_id }} · {{ $return->refundMethodLabel() }}</span>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-right text-[#1E2530]">{{ $return->quantity }}</td>
                            <td class="px-5 py-3.5 text-sm text-right text-[#64748b]">ETB {{ number_format($return->refund_amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-10 text-center text-sm text-[#64748b]">No customer returns yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-[#1E2530]">Supplier returns</h2>
        </div>
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50/50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Date</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Batch / Drug</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Qty</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">At cost</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($supplierReturns as $return)
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-5 py-3.5 text-sm text-[#64748b]">{{ $return->created_at->format('M d, Y g:i A') }}</td>
                        <td class="px-5 py-3.5 text-sm">
                            <span class="font-medium text-[#1E2530]">{{ $return->purchaseBatch->drug->name ?? $return->purchaseBatch->product->name ?? '—' }}</span>
                            <span class="block text-xs text-[#64748b]">{{ $return->purchaseBatch->batch_number ?? '—' }} · {{ $return->supplier->name ?? '—' }}</span>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-right text-[#1E2530]">{{ $return->quantity }}</td>
                            <td class="px-5 py-3.5 text-sm text-right text-[#64748b]">ETB {{ number_format($return->total_amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-10 text-center text-sm text-[#64748b]">No supplier returns yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
