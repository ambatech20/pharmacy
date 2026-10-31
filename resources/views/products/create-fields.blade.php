<div><label class="block text-sm font-medium mb-1.5">Product name</label><input name="name" value="{{ old('name', $product->name ?? '') }}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required></div>
<div><label class="block text-sm font-medium mb-1.5">SKU / product code <span class="font-normal text-[#64748b]">(optional)</span></label><input name="sku" value="{{ old('sku', $product->sku ?? '') }}" placeholder="Leave blank if not available" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"><p class="text-xs text-[#64748b] mt-1">Use an SKU when you want faster searching or future barcode support.</p></div>
<div><label class="block text-sm font-medium mb-1.5">Category</label><select name="category_id" id="product-category" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required><option value="">-- Choose the closest category --</option>@foreach($categories as $category)<option value="{{ $category->id }}" data-hint="{{ $categoryHints[$category->name] ?? 'Choose this for products that fit this category.' }}" @selected(old('category_id', $product->category_id ?? '') == $category->id)>{{ $category->name }}</option>@endforeach</select><p id="category-hint" class="text-xs text-[#64748b] mt-1">Pick the category that best describes the product's main use.</p></div>
<div><label class="block text-sm font-medium mb-1.5">Brand</label><input name="brand" value="{{ old('brand', $product->brand ?? '') }}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"></div>
<div class="grid grid-cols-2 gap-4"><div><label class="block text-sm font-medium mb-1.5">Purchase price</label><input type="number" min="0" step="0.01" name="purchase_price" value="{{ old('purchase_price', $product->purchase_price ?? 0) }}" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required></div><div><label class="block text-sm font-medium mb-1.5">Selling price</label><input type="number" min="0" step="0.01" name="selling_price" value="{{ old('selling_price', $product->selling_price ?? 0) }}" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required></div></div>
<div><label class="block text-sm font-medium mb-1.5">Description</label><textarea name="description" rows="3" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm">{{ old('description', $product->description ?? '') }}</textarea></div>
@php($packingDefaults = $product ?? new \App\Models\Product([
    'unit_top' => 'piece',
    'unit_base' => 'piece',
    'sell_base' => true,
    'minimum_stock' => 0,
]))
@include('drugs.partials.packing', [
    'packing' => $packingDefaults,
    'baseOptions' => [
        'piece' => 'Pieces',
        'bottle' => 'Bottles',
        'roll' => 'Rolls',
        'sachet' => 'Sachets',
        'ampoule' => 'Ampoules',
        'bag' => 'Bags',
        'pair' => 'Pairs',
        'unit' => 'Units',
    ],
])
<script>
(() => { const select = document.getElementById('product-category'); const hint = document.getElementById('category-hint'); if (!select || !hint) return; const update = () => { hint.textContent = select.selectedOptions[0]?.dataset.hint || "Pick the category that best describes the product's main use."; }; select.addEventListener('change', update); update(); })();
</script>
