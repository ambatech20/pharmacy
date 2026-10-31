@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-1">Record Purchase</h1>
    <p class="text-sm text-[#64748b] mb-6">Add one or more Drugs or Products from the same supplier.</p>

    <form method="POST" action="{{ route('purchases.store') }}" autocomplete="off" data-form-type="other" class="space-y-5">
        @csrf
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium mb-1.5">Supplier</label><select name="supplier_id" id="purchase-supplier" data-credit-days="{{ collect($suppliers)->mapWithKeys(fn ($s) => [$s->id => (int) ($s->default_credit_days ?: 0)]) }}" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required><option value="">-- Select supplier --</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></div>
                <div><label class="block text-sm font-medium mb-1.5">Branch</label><select name="branch_id" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"><option value="">-- Use my branch --</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>@endforeach</select><p class="text-xs text-[#64748b] mt-1">Leave blank to stamp the purchase with your own branch.</p></div>
            </div>
            <div><label class="block text-sm font-medium mb-1.5">Purchase date</label><input type="date" name="purchase_date" value="{{ old('purchase_date', now()->toDateString()) }}" class="w-full sm:w-1/2 border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required></div>
        </div>

        {{-- Part A: How are you paying? (plain language, no accounting terms) --}}
        <div x-data="purchasePayment(@js(array_keys(\App\Models\Payment::METHODS)))" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
            <h2 class="font-semibold text-[#1E2530]">How are you paying?</h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                @foreach(\App\Models\Payment::METHODS as $key => $label)
                    <label class="flex items-center justify-center gap-2 border border-gray-200 rounded-lg px-3 py-3 text-sm font-medium cursor-pointer touch-manipulation min-h-[44px]" :class="method === '{{ $key }}' ? 'border-[var(--brand-primary)] bg-[var(--brand-primary)]/5 text-[var(--brand-primary-dark)]' : ''">
                        <input type="radio" name="payment_method" value="{{ $key }}" x-model="method" class="accent-[var(--brand-primary)] w-4 h-4" @checked(old('payment_method', 'cash') === $key)>
                        {{ $label }}
                    </label>
                @endforeach
                <label class="flex items-center justify-center gap-2 border border-gray-200 rounded-lg px-3 py-3 text-sm font-medium cursor-pointer touch-manipulation min-h-[44px]" :class="method === 'pay_later' ? 'border-amber-500 bg-amber-50 text-amber-700' : ''">
                    <input type="radio" name="payment_method" value="pay_later" x-model="method" class="accent-[var(--brand-primary)] w-4 h-4" @checked(old('payment_method') === 'pay_later')>
                    Pay later
                </label>
            </div>

            {{-- Non-cash, paid-now methods: transaction / check number reference --}}
            {{-- For a check the check number below is used as the reference, so the field is optional there. --}}
            <div x-show="method !== 'cash' && method !== 'pay_later' && method !== 'check'" x-cloak>
                <label class="block text-sm font-medium mb-1.5">Transaction number / reference</label>
                <input type="text" name="payment_reference" value="{{ old('payment_reference') }}" x-bind:required="method !== 'cash' && method !== 'pay_later' && method !== 'check'"
                       autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                       data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other"
                       placeholder="e.g. transfer ID or slip number" class="w-full sm:w-1/2 border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[44px]">
            </div>

            {{-- Check: also store check number, check date and bank --}}
            <div x-show="method === 'check'" x-cloak class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div><label class="block text-sm font-medium mb-1.5">Check number</label><input type="text" name="check_number" value="{{ old('check_number') }}" x-bind:required="method === 'check'" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[44px]"></div>
                <div><label class="block text-sm font-medium mb-1.5">Check date</label><input type="date" name="check_date" value="{{ old('check_date') }}" x-bind:required="method === 'check'" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[44px]"></div>
                <div><label class="block text-sm font-medium mb-1.5">Bank</label><input type="text" name="check_bank" value="{{ old('check_bank') }}" x-bind:required="method === 'check'" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other" placeholder="Bank the check is drawn on" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[44px]"></div>
            </div>

            {{-- Pay later: due date with quick choices --}}
            <div x-show="method === 'pay_later'" x-cloak class="space-y-3 border border-amber-200 bg-amber-50/50 rounded-lg p-4">
                <p class="text-sm text-amber-800">This will be added to "Owed to suppliers" until you pay it.</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="setDue(30)" class="px-4 py-2.5 min-h-[44px] rounded-lg text-sm font-medium border" :class="dueChoice === 30 ? 'bg-amber-600 text-white border-amber-600' : 'border-amber-300 text-amber-800 bg-white'">Due in 30 days</button>
                    <button type="button" @click="setDue(60)" class="px-4 py-2.5 min-h-[44px] rounded-lg text-sm font-medium border" :class="dueChoice === 60 ? 'bg-amber-600 text-white border-amber-600' : 'border-amber-300 text-amber-800 bg-white'">Due in 60 days</button>
                    <button type="button" @click="dueChoice = 'custom'" class="px-4 py-2.5 min-h-[44px] rounded-lg text-sm font-medium border" :class="dueChoice === 'custom' ? 'bg-amber-600 text-white border-amber-600' : 'border-amber-300 text-amber-800 bg-white'">Custom date</button>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1.5">Due date</label>
                    <input type="date" name="due_date" x-model="dueDate" x-bind:required="method === 'pay_later'" @change="dueChoice = 'custom'" class="w-full sm:w-1/2 border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[44px]">
                    <p class="text-xs text-[#64748b] mt-1">Default is <span x-text="defaultDays"></span> days after the purchase date.</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
            <div><label class="block text-sm font-medium mb-1.5">Supplier invoice number (optional)</label>
                <input type="text" name="supplier_invoice_no" value="{{ old('supplier_invoice_no') }}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other" placeholder="As printed on the supplier's invoice" class="w-full sm:w-1/2 border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[44px]">
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
            <div class="flex justify-between items-center mb-3"><h2 class="font-semibold">Purchase items</h2><button type="button" id="add-purchase-line" class="text-[var(--brand-primary)] text-sm font-semibold">+ Add item</button></div>
            <div id="purchase-lines" class="space-y-4">
                <div class="purchase-line border border-gray-100 rounded-lg p-4 space-y-3" data-index="0">
                    <div class="flex items-start gap-3">
                    <div class="grid flex-1 grid-cols-1 sm:grid-cols-4 gap-3">
                        <select name="items[0][item_type]" class="item-type border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required><option value="drug">Drug</option><option value="product">Product</option></select>
                        <select name="items[0][item_id]" class="drug-choice border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required><option value="">-- Select drug --</option>@foreach($drugs as $drug)<option value="{{ $drug->id }}">{{ $drug->name }} @if($drug->strength) ({{ $drug->strength }}) @endif</option>@endforeach</select>
                        <select class="product-choice hidden border border-gray-200 rounded-lg px-3 py-2.5 text-sm"><option value="">-- Select product --</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>@endforeach</select>
                        <input name="items[0][batch_number]" placeholder="Batch / reference" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other" class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
                    </div>
                    <button type="button" class="remove-purchase-line hidden mt-2 rounded-lg px-2 py-1 text-xl leading-none text-red-500 hover:bg-red-50 hover:text-red-700" title="Remove purchase item" aria-label="Remove purchase item">&times;</button>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                        <input type="number" min="1" name="items[0][quantity_received]" placeholder="Quantity" class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
                        <select name="items[0][quantity_unit]" class="purchase-unit border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required><option value="">Unit</option></select>
                        <input type="number" min="0" step="0.01" name="items[0][cost_per_unit]" placeholder="Cost / unit" class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
                        <input type="number" min="0" step="0.01" name="items[0][selling_price_per_unit]" placeholder="Selling / unit" class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
                        <label class="text-xs font-medium text-[#64748b]">Expiry date <span class="text-red-500">*</span><input type="date" name="items[0][expiry_date]" class="mt-1 w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required></label>
                    </div>
                    {{-- The invoice's own wording: "2 boxes", not "48 tablets". --}}
                    <p class="unit-preview text-xs font-medium text-[var(--brand-primary-dark)]"></p>
                    {{-- What the rest of the app will call this stock, and the
                         unit(s) the till is allowed to sell. --}}
                    <p class="sell-preview text-xs text-[#64748b]"></p>
                </div>
            </div>
            <p class="text-xs text-[#64748b] mt-3">Pick the unit the invoice uses - pack, box, bottle, strip or tablet - and enter the quantity and price in that unit. Stock is stored in base units. An expiry date is required on every line - for items that never expire, use a far-future date such as 31/12/2099 so it never appears in the alerts.</p>
        </div>

        <div class="flex gap-3"><button class="bg-[var(--brand-primary)] text-white font-medium text-sm py-2.5 px-5 rounded-lg">Save Purchase</button><a href="{{ route('purchases.index') }}" class="text-[#64748b] py-2.5 px-4">Cancel</a></div>
    </form>
</div>

<script>
// Part A — "How are you paying?" Alpine component. Default due date is the
// supplier's own credit period when set, otherwise 30 days after purchase date.
document.addEventListener('alpine:init', () => {
    Alpine.data('purchasePayment', (methods) => ({
        method: @js(old('payment_method', 'cash')),
        dueDate: @js(old('due_date', '')),
        dueChoice: @js(old('due_date') ? 'custom' : 30),
        defaultDays: 30,
        init() {
            this.$watch('method', (m) => { if (m === 'pay_later') this.refreshDefault(); });
            const dateInput = document.querySelector('[name=purchase_date]');
            if (dateInput) dateInput.addEventListener('change', () => { if (this.method === 'pay_later' && this.dueChoice !== 'custom') this.setDue(this.dueChoice); });
            const supplierSelect = document.getElementById('purchase-supplier');
            if (supplierSelect) supplierSelect.addEventListener('change', () => { if (this.method === 'pay_later') this.refreshDefault(); });
            if (this.method === 'pay_later' && !this.dueDate) this.refreshDefault();
        },
        supplierDays() {
            const sel = document.getElementById('purchase-supplier');
            if (!sel) return 0;
            try {
                const map = JSON.parse(sel.dataset.creditDays || '{}');
                return parseInt(map[sel.value] || 0, 10);
            } catch (e) { return 0; }
        },
        refreshDefault() {
            const d = this.supplierDays();
            this.defaultDays = d > 0 ? d : 30;
            if (this.dueChoice !== 'custom' || !this.dueDate) this.setDue(this.dueChoice === 60 ? 60 : this.defaultDays);
        },
        setDue(days) {
            this.dueChoice = days;
            const base = (document.querySelector('[name=purchase_date]')?.value) || new Date().toISOString().slice(0, 10);
            const dt = new Date(base + 'T00:00:00');
            dt.setDate(dt.getDate() + days);
            this.dueDate = dt.toISOString().slice(0, 10);
        },
    }));
});
</script>
<script>
(() => {
    const lines = document.getElementById('purchase-lines');
    let index = 1;

    // The rungs each drug / product has, biggest first, keyed by id.
    const drugLevels = @json($drugLevels);
    const productLevels = @json($productLevels);

    const plural = (word) => /(s|x|z|ch|sh)$/i.test(word) ? word + 'es' : word + 's';

    const optionsHtml = (levels) => levels.map((level) => {
        const name = level.name.charAt(0).toUpperCase() + level.name.slice(1);
        const holds = level.factor > 1 ? ' (' + level.factor + ')' : '';
        return '<option value="' + level.key + '">' + name + holds + '</option>';
    }).join('');

    const bind = (line) => {
        const type = line.querySelector('.item-type');
        const drug = line.querySelector('.drug-choice');
        const product = line.querySelector('.product-choice');
        const unit = line.querySelector('.purchase-unit');
        const qty = line.querySelector('[name*="[quantity_received]"]');
        const cost = line.querySelector('[name*="[cost_per_unit]"]');
        const sell = line.querySelector('[name*="[selling_price_per_unit]"]');
        const preview = line.querySelector('.unit-preview');
        const sellPreview = line.querySelector('.sell-preview');

        const levels = () => type.value === 'product'
            ? (productLevels[product.value] || [])
            : (drugLevels[drug.value] || []);

        const capped = (word) => word.charAt(0).toUpperCase() + word.slice(1);

        // Whole money where it is whole, four decimals where dividing a pack
        // by its base count does not land on a cent (120 / 28).
        const money = (value) => {
            const cents = Math.round(value * 100);
            return Math.abs(value * 100 - cents) < 1e-6
                ? (cents / 100).toFixed(2)
                : value.toFixed(4);
        };

        const refreshLabels = () => {
            const list = levels();
            const level = list.find((entry) => entry.key === unit.value);
            const base = list.length ? list[list.length - 1] : null;
            const name = level ? level.name : 'unit';
            const factor = level ? Math.max(1, Number(level.factor) || 1) : 1;

            cost.placeholder = 'Cost / ' + name;
            sell.placeholder = 'Selling / ' + name;

            const count = parseInt(qty.value || '1', 10) || 1;
            const parts = [];

            if (level) {
                const totalBase = count * factor;

                if (factor > 1 || (base && base.name !== name)) {
                    parts.push(
                        count + ' ' + (count === 1 ? name : plural(name))
                        + ' = ' + totalBase.toLocaleString() + ' '
                        + plural(base ? base.name : name, totalBase)
                    );
                } else {
                    parts.push(count + ' ' + (count === 1 ? name : plural(name)));
                }

                const costValue = parseFloat(cost.value);
                const sellValue = parseFloat(sell.value);

                if (Number.isFinite(costValue) && costValue > 0) {
                    parts.push('cost ETB ' + money(costValue / factor) + ' / ' + (base ? base.name : name));
                }

                if (Number.isFinite(sellValue) && sellValue > 0) {
                    parts.push('selling ETB ' + money(sellValue / factor) + ' / ' + (base ? base.name : name));
                }
            }

            preview.textContent = parts.join(' · ');

            // The till only sells the rungs ticked on the item's record. Show
            // them here so the buyer sees the same answer the seller will.
            const sold = list.filter((entry) => entry.sellable).map((entry) => capped(entry.name));

            sellPreview.textContent = sold.length ? 'Sold as: ' + sold.join(', ') : '';
        };

        const syncUnits = () => {
            const current = unit.value;
            const list = levels();

            unit.innerHTML = list.length ? optionsHtml(list) : '<option value="">Unit</option>';

            // Buying starts at the biggest unit - that is how the invoice is
            // written ("2 boxes") - unless the cashier already chose a rung
            // this item also has.
            if (current && list.some((entry) => entry.key === current)) {
                unit.value = current;
            } else if (list.length) {
                unit.value = list[0].key;
            }

            refreshLabels();
        };

        const sync = () => {
            const isProduct = type.value === 'product';
            drug.classList.toggle('hidden', isProduct);
            product.classList.toggle('hidden', !isProduct);
            drug.required = !isProduct;
            product.required = isProduct;
            drug.name = isProduct ? '' : `items[${line.dataset.index}][item_id]`;
            product.name = isProduct ? `items[${line.dataset.index}][item_id]` : '';
            syncUnits();
        };

        type.addEventListener('change', sync);
        drug.addEventListener('change', syncUnits);
        product.addEventListener('change', syncUnits);
        unit.addEventListener('change', refreshLabels);
        qty.addEventListener('input', refreshLabels);
        cost.addEventListener('input', refreshLabels);
        sell.addEventListener('input', refreshLabels);

        sync();
    };
    bind(lines.firstElementChild);
    document.getElementById('add-purchase-line').addEventListener('click', () => {
        const line = lines.firstElementChild.cloneNode(true);
        line.dataset.index = index;
        line.querySelectorAll('[name]').forEach(el => el.name = el.name.replace(/items\[0\]/g, `items[${index}]`));
        line.querySelectorAll('input').forEach(el => el.value = '');
        line.querySelectorAll('select').forEach(el => el.selectedIndex = 0);
        line.querySelector('.unit-preview').textContent = '';
        line.querySelector('.sell-preview').textContent = '';
        line.querySelector('.remove-purchase-line').classList.remove('hidden');
        lines.appendChild(line);
        bind(line);
        index++;
    });
    lines.addEventListener('click', (event) => {
        const removeButton = event.target.closest('.remove-purchase-line');
        if (removeButton) removeButton.closest('.purchase-line').remove();
    });
})();
</script>
@endsection
