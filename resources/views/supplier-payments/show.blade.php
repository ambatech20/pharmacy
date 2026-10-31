@extends('layouts.app')

@section('content')
<div class="mb-6">
    <a href="{{ route('supplier-owed.index') }}" class="text-sm text-[var(--brand-primary)] hover:underline">&larr; Back to Owed to suppliers</a>
    <h1 class="text-2xl font-bold text-[#1E2530] mt-2">{{ $supplier->name }}</h1>
    <p class="text-sm text-[#64748b] mt-1">Purchases marked "Pay later" that are not fully paid yet.</p>
</div>

@if(session('success'))
    <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-100 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
@endif

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div><h2 class="font-bold text-[#1E2530]">Total owed</h2><p class="text-xs text-[#64748b] mt-0.5">Calculated from the purchases below</p></div>
        <div class="flex items-center gap-3">
            <p class="text-xl font-bold text-[#1E2530]">ETB {{ number_format($totalOwed, 2) }}</p>
            @if($totalOwed > 0)
                <a href="{{ route('supplier-payments.create', $supplier) }}" class="inline-flex items-center rounded-lg bg-[var(--brand-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--brand-primary-dark)] min-h-[44px]">Pay supplier</a>
            @endif
        </div>
    </div>
</div>

<div class="space-y-4">
    @forelse($invoices as $inv)
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden" x-data="{ open: false }">
            <div class="px-5 py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-[#1E2530]">
                        Invoice {{ $inv['supplier_invoice_no'] ?: $inv['purchase_ref'] }}
                        @if($inv['supplier_invoice_no'])<span class="text-xs font-normal text-[#64748b]">({{ $inv['purchase_ref'] }})</span>@endif
                    </p>
                    <p class="text-xs text-[#64748b] mt-0.5">
                        Purchased {{ \Carbon\Carbon::parse($inv['date'])->format('d M Y') }}
                        &middot; Due {{ $inv['due_date'] ? \Carbon\Carbon::parse($inv['due_date'])->format('d M Y') : '—' }}
                    </p>
                </div>
                <div class="flex items-center gap-3 flex-wrap">
                    <div class="text-right">
                        <p class="text-sm font-bold text-[#1E2530]">ETB {{ number_format($inv['balance'], 2) }} left</p>
                        <p class="text-xs text-[#64748b]">of ETB {{ number_format($inv['total'], 2) }} &middot; paid ETB {{ number_format($inv['paid'], 2) }}</p>
                    </div>
                    @if($inv['status'] === 'partial')
                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">Partly paid</span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700">Unpaid</span>
                    @endif
                    <button type="button" @click="open = !open" class="text-xs font-medium text-[var(--brand-primary)] hover:underline min-h-[44px] min-w-[44px]" x-text="open ? 'Hide lines' : 'View lines'">View lines</button>
                </div>
            </div>
            <div x-show="open" x-cloak class="border-t border-gray-100 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-50">
                    <thead class="bg-gray-50/60">
                        <tr>
                            <th class="px-5 py-2 text-left text-xs font-semibold uppercase tracking-wider text-[#64748b]">Item</th>
                            <th class="px-5 py-2 text-left text-xs font-semibold uppercase tracking-wider text-[#64748b]">Batch</th>
                            <th class="px-5 py-2 text-right text-xs font-semibold uppercase tracking-wider text-[#64748b]">Qty</th>
                            <th class="px-5 py-2 text-right text-xs font-semibold uppercase tracking-wider text-[#64748b]">Cost each</th>
                            <th class="px-5 py-2 text-right text-xs font-semibold uppercase tracking-wider text-[#64748b]">Amount</th>
                            <th class="px-5 py-2 text-right text-xs font-semibold uppercase tracking-wider text-[#64748b]">Paid</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($inv['lines'] as $line)
                            <tr>
                                <td class="px-5 py-2 text-sm text-[#1E2530]">{{ $line->drug->name ?? ($line->product->name ?? '—') }}</td>
                                <td class="px-5 py-2 text-xs text-[#64748b]">{{ $line->batch_number }}</td>
                                <td class="px-5 py-2 text-sm text-right text-[#64748b]">{{ number_format($line->quantity_received) }}</td>
                                <td class="px-5 py-2 text-sm text-right text-[#64748b]">{{ number_format((float) $line->cost_per_unit, 2) }}</td>
                                <td class="px-5 py-2 text-sm text-right text-[#1E2530]">{{ number_format($line->total_cost, 2) }}</td>
                                <td class="px-5 py-2 text-sm text-right text-[#1E2530]">{{ number_format((float) $line->amount_paid, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
            <p class="text-sm text-[#64748b]">Nothing is owed to this supplier right now.</p>
        </div>
    @endforelse
</div>

@if(count($invoices))
    <div class="mt-6 flex justify-end">
        <a href="{{ route('supplier-payments.history', $supplier) }}" class="text-sm font-medium text-[var(--brand-primary)] hover:underline">Payment history</a>
    </div>
@endif
@endsection
