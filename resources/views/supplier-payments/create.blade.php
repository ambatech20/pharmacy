@extends('layouts.app')

@section('content')
<div class="mb-6">
    <a href="{{ route('supplier-owed.show', $supplier) }}" class="text-sm text-[var(--brand-primary)] hover:underline">&larr; Back to {{ $supplier->name }}</a>
    <h1 class="text-2xl font-bold text-[#1E2530] mt-2">Pay supplier: {{ $supplier->name }}</h1>
    <p class="text-sm text-[#64748b] mt-1">Total owed right now: <strong>ETB {{ number_format($totalOwed, 2) }}</strong>. Tick the purchases you are paying for, or just enter an amount — it is applied to the oldest purchases first.</p>
</div>

@if ($errors->any())
    <div class="mb-4 rounded-lg bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-800">
        <ul class="list-disc list-inside space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('supplier-payments.store', $supplier) }}" class="space-y-6"
      x-data="{
          amount: '{{ old('amount', '') }}',
          totalOwed: {{ $totalOwed }},
          methods: {{ Js::from(array_keys(App\Models\Payment::METHODS)) }},
          method: '{{ old('payment_method', 'cash') }}',
          isCheck() { return this.method === 'check'; },
          needsRef() { return this.method !== 'cash' && !this.isCheck(); },
      }">
    @csrf

    {{-- Purchases to tick --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100"><h2 class="font-bold text-[#1E2530]">Purchases owed</h2></div>
        <div class="divide-y divide-gray-50">
            @forelse($invoices as $inv)
                @php $checked = old('invoices.' . $inv['purchase_ref'], $prefillInvoice === $inv['purchase_ref'] ? '1' : null); @endphp
                <label class="flex items-start gap-3 px-5 py-4 cursor-pointer hover:bg-gray-50/50 min-h-[44px]">
                    <input type="checkbox" name="invoices[{{ $inv['purchase_ref'] }}]" value="{{ $inv['balance'] }}"
                           @checked((bool) $checked)
                           class="mt-1 h-5 w-5 rounded border-gray-300 text-[var(--brand-primary)] focus:ring-[var(--brand-primary)]">
                    <span class="flex-1">
                        <span class="block text-sm font-medium text-[#1E2530]">
                            Invoice {{ $inv['supplier_invoice_no'] ?: $inv['purchase_ref'] }}
                            @if($inv['supplier_invoice_no'])<span class="text-xs font-normal text-[#64748b]">({{ $inv['purchase_ref'] }})</span>@endif
                        </span>
                        <span class="block text-xs text-[#64748b] mt-0.5">
                            Purchased {{ \Carbon\Carbon::parse($inv['date'])->format('d M Y') }}
                            &middot; Due {{ $inv['due_date'] ? \Carbon\Carbon::parse($inv['due_date'])->format('d M Y') : '—' }}
                            &middot; Paid so far ETB {{ number_format($inv['paid'], 2) }}
                        </span>
                    </span>
                    <span class="text-sm font-semibold text-[#1E2530] whitespace-nowrap">ETB {{ number_format($inv['balance'], 2) }}</span>
                </label>
            @empty
                <p class="px-5 py-6 text-sm text-[#64748b]">This supplier is not owed anything right now.</p>
            @endforelse
        </div>
    </div>

    {{-- Payment details --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <h2 class="font-bold text-[#1E2530] mb-4">Payment details</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1">Amount you are paying (ETB) <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0.01" max="{{ $totalOwed }}" name="amount" x-model="amount" required
                       class="w-full rounded-lg border-gray-300 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
                <p class="text-xs mt-1" :class="Number(amount) > totalOwed ? 'text-red-600' : 'text-[#64748b]'"
                   x-text="Number(amount) > totalOwed ? 'You can\'t pay more than what is owed (ETB ' + totalOwed.toFixed(2) + ').' : 'Maximum you can pay: ETB ' + totalOwed.toFixed(2)"></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1">Payment date <span class="text-red-500">*</span></label>
                <input type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" required
                       class="w-full rounded-lg border-gray-300 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-[#1E2530] mb-1">How are you paying? <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                    @foreach(App\Models\Payment::METHODS as $code => $label)
                        <label class="relative flex items-center justify-center rounded-lg border px-3 py-3 text-sm cursor-pointer min-h-[44px] transition-colors"
                               :class="method === '{{ $code }}' ? 'border-[var(--brand-primary)] bg-[var(--brand-primary)]/10 text-[var(--brand-primary-dark)] font-semibold' : 'border-gray-200 text-[#64748b] hover:border-gray-300'">
                            <input type="radio" name="payment_method" value="{{ $code }}" x-model="method" class="sr-only">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div x-show="needsRef" x-cloak>
                <label class="block text-sm font-medium text-[#1E2530] mb-1">Transaction / reference number <span class="text-red-500">*</span></label>
                <input type="text" name="payment_reference" value="{{ old('payment_reference') }}" maxlength="255"
                       :required="needsRef" placeholder="e.g. transfer or terminal number"
                       class="w-full rounded-lg border-gray-300 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
            </div>
            <template x-if="isCheck">
                <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[#1E2530] mb-1">Check number <span class="text-red-500">*</span></label>
                        <input type="text" name="check_number" value="{{ old('check_number') }}" maxlength="100" required
                               class="w-full rounded-lg border-gray-300 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[#1E2530] mb-1">Check date <span class="text-red-500">*</span></label>
                        <input type="date" name="check_date" value="{{ old('check_date') }}" required
                               class="w-full rounded-lg border-gray-300 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
                        <p class="text-xs text-[#64748b] mt-1">If the check is post-dated, enter its date here — it will be shown with the payment.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[#1E2530] mb-1">Bank <span class="text-red-500">*</span></label>
                        <input type="text" name="check_bank" value="{{ old('check_bank') }}" maxlength="255" required
                               class="w-full rounded-lg border-gray-300 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
                    </div>
                </div>
            </template>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-[#1E2530] mb-1">Note (optional)</label>
                <textarea name="note" rows="2" maxlength="1000" class="w-full rounded-lg border-gray-300 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)]" placeholder="Anything worth remembering about this payment">{{ old('note') }}</textarea>
            </div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('supplier-owed.show', $supplier) }}" class="inline-flex items-center rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-[#64748b] hover:bg-gray-50 min-h-[44px]">Cancel</a>
        <button type="submit" class="inline-flex items-center rounded-lg bg-[var(--brand-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--brand-primary-dark)] min-h-[44px]">Record payment</button>
    </div>
</form>
@endsection
