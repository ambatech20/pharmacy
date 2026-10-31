@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-6">Edit Customer</h1>

    <form method="POST" action="{{ route('customers.update', $customer->id) }}" autocomplete="off" data-form-type="other" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Name</label>
            <input type="text" name="name" value="{{ old('name', $customer->name) }}"
                   autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                   data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30" required>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $customer->phone) }}"
                       autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                       data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30">
            </div>
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Email</label>
                <input type="email" name="email" value="{{ old('email', $customer->email) }}"
                       autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                       data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Address</label>
            <textarea name="address" rows="2"
                      autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                      data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other"
                      class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30">{{ old('address', $customer->address) }}</textarea>
        </div>

        <hr class="border-gray-100">

        <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" name="is_recurring" value="1" id="is-recurring-checkbox" @checked($customer->is_recurring)>
            Recurring customer (gets a refill reminder)
        </label>
        <div id="recurring-fields" class="space-y-2 pl-6 {{ $customer->is_recurring ? '' : 'hidden' }}">
            <div>
                <label class="block text-xs font-medium text-[#64748b] mb-1">Remind every (days)</label>
                <input type="number" name="reminder_interval_days" value="{{ old('reminder_interval_days', $customer->reminder_interval_days ?? 30) }}"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-[#64748b] mb-1">Note</label>
                <input type="text" name="condition_note" value="{{ old('condition_note', $customer->condition_note) }}"
                       autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                       data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-medium text-sm py-2.5 px-5 rounded-lg transition-colors">Save Changes</button>
            <a href="{{ route('customers.index') }}" class="text-[#64748b] hover:text-[#1E2530] text-sm py-2.5 px-4">Cancel</a>
        </div>
    </form>
</div>

<script>
    document.getElementById('is-recurring-checkbox').addEventListener('change', function () {
        document.getElementById('recurring-fields').classList.toggle('hidden', !this.checked);
    });
</script>
@endsection
