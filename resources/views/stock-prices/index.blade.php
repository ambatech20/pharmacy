@extends('layouts.app')

@section('content')
<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-[#1E2530]">Batch Prices</h1>
        <p class="text-sm text-[#64748b] mt-1">
            The price on the sale screen comes from the <strong>newest batch with stock</strong>, so
            a price you set here is what every sale charges from now on.
        </p>
    </div>
    <form method="GET" action="{{ route('batch-prices.index') }}" autocomplete="off" data-form-type="other" class="flex gap-2">
        <input type="search" name="q" value="{{ $search }}" placeholder="Item or batch number"
               autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
               data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other"
               class="w-64 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30">
        <button type="submit" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm font-medium hover:bg-gray-50">Search</button>
        @if($search)
            <a href="{{ route('batch-prices.index') }}" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm hover:bg-gray-50">Clear</a>
        @endif
    </form>
</div>

<div class="mb-6 rounded-xl border border-sky-100 bg-sky-50 px-5 py-4 text-sm text-sky-900">
    <strong>To raise a price:</strong> change the price on one row, press <em>Save</em>, then press
    <em>Apply to all batches of this item</em> so every open batch matches. Sells already completed keep
    the price they were charged.
</div>

@if(session('success'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-gray-50/50">
            <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Item</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Batch #</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Expires</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Qty left</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Cost</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Selling price</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($batches as $batch)
                @php
                    $item = $batch->drug ?? $batch->product;
                    $expired = $batch->expiry_date && \Carbon\Carbon::parse($batch->expiry_date)->isPast();
                @endphp
                <tr class="hover:bg-gray-50/50 {{ $expired ? 'opacity-60' : '' }}">
                    <td class="px-5 py-3.5 text-sm font-medium text-[#1E2530]">
                        {{ $item->name ?? '—' }}
                        @if($batch->drug && $batch->drug->strength)
                            <span class="text-[#64748b] font-normal">({{ $batch->drug->strength }})</span>
                        @endif
                        @if($batch->product && $batch->product->sku)
                            <span class="block text-xs text-[#64748b] font-mono">{{ $batch->product->sku }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-sm font-mono text-[#64748b]">{{ $batch->batch_number }}</td>
                    <td class="px-5 py-3.5 text-sm">
                        @if($expired)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-600">Expired</span>
                        @elseif($batch->expiry_date)
                            <span class="text-[#64748b]">{{ \Carbon\Carbon::parse($batch->expiry_date)->format('M d, Y') }}</span>
                        @else
                            <span class="text-[#64748b]">Not tracked</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-sm text-right text-[#1E2530]">{{ $batch->quantity_remaining }}</td>
                    <td class="px-5 py-3.5 text-sm text-right text-[#64748b]">ETB {{ number_format((float) $batch->cost_per_unit, 2) }}</td>
                    <td class="px-5 py-3.5 text-sm text-right text-[#64748b]">ETB {{ number_format((float) $batch->selling_price_per_unit, 2) }}</td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center justify-end gap-2">
                            <form method="POST" action="{{ route('batch-prices.update', $batch) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PUT')
                                <input type="number" step="0.0001" min="0" name="selling_price_per_unit"
                                       value="{{ rtrim(rtrim(number_format((float) $batch->selling_price_per_unit, 4, '.', ''), '0'), '.') }}"
                                       class="w-28 border border-gray-200 rounded-lg px-2.5 py-1.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30">
                                <button type="submit" class="text-sm font-medium text-[var(--brand-primary)] hover:underline">Save</button>
                            </form>
                            <form method="POST" action="{{ route('batch-prices.apply', $batch) }}"
                                  onsubmit="return confirm('Set every open batch of this item to ETB {{ number_format((float) $batch->selling_price_per_unit, 2) }}?');">
                                @csrf
                                @method('PUT')
                                <button type="submit" class="text-xs font-medium border border-gray-200 rounded-lg px-2.5 py-1.5 hover:bg-gray-50">
                                    Apply to all batches of this item
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-5 py-10 text-center text-sm text-[#64748b]">
                        No open batches{{ $search ? ' match that search' : ' yet — record a purchase first' }}.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $batches->links() }}</div>
@endsection
