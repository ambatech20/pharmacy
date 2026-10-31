@extends('layouts.app')

@section('content')
<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-[#1E2530]">Purchases</h1>
        <p class="text-sm text-[#64748b] mt-1">Incoming stock batches from suppliers.</p>
    </div>
    <a href="{{ route('purchases.create') }}"
       class="inline-flex items-center gap-2 bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-medium text-sm py-2.5 px-4 rounded-lg transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        Record Purchase
    </a>
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-gray-50/50">
            <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Batch #</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Drug</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Supplier</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Branch</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Purchased</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Remaining</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Cost/Unit</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Paying</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Status</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Expiry</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($purchases as $batch)
                @php
                    $isExpired = $batch->expiry_date && \Carbon\Carbon::parse($batch->expiry_date)->isPast();
                    $isNearExpiry = $batch->expiry_date && !$isExpired && now()->diffInDays($batch->expiry_date, false) <= 30;
                @endphp
                <tr class="hover:bg-gray-50/50">
                    <td class="px-5 py-3.5 text-sm font-mono text-[#64748b]">{{ $batch->batch_number }}</td>
                    <td class="px-5 py-3.5 text-sm font-medium text-[#1E2530]">{{ $batch->drug->name ?? $batch->product->name ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#64748b]">{{ $batch->supplier->name ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#64748b]">{{ $batch->branch->name ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-sm text-right text-[#64748b]">{{ ($batch->drug ?? $batch->product)?->describeQuantity($batch->quantity_received) ?? $batch->quantity_received . ' units' }}</td>
                    <td class="px-5 py-3.5 text-sm text-right text-[#1E2530]">{{ ($batch->drug ?? $batch->product)?->describeQuantity($batch->quantity_remaining) ?? $batch->quantity_remaining . ' units' }}</td>
                    <td class="px-5 py-3.5 text-sm text-right text-[#64748b]">ETB {{ number_format($batch->cost_per_unit, 2) }}</td>
                    {{-- Part A: how this purchase is being paid (plain language). --}}
                    <td class="px-5 py-3.5 text-sm text-[#64748b]">
                        {{ $batch->payment_label() }}
                        @if($batch->payment_reference)
                            <span class="block text-xs font-mono text-[#9aa6ba]">{{ $batch->payment_reference }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-sm">
                        @if($batch->payment_status === 'paid' || !$batch->payment_status)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">Paid</span>
                        @elseif($batch->payment_status === 'partial')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-sky-50 text-sky-700">Partly paid</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700">Not paid yet</span>
                            @if($batch->due_date)
                                <span class="block text-xs mt-0.5 {{ \Carbon\Carbon::parse($batch->due_date)->isBefore(today()) ? 'text-red-600 font-medium' : 'text-[#64748b]' }}">Due {{ \Carbon\Carbon::parse($batch->due_date)->format('M d, Y') }}</span>
                            @endif
                        @endif
                    </td>
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
                    <td class="px-5 py-3.5 text-sm text-right whitespace-nowrap">
                        @if($batch->quantity_remaining > 0)
                            <a href="{{ route('returns.supplier.create', ['batch_id' => $batch->id]) }}" class="text-[var(--brand-primary)] hover:underline">Return</a>
                        @endif

                        {{-- Only an untouched purchase can be removed. Once any of
                             it has moved, the row is history and a supplier return
                             is the correct correction. --}}
                        @if(auth()->user()->role === 'owner_admin'
                            && (int) $batch->quantity_remaining === (int) $batch->quantity_received)
                            <form method="POST" action="{{ route('purchases.destroy', $batch) }}" class="inline"
                                  onsubmit="return confirm('Delete this purchase? The stock and its ledger entry will be reversed. Only do this for a mistaken or test entry.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline {{ $batch->quantity_remaining > 0 ? 'ml-3' : '' }}">Delete</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                        <td colspan="11" class="px-5 py-12 text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-gray-300 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <p class="text-sm text-[#64748b]">No purchases recorded yet.</p>
                        <a href="{{ route('purchases.create') }}" class="text-sm font-medium text-[var(--brand-primary)] hover:underline mt-1 inline-block">Record your first purchase</a>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $purchases->links() }}
</div>
@endsection
