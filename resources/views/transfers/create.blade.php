@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-1">Transfer Stock</h1>
    <p class="text-sm text-[#6B7A93] mb-6">Move stock from one branch to another.</p>

    <form method="POST" action="{{ route('transfers.store') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Item</label>
            <select name="item_type" id="transfer-type" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm">
                <option value="drug" @selected(old('item_type', 'drug') === 'drug')>Drug</option>
                <option value="product" @selected(old('item_type') === 'product')>Product</option>
            </select>
            <select name="item_id" id="transfer-drug" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30 mt-2" required>
                <option value="">-- Select Drug --</option>
                @foreach($drugs as $drug)
                    <option value="{{ $drug->id }}" @selected(old('item_type', 'drug') === 'drug' && (int) old('item_id') === $drug->id)>
                        {{ $drug->name }} @if($drug->strength) ({{ $drug->strength }}) @endif
                    </option>
                @endforeach
            </select>
            <select id="transfer-product" class="hidden w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30 mt-2">
                <option value="">-- Select Product --</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}" @selected(old('item_type') === 'product' && (int) old('item_id') === $product->id)>
                        {{ $product->name }} @if($product->sku) ({{ $product->sku }}) @endif
                    </option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-2 gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">From Branch</label>
                @if($sourceBranchId)
                    @php($sourceBranch = $branches->firstWhere('id', $sourceBranchId))
                    <input type="hidden" name="from_branch_id" value="{{ $sourceBranchId }}">
                    <div class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-[#1E2530]">
                        {{ $sourceBranch?->name ?? 'Your branch' }}
                    </div>
                @else
                    <select name="from_branch_id" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30" required>
                        <option value="">-- Select --</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((int) old('from_branch_id') === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">To Branch</label>
                <select name="to_branch_id" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30" required>
                    <option value="">-- Select --</option>
                    @foreach($branches as $branch)
                        @continue($sourceBranchId && (int) $branch->id === (int) $sourceBranchId)
                        <option value="{{ $branch->id }}" @selected((int) old('to_branch_id') === $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @if($branches->count() < 2)
            <p class="text-sm text-amber-600">You need at least two branches to transfer stock — <a href="{{ route('branches.create') }}" class="underline">add another branch</a>.</p>
        @endif

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Quantity</label>
                <input type="number" min="1" name="quantity" value="{{ old('quantity') }}"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Unit</label>
                <select name="quantity_unit" id="transfer-unit" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30" required>
                    <option value="">-- Select item first --</option>
                </select>
            </div>
        </div>
        <p id="transfer-unit-hint" class="text-xs text-[#6B7A93] -mt-2">Pick an item to see its units.</p>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-medium text-sm py-2.5 px-5 rounded-lg transition-colors">
                Transfer Stock
            </button>
            <a href="{{ route('transfers.index') }}" class="text-[#6B7A93] hover:text-[#1E2530] text-sm py-2.5 px-4">Cancel</a>
        </div>
    </form>
</div>

<script>
    (function () {
        const drugSelect = document.getElementById('transfer-drug');
        const productSelect = document.getElementById('transfer-product');
        const typeSelect = document.getElementById('transfer-type');
        const unitSelect = document.getElementById('transfer-unit');
        const hint = document.getElementById('transfer-unit-hint');
        const levels = {
            drug: @json($drugLevels),
            product: @json($productLevels),
        };

        function sync() {
            const isProduct = typeSelect.value === 'product';
            drugSelect.classList.toggle('hidden', isProduct);
            productSelect.classList.toggle('hidden', !isProduct);
            drugSelect.required = !isProduct;
            productSelect.required = isProduct;
            drugSelect.name = isProduct ? '' : 'item_id';
            productSelect.name = isProduct ? 'item_id' : '';

            const itemSelect = isProduct ? productSelect : drugSelect;
            const itemLevels = levels[typeSelect.value]?.[itemSelect.value] ?? [];
            const current = unitSelect.value;
            unitSelect.innerHTML = '';

            if (!itemLevels.length) {
                unitSelect.innerHTML = '<option value="">-- Select item first --</option>';
                hint.textContent = 'Pick an item to see its units.';
                return;
            }

            for (const level of itemLevels) {
                const option = document.createElement('option');
                option.value = level.key;
                option.textContent = level.name + (level.factor > 1 ? ` (${level.factor} base units)` : '');
                unitSelect.appendChild(option);
            }

            const base = itemLevels[itemLevels.length - 1];
            const baseName = base?.name ?? 'unit';
            const preferred = itemLevels.find(level => level.key === current)
                ?? itemLevels.find(level => level.key === 'base')
                ?? itemLevels[0];

            unitSelect.value = preferred.key;
            hint.textContent = itemLevels
                .map(level => `1 ${level.name} = ${level.factor} ${baseName}${level.factor === 1 ? '' : 's'}`)
                .join(' · ');
        }

        drugSelect.addEventListener('change', sync);
        productSelect.addEventListener('change', sync);
        typeSelect.addEventListener('change', sync);
        sync();
    })();
</script>
@endsection
