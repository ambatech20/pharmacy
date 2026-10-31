@extends('layouts.app')

@section('content')
<h1 class="text-2xl font-bold text-[#1E2530] mb-6">{{ $bill ? 'Pay Bill' : 'Add Expense' }}</h1>

@if($bill)
    <div class="mb-5 rounded-xl border border-sky-200 bg-sky-50 px-5 py-4 text-sm text-sky-900">
        <strong>Paying:</strong> {{ $bill->category->name ?? 'a recurring bill' }}
        — ETB {{ number_format((float) $bill->amount, 2) }},
        @if($bill->next_due_date) due {{ $bill->next_due_date->format('d M Y') }}@endif.
        Saving this moves the reminder on.
    </div>
@endif

<form method="POST" action="{{ route('expenses.store') }}" autocomplete="off" data-form-type="other" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 max-w-2xl space-y-5">
    @csrf
    @if($bill)
        <input type="hidden" name="bill" value="{{ $bill->id }}">
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-semibold text-[#1E2530] mb-1">Date</label>
            <input type="date" name="expense_date" value="{{ old('expense_date', now()->toDateString()) }}" required
                   class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
            @error('expense_date')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-semibold text-[#1E2530] mb-1">Category</label>
            <select name="expense_category_id" required class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
                <option value="">Choose a category…</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(old('expense_category_id', $bill?->expense_category_id) == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
            @error('expense_category_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-semibold text-[#1E2530] mb-1" id="amount-label">Amount</label>
            <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', $bill ? (float) $bill->reminderAmount() : null) }}" required
                   class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
            @error('amount')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            <p id="amount-total" class="text-xs text-[#64748b] mt-1"></p>
        </div>
        <div id="paid-months-field" class="{{ old('repeat_monthly', $bill ? 1 : null) ? '' : 'hidden' }}">
            <label class="block text-sm font-semibold text-[#1E2530] mb-1">Paid for</label>
            <select name="paid_months" id="paid-months" class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
                @foreach($paidMonthOptions as $months)
                    <option value="{{ $months }}" @selected((int) old('paid_months', 1) === $months)>
                        {{ $months }} month{{ $months === 1 ? '' : 's' }}
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-[#64748b] mt-1">Paying several months at once moves the reminder on by the same amount.</p>
        </div>
    </div>

    {{-- Plain language: "How did you pay?" --}}
    <div x-data="{ method: '{{ old('payment_method', 'cash') }}' }">
        <label class="block text-sm font-semibold text-[#1E2530] mb-1">How did you pay?</label>
        <div class="flex flex-wrap gap-2">
            @foreach($methods as $value => $label)
                <label class="inline-flex items-center gap-2 border rounded-lg px-3 py-2 min-h-[44px] cursor-pointer {{ in_array(old('payment_method','cash'), [$value]) ? 'border-[var(--brand-primary)] bg-[var(--brand-primary)]/10' : 'border-gray-200' }}">
                    <input type="radio" name="payment_method" value="{{ $value }}" x-model="method" class="accent-[var(--brand-primary)]" @checked(old('payment_method', 'cash') === $value)>
                    <span class="text-sm">{{ $label }}</span>
                </label>
            @endforeach
        </div>
        @error('payment_method')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror

        <div x-show="method !== 'cash' && method !== 'check'" x-cloak class="mt-4">
            <label class="block text-sm font-semibold text-[#1E2530] mb-1">Transaction number / reference</label>
            <input type="text" name="payment_reference" value="{{ old('payment_reference') }}"
                   class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
            @error('payment_reference')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div x-show="method === 'check'" x-cloak class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-semibold text-[#1E2530] mb-1">Check number</label>
                <input type="text" name="check_number" value="{{ old('check_number') }}"
                       class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
                @error('check_number')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-[#1E2530] mb-1">Check date</label>
                <input type="date" name="check_date" value="{{ old('check_date') }}"
                       class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
                @error('check_date')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-[#1E2530] mb-1">Bank name</label>
                <input type="text" name="check_bank" value="{{ old('check_bank') }}"
                       class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
                @error('check_bank')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    <div>
        <label class="block text-sm font-semibold text-[#1E2530] mb-1">Note (optional)</label>
        <textarea name="note" rows="2" class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)]" placeholder="e.g. March rent, receipt #221">{{ old('note') }}</textarea>
        @error('note')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <label class="flex items-center gap-3 cursor-pointer min-h-[44px]">
        <input type="checkbox" name="repeat_monthly" value="1" id="repeat-monthly" class="rounded accent-[var(--brand-primary)] w-5 h-5" @checked(old('repeat_monthly', $bill ? 1 : null))>
        <span class="text-sm text-[#1E2530]">This happens every month (I'll get a reminder when the next one is due)</span>
    </label>
    <p id="repeating-hint" class="text-xs text-sky-700 bg-sky-50 border border-sky-200 rounded-lg px-3 py-2 {{ old('repeat_monthly', $bill ? 1 : null) ? '' : 'hidden' }}">
        This category already repeats monthly — saving this moves that reminder on instead of starting a second one.
    </p>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-bold py-2 px-6 rounded-lg min-h-[44px]">Save Expense</button>
        <a href="{{ route('expenses.index') }}" class="text-[#64748b] hover:text-[#1E2530] py-2 px-4 min-h-[44px] inline-flex items-center">Cancel</a>
    </div>
</form>

<script>
    (function () {
        const repeat = document.getElementById('repeat-monthly');
        const monthsField = document.getElementById('paid-months-field');
        const hint = document.getElementById('repeating-hint');
        const category = document.querySelector('select[name="expense_category_id"]');
        const amountLabel = document.getElementById('amount-label');
        const amountInput = document.querySelector('input[name="amount"]');
        const monthsSelect = document.getElementById('paid-months');
        const total = document.getElementById('amount-total');

        // Categories that already have a repeating bill. Choosing one ticks
        // "every month" by itself, so paying rent from this screen still moves
        // the reminder on instead of starting a second one.
        const repeating = @json($repeatingCategoryIds);

        // The amount box is the rate for ONE period. The books record the
        // total, so the total is spelled out here rather than left to guess.
        function syncTotal() {
            const months = parseInt(monthsSelect ? monthsSelect.value : '1', 10) || 1;
            const rate = parseFloat(amountInput ? amountInput.value : '') || 0;

            if (repeat.checked && months > 1) {
                amountLabel.textContent = 'Amount per month';
                total.textContent = 'Total being paid: ETB ' + (rate * months).toFixed(2)
                    + ' (' + months + ' months). This is what the books will record.';
            } else {
                amountLabel.textContent = 'Amount';
                total.textContent = '';
            }
        }

        function sync() {
            monthsField.classList.toggle('hidden', !repeat.checked);
            const isRepeating = repeating.includes(parseInt(category.value, 10));
            hint.classList.toggle('hidden', !(isRepeating && repeat.checked));
            syncTotal();
        }

        repeat.addEventListener('change', sync);
        if (monthsSelect) monthsSelect.addEventListener('change', syncTotal);
        if (amountInput) amountInput.addEventListener('input', syncTotal);

        if (category) {
            category.addEventListener('change', function () {
                if (repeating.includes(parseInt(category.value, 10))) {
                    repeat.checked = true;
                }
                sync();
            });
        }

        sync();
    })();
</script>
@endsection
