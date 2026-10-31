<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Type</label>
        <select name="kind" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30">
            @foreach(\App\Models\Drug::KINDS as $value => $label)
                <option value="{{ $value }}" @selected(old('kind', $drug->kind ?? 'medicine') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <p class="text-xs text-[#64748b] mt-1">Medical supplies, supplements and infant formula are filed here for tax relief.</p>
    </div>
    <div>
        <label class="block text-sm font-medium text-[#1E2530] mb-1.5">SKU / code <span class="font-normal text-[#64748b]">(optional)</span></label>
        <input type="text" name="sku" value="{{ old('sku', $drug->sku ?? '') }}"
               placeholder="e.g. MS-SYR-020"
               autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
               data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other"
               class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30">
        <p class="text-xs text-[#64748b] mt-1">Kept from the product record when an item moves across.</p>
    </div>
</div>
