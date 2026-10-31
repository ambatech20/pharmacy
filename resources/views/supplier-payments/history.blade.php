@extends('layouts.app')

@section('content')
<div class="mb-6">
    <a href="{{ route('supplier-owed.show', $supplier) }}" class="text-sm text-[var(--brand-primary)] hover:underline">&larr; Back to {{ $supplier->name }}</a>
    <h1 class="text-2xl font-bold text-[#1E2530] mt-2">Payments to {{ $supplier->name }}</h1>
    <p class="text-sm text-[#64748b] mt-1">Every payment recorded so far, newest first.</p>
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    @if($payments->count())
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50/60">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[#64748b]">Date</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[#64748b]">Payment #</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-[#64748b]">Amount</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[#64748b]">How</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[#64748b]">Reference</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[#64748b]">Applied to</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($payments as $p)
                        <tr class="hover:bg-gray-50/50 align-top">
                            <td class="px-5 py-3 text-sm text-[#1E2530]">{{ $p->payment_date->format('d M Y') }}</td>
                            <td class="px-5 py-3 text-xs text-[#64748b]">{{ $p->payment_number }}</td>
                            <td class="px-5 py-3 text-sm text-right font-semibold text-[#1E2530]">ETB {{ number_format((float) $p->amount, 2) }}</td>
                            <td class="px-5 py-3 text-sm text-[#64748b]">{{ App\Models\Payment::METHODS[$p->payment_method] ?? $p->payment_method }}</td>
                            <td class="px-5 py-3 text-sm text-[#64748b]">
                                @if($p->payment_method === 'check')
                                    Check {{ $p->check_number }}
                                    @if($p->check_date)<span class="block text-xs">Check date: {{ $p->check_date->format('d M Y') }}</span>@endif
                                    @if($p->check_bank)<span class="block text-xs">{{ $p->check_bank }}</span>@endif
                                @else
                                    {{ $p->payment_reference ?: '—' }}
                                @endif
                            </td>
                            <td class="px-5 py-3 text-xs text-[#64748b]">
                                @forelse($p->allocations as $a)
                                    <span class="block">{{ $a->purchase_ref ? ('Invoice ' . $a->purchase_ref) : ('Batch #' . $a->purchase_batch_id) }} — ETB {{ number_format((float) $a->amount, 2) }}</span>
                                @empty
                                    —
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4 border-t border-gray-100">{{ $payments->links() }}</div>
    @else
        <p class="px-5 py-6 text-sm text-[#64748b]">No payments recorded for this supplier yet.</p>
    @endif
</div>
@endsection
