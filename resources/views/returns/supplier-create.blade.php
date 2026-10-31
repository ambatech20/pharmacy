@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-1">Supplier return</h1>
    <p class="text-sm text-[#64748b] mb-6">Takes the remaining stock off the batch and corrects the purchase record. One line per submit.</p>

    <form method="POST" action="{{ route('returns.supplier.store') }}" autocomplete="off" data-form-type="other" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Batch</label>
            <select name="purchase_batch_id" id="supplier-batch" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
                <option value="">-- Select batch --</option>
                @foreach($batches as $batch)
                    @php
                        $returnItem = $batch->drug ?? $batch->product;
                        $returnLevels = $returnItem ? $returnItem->packingLevels() : [];
                    @endphp
                    <option value="{{ $batch->id }}"
                            @selected((int) old('purchase_batch_id', $selectedBatchId) === $batch->id)
                            data-levels="{{ json_encode($returnLevels, JSON_HEX_APOS | JSON_HEX_QUOT) }}">
                        {{ ($batch->drug ?? $batch->product)->name ?? 'Item' }}
                        — {{ $batch->batch_number }}
                        ({{ $returnItem ? $returnItem->describeQuantity($batch->quantity_remaining) : $batch->quantity_remaining . ' unit' }} left
                        @if($batch->supplier) · {{ $batch->supplier->name }}@endif)
                    </option>
                @endforeach
            </select>
            @if($batches->isEmpty())
                <p class="text-sm text-red-600 mt-1.5">No batches with remaining stock.</p>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Quantity</label>
                <input type="number" min="1" name="quantity" value="{{ old('quantity') }}"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Unit</label>
                <select name="quantity_unit" id="supplier-unit" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required></select>
            </div>
        </div>
        <p id="supplier-hint" class="text-xs text-[#64748b] -mt-2">Only unsold remaining stock can go back to the supplier.</p>

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
</div>

<script>
    (function () {
        const select = document.getElementById('supplier-batch');
        const hint = document.getElementById('supplier-hint');
        const unitSelect = document.getElementById('supplier-unit');
        const initialUnit = @json(old('quantity_unit'));

        const plural = (word) => /(s|x|z|ch|sh)$/i.test(word) ? word + 'es' : word + 's';
        const levelsFor = (opt) => {
            if (!opt || !opt.dataset.levels) return [];
            try { return JSON.parse(opt.dataset.levels); } catch (e) { return []; }
        };

        function describeUnit() {
            const levels = levelsFor(select.options[select.selectedIndex]);
            if (!levels.length) {
                hint.textContent = 'Only unsold remaining stock can go back to the supplier.';
                return;
            }
            const base = levels[levels.length - 1];
            const chosen = levels.find((level) => level.key === unitSelect.value) || base;
            const part = chosen.factor > 1
                ? '1 ' + chosen.name + ' = ' + chosen.factor + ' ' + plural(base.name, chosen.factor) + '. '
                : '';
            hint.textContent = part + 'Only unsold remaining stock can go back to the supplier.';
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
@endsection
