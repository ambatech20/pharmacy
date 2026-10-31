@extends('layouts.app')

@section('content')
<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-[#1E2530]">Transfers</h1>
        <p class="text-sm text-[#6B7A93] mt-1">Stock moved between branches.</p>
    </div>
    <a href="{{ route('transfers.create') }}"
       class="inline-flex items-center gap-2 bg-[#12A594] hover:bg-[#0e8a7d] text-white font-medium text-sm py-2.5 px-4 rounded-lg transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        Transfer Stock
    </a>
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-gray-50/50">
            <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Date</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Item</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">From</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide"></th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">To</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Qty</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($transfers as $transfer)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-5 py-3.5 text-sm text-[#6B7A93]">{{ \Carbon\Carbon::parse($transfer->transfer_date)->format('M d, Y') }}</td>
                    <td class="px-5 py-3.5 text-sm font-medium text-[#1E2530]">{{ $transfer->purchaseBatch->drug->name ?? $transfer->purchaseBatch->product->name ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#6B7A93]">{{ $transfer->fromBranch->name ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-sm text-gray-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </td>
                    <td class="px-5 py-3.5 text-sm text-[#6B7A93]">{{ $transfer->toBranch->name ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-sm text-right text-[#1E2530]">
                        {{ ($transfer->purchaseBatch?->drug ?? $transfer->purchaseBatch?->product)?->describeQuantity($transfer->quantity) ?? $transfer->quantity }}
                    </td>
                    <td class="px-5 py-3.5 text-sm">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">
                            {{ ucfirst($transfer->status) }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-5 py-12 text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-gray-300 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        <p class="text-sm text-[#6B7A93]">No transfers yet.</p>
                        <a href="{{ route('transfers.create') }}" class="text-sm font-medium text-[#12A594] hover:underline mt-1 inline-block">Move stock between branches</a>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $transfers->links() }}
</div>
@endsection
