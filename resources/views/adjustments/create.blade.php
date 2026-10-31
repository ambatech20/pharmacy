@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-1">Write off stock</h1>
    <p class="text-sm text-[#64748b] mb-6">Removes units from a specific batch, posts shrinkage at cost, and writes the activity log.</p>

    <form method="POST" action="{{ route('adjustments.store') }}" autocomplete="off" data-form-type="other" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Batch</label>
            <select name="purchase_batch_id" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
                <option value="">-- Select batch --</option>
                @foreach($batches as $batch)
                    @php
                        $adjustItem = $batch->drug ?? $batch->product;
                        $adjustLevels = $adjustItem ? $adjustItem->packingLevels() : [];
                    @endphp
                    <option value="{{ $batch->id }}"
                            @selected((int) old('purchase_batch_id', $selectedBatchId) === $batch->id)
                            data-levels="{{ json_encode($adjustLevels, JSON_HEX_APOS | JSON_HEX_QUOT) }}">
                        {{ ($batch->drug ?? $batch->product)->name ?? 'Item' }}
                        — {{ $batch->batch_number }}
                        ({{ $adjustItem ? $adjustItem->describeQuantity($batch->quantity_remaining) : $batch->quantity_remaining . ' unit' }} left
                        @if($batch->expiry_date) · exp {{ $batch->expiry_date->format('M d, Y') }}@endif)
                    </option>
                @endforeach
            </select>
            @if($batches->isEmpty())
                <p class="text-sm text-red-600 mt-1.5">No batches with remaining stock.</p>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Quantity to write off</label>
                <input type="number" min="1" name="quantity" value="{{ old('quantity') }}"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Unit</label>
                <select name="quantity_unit" id="adjust-unit" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required></select>
            </div>
        </div>
        <p id="adjust-unit-hint" class="text-xs text-[#64748b] -mt-2">Remaining stock on the batch is shown in base units.</p>

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Reason</label>
            <select name="reason" id="reason-select" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
                <option value="">-- Select reason --</option>
                @foreach($reasons as $value => $label)
                    <option value="{{ $value }}" @selected(old('reason') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Notes <span id="notes-required" class="hidden text-red-600"> (required)</span></label>
            <input type="text" name="notes" id="notes-input" value="{{ old('notes') }}" maxlength="255"
                   autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                   data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                   placeholder="Optional unless reason is Other">
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-medium text-sm py-2.5 px-5 rounded-lg transition-colors">
                Confirm write-off
            </button>
            <a href="{{ route('adjustments.index') }}" class="text-[#64748b] hover:text-[#1E2530] text-sm py-2.5 px-4">Cancel</a>
        </div>
    </form>
</div>

<script>
    (function () {
        const batchSelect = document.querySelector('select[name="purchase_batch_id"]');
        const unitSelect = document.getElementById('adjust-unit');
        const hint = document.getElementById('adjust-unit-hint');
        const initialUnit = @json(old('quantity_unit'));

        const plural = (word) => /(s|x|z|ch|sh)$/i.test(word) ? word + 'es' : word + 's';
        const levelsFor = (opt) => {
            if (!opt || !opt.dataset.levels) return [];
            try { return JSON.parse(opt.dataset.levels); } catch (e) { return []; }
        };

        function describeUnit() {
            const levels = levelsFor(batchSelect.options[batchSelect.selectedIndex]);
            if (!levels.length) {
                hint.textContent = 'Remaining stock is in base units.';
                return;
            }
            const base = levels[levels.length - 1];
            const chosen = levels.find((level) => level.key === unitSelect.value) || base;
            const part = chosen.factor > 1
                ? '1 ' + chosen.name + ' = ' + chosen.factor + ' ' + plural(base.name, chosen.factor) + '. '
                : '';
            hint.textContent = part + 'Remaining is in ' + plural(base.name, 2) + '.';
        }

        function fillUnits() {
            const levels = levelsFor(batchSelect.options[batchSelect.selectedIndex]);
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

        batchSelect.addEventListener('change', fillUnits);
        unitSelect.addEventListener('change', describeUnit);
        fillUnits();
        const reasonSelect = document.getElementById('reason-select');
        const requiredMark = document.getElementById('notes-required');
        const notes = document.getElementById('notes-input');
        function sync() {
            const other = reasonSelect.value === 'other';
            requiredMark.classList.toggle('hidden', !other);
            notes.required = other;
        }
        reasonSelect.addEventListener('change', sync);
        sync();
    })();
</script>
@endsection
