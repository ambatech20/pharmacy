@extends('layouts.app')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-[#1E2530]">Expenses</h1>
    <a href="{{ route('expenses.create') }}" class="bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-bold py-2 px-4 rounded-lg text-sm min-h-[44px] inline-flex items-center">+ Add Expense</a>
</div>

{{-- Filters --}}
<form method="GET" action="{{ route('expenses.index') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-6 grid grid-cols-1 sm:grid-cols-4 gap-3">
    <div>
        <label class="block text-xs font-semibold text-[#64748b] mb-1">From date</label>
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
    </div>
    <div>
        <label class="block text-xs font-semibold text-[#64748b] mb-1">To date</label>
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
    </div>
    <div>
        <label class="block text-xs font-semibold text-[#64748b] mb-1">Category</label>
        <select name="category_id" class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
            <option value="">All categories</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}@unless($cat->active) (inactive) @endunless</option>
            @endforeach
        </select>
    </div>
    <div class="flex items-end gap-2">
        <button type="submit" class="bg-[var(--brand-accent)] hover:bg-[#1a2b47] text-white font-semibold py-2 px-4 rounded-lg text-sm min-h-[44px]">Filter</button>
        <a href="{{ route('expenses.index') }}" class="text-[#64748b] hover:text-[#1E2530] text-sm py-2 px-2 min-h-[44px] inline-flex items-center">Clear</a>
    </div>
</form>

<div class="overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0">
<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden min-w-[760px]">
    <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-gray-50/50">
            <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Date</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Category</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Amount</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">How Paid</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Note</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($expenses as $expense)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-5 py-3.5 text-[#1E2530] whitespace-nowrap">{{ $expense->expense_date->format('d M Y') }}</td>
                    <td class="px-5 py-3.5 font-medium text-[#1E2530]">{{ $expense->category->name }}</td>
                    <td class="px-5 py-3.5 text-right font-semibold text-[#1E2530] whitespace-nowrap">{{ number_format($expense->amount, 2) }}</td>
                    <td class="px-5 py-3.5 text-[#64748b]">{{ $expense->payment_label() }}@if($expense->payment_reference) <span class="text-xs">({{ $expense->payment_reference }})</span>@endif</td>
                    <td class="px-5 py-3.5 text-[#64748b] max-w-[220px] truncate" title="{{ $expense->note }}">{{ $expense->note ?? '—' }}@if($expense->repeat_monthly)<span class="ml-1 inline-flex items-center rounded-full bg-sky-50 text-sky-700 text-xs px-2 py-0.5 font-medium">Monthly</span>@endif</td>
                    <td class="px-5 py-3.5 text-right whitespace-nowrap">
                        <a href="{{ route('expenses.edit', $expense->id) }}" class="text-[var(--brand-primary)] hover:underline mr-3">Edit</a>
                        <form action="{{ route('expenses.destroy', $expense->id) }}" method="POST" class="inline"
                              onsubmit="return confirm('Remove this {{ $expense->category->name }} expense of {{ number_format($expense->amount, 2) }}? It will be cancelled out in the books.');">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-500 hover:underline">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-5 py-8 text-center text-[#64748b]">No expenses found for this filter.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
</div>

<div class="mt-4">{{ $expenses->links() }}</div>

{{-- Monthly totals by category --}}
<h2 class="text-lg font-bold text-[#1E2530] mt-8 mb-3">Total per category ({{ now()->format('F Y') }})</h2>
<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden max-w-md">
    <table class="min-w-full divide-y divide-gray-100">
        <tbody class="divide-y divide-gray-50">
            @php $grand = 0; @endphp
            @foreach($categories as $cat)
                @if(($monthlyTotals[$cat->id] ?? 0) > 0)
                    @php $grand += $monthlyTotals[$cat->id]; @endphp
                    <tr>
                        <td class="px-5 py-3 text-[#1E2530]">{{ $cat->name }}</td>
                        <td class="px-5 py-3 text-right font-semibold text-[#1E2530]">{{ number_format($monthlyTotals[$cat->id], 2) }}</td>
                    </tr>
                @endif
            @endforeach
            <tr class="bg-gray-50/70">
                <td class="px-5 py-3 font-bold text-[#1E2530]">Total expenses</td>
                <td class="px-5 py-3 text-right font-bold text-[#1E2530]">{{ number_format($grand, 2) }}</td>
            </tr>
        </tbody>
    </table>
</div>
@endsection
