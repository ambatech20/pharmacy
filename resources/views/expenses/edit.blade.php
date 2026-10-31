@extends('layouts.app')

@section('content')
<h1 class="text-2xl font-bold text-[#1E2530] mb-6">Edit Expense</h1>

<form method="POST" action="{{ route('expenses.update', $expense->id) }}" autocomplete="off" data-form-type="other" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 max-w-2xl space-y-5">
    @csrf @method('PUT')

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-semibold text-[#1E2530] mb-1">Date</label>
            <input type="date" name="expense_date" value="{{ old('expense_date', $expense->expense_date->toDateString()) }}" required
                   class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
            @error('expense_date')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-semibold text-[#1E2530] mb-1">Category</label>
            <select name="expense_category_id" required class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(old('expense_category_id', $expense->expense_category_id) == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
            @error('expense_category_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-semibold text-[#1E2530] mb-1">Amount</label>
            <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', $expense->amount) }}" required
                   class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
            @error('amount')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
    </div>

    <div x-data="{ method: '{{ old('payment_method', $expense->payment_method) }}' }">
        <label class="block text-sm font-semibold text-[#1E2530] mb-1">How did you pay?</label>
        <div class="flex flex-wrap gap-2">
            @foreach($methods as $value => $label)
                <label class="inline-flex items-center gap-2 border rounded-lg px-3 py-2 min-h-[44px] cursor-pointer">
                    <input type="radio" name="payment_method" value="{{ $value }}" x-model="method" class="accent-[var(--brand-primary)]" @checked(old('payment_method', $expense->payment_method) === $value)>
                    <span class="text-sm">{{ $label }}</span>
                </label>
            @endforeach
        </div>
        @error('payment_method')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror

        <div x-show="method !== 'cash' && method !== 'check'" x-cloak class="mt-4">
            <label class="block text-sm font-semibold text-[#1E2530] mb-1">Transaction number / reference</label>
            <input type="text" name="payment_reference" value="{{ old('payment_reference', $expense->payment_reference) }}"
                   class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
            @error('payment_reference')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div x-show="method === 'check'" x-cloak class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-semibold text-[#1E2530] mb-1">Check number</label>
                <input type="text" name="check_number" value="{{ old('check_number', $expense->check_number) }}"
                       class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
            </div>
            <div>
                <label class="block text-sm font-semibold text-[#1E2530] mb-1">Check date</label>
                <input type="date" name="check_date" value="{{ old('check_date', $expense->check_date?->toDateString()) }}"
                       class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
            </div>
            <div>
                <label class="block text-sm font-semibold text-[#1E2530] mb-1">Bank name</label>
                <input type="text" name="check_bank" value="{{ old('check_bank', $expense->check_bank) }}"
                       class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
            </div>
        </div>
    </div>

    <div>
        <label class="block text-sm font-semibold text-[#1E2530] mb-1">Note (optional)</label>
        <textarea name="note" rows="2" class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)]">{{ old('note', $expense->note) }}</textarea>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-semibold text-[#1E2530] mb-1">Paid for</label>
            <select name="paid_months" class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
                @foreach($paidMonthOptions as $months)
                    <option value="{{ $months }}" @selected((int) old('paid_months', $expense->paid_months ?: 1) === $months)>
                        {{ $months }} month{{ $months === 1 ? '' : 's' }}
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-[#64748b] mt-1">Paying several months at once moves the reminder on by the same amount.</p>
        </div>
    </div>

    <label class="flex items-center gap-3 cursor-pointer min-h-[44px]">
        <input type="checkbox" name="repeat_monthly" value="1" class="rounded accent-[var(--brand-primary)] w-5 h-5" @checked(old('repeat_monthly', $expense->repeat_monthly))>
        <span class="text-sm text-[#1E2530]">This happens every month (I'll get a reminder when the next one is due)</span>
    </label>

    <p class="text-xs text-[#64748b]">Saving will cancel out the old entry and record the new one — nothing disappears from the books.</p>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-bold py-2 px-6 rounded-lg min-h-[44px]">Save Changes</button>
        <a href="{{ route('expenses.index') }}" class="text-[#64748b] hover:text-[#1E2530] py-2 px-4 min-h-[44px] inline-flex items-center">Cancel</a>
    </div>
</form>
@endsection
