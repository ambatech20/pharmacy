@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-1">Customer return</h1>
    <p class="text-sm text-[#64748b] mb-6">Restocks the original batch and corrects the sale record. One line per submit.</p>

    <form method="GET" action="{{ route('returns.customer.create') }}" autocomplete="off" data-form-type="other" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-4 space-y-4">
        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Order</label>
            <select name="order_id" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" onchange="this.form.submit()" required>
                <option value="">-- Select order --</option>
                @foreach($orders as $row)
                    <option value="{{ $row->id }}" @selected((int) ($order->id ?? 0) === $row->id)>
                        #{{ $row->id }} · {{ $row->customer->name ?? 'Walk-in' }} · {{ $row->created_at->format('M d, Y') }} · ${{ number_format($row->total_amount, 2) }}
                    </option>
                @endforeach
            </select>
        </div>
    </form>

    @if($order)
        <form method="POST" action="{{ route('returns.customer.store') }}" autocomplete="off" data-form-type="other" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Sale line</label>
                <select name="issued_item_id" id="return-item" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
                    <option value="">-- Select item --</option>
                    @foreach($items as $item)
                        @php
                            $left = $item->quantityReturnable();
                            $soldItem = $item->purchaseBatch?->drug ?? $item->purchaseBatch?->product;
                            $soldLevels = $soldItem ? $soldItem->packingLevels() : [];
                        @endphp
                        <option value="{{ $item->id }}"
                                @selected((int) old('issued_item_id') === $item->id)
                                data-left="{{ $left }}"
                                data-levels="{{ json_encode($soldLevels, JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                                @disabled($left < 1)>
                            {{ $soldItem?->name ?? 'Item' }}
                            — {{ $left }} of {{ $item->quantity_issued }} {{ $soldItem?->baseUnitLabel() ?? 'unit' }} still returnable
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Quantity</label>
                    <input type="number" min="1" name="quantity" value="{{ old('quantity') }}"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Unit</label>
                    <select name="quantity_unit" id="return-unit" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required></select>
                </div>
            </div>
            <p id="return-hint" class="text-xs text-[#64748b] -mt-2">Refund is qty × original unit selling price. Stock goes back on the same batch.</p>

            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Refund method</label>
                <select name="refund_method" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
                    @foreach($paymentMethods as $value => $label)
                        <option value="{{ $value }}" @selected(old('refund_method', $defaultMethod) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Notes</label>
                <input type="text" name="notes" value="{{ old('notes') }}" maxlength="255"
                       autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                       data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" placeholder="Optional">
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-medium text-sm py-2.5 px-5 rounded-lg transition-colors">
                    Record return
                </button>
                <a href="{{ route('returns.index') }}" class="text-[#64748b] hover:text-[#1E2530] text-sm py-2.5 px-4">Cancel</a>
            </div>
        </form>
    @endif
</div>

@if($order)
<script>
    (function () {
        const select = document.getElementById('return-item');
        const hint = document.getElementById('return-hint');
        const unitSelect = document.getElementById('return-unit');
        const initialUnit = @json(old('quantity_unit'));

        const plural = (word) => /(s|x|z|ch|sh)$/i.test(word) ? word + 'es' : word + 's';
        const levelsFor = (opt) => {
            if (!opt || !opt.dataset.levels) return [];
            try { return JSON.parse(opt.dataset.levels); } catch (e) { return []; }
        };

        function describeUnit() {
            const opt = select.options[select.selectedIndex];
            const levels = levelsFor(opt);
            const left = opt && opt.dataset.left ? opt.dataset.left : '0';

            if (!levels.length) {
                hint.textContent = 'Refund is qty × original unit selling price. Stock goes back on the same batch.';
                return;
            }

            const base = levels[levels.length - 1];
            const chosen = levels.find((level) => level.key === unitSelect.value) || base;
            const part = chosen.factor > 1
                ? ' 1 ' + chosen.name + ' = ' + chosen.factor + ' ' + plural(base.name, chosen.factor) + '.'
                : '';

            hint.textContent = left + ' ' + plural(base.name, Number(left)) + ' still returnable.' + part;
        }

        function fillUnits() {
            const levels = levelsFor(select.options[select.selectedIndex]);
            const previous = unitSelect.value || initialUnit;
            unitSelect.innerHTML = '';

            if (!levels.length) {
                unitSelect.innerHTML = '<option value="base">Base unit</option>';
                describeUnit();
                return;
            }

            levels.forEach((level) => {
                const option = document.createElement('option');
                option.value = level.key;
                option.textContent = level.name.charAt(0).toUpperCase() + level.name.slice(1)
                    + (level.factor > 1 ? ' (' + level.factor + ' ' + plural(levels[levels.length - 1].name) + ')' : '');
                unitSelect.appendChild(option);
            });

            const match = levels.find((level) => level.key === previous);
            unitSelect.value = match ? match.key : levels[levels.length - 1].key;
            describeUnit();
        }

        select.addEventListener('change', fillUnits);
        unitSelect.addEventListener('change', describeUnit);
        fillUnits();
    })();
</script>
@endif
@endsection
