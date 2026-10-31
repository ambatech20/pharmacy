@php
    /*
        One block, used by both the add and the edit form, so the two can never
        describe packing differently.

        The sentence underneath is the whole point: it names what is inside one
        pack and how many, and it uses this drug's own numbers. Nothing here
        assumes a strip of ten.
    */
    $state = $packing ? $packing->packingFormState() : [
        'unit_top' => 'pack',
        'top_holds' => '',
        'inner' => 'none',
        'middle_holds' => '',
        'unit_base' => 'tablet',
        'sell_middle' => true,
        'sell_base' => true,
    ];

    $unitTop = old('unit_top', $state['unit_top']);
    $topHolds = old('top_holds', $state['top_holds']);
    $inner = old('inner', $state['inner']);
    $middleHolds = old('middle_holds', $state['middle_holds']);
    $unitBase = old('unit_base', $state['unit_base']);
    $sellMiddle = old('sell_middle', $state['sell_middle'] ? '1' : null);
    $sellBase = old('sell_base', $state['sell_base'] ? '1' : null);

    $tops = [
        'pack' => 'Pack',
        'box' => 'Box',
        'bottle' => 'Bottle',
        'tin' => 'Tin',
        'vial' => 'Vial',
        'tube' => 'Tube',
    ];

    // Keep an unusual existing unit selectable rather than letting the first
    // option win by default, which would rewrite the drug on save.
    if ($unitTop && ! array_key_exists($unitTop, $tops)) {
        $tops[$unitTop] = ucfirst($unitTop);
    }

    $bases = $baseOptions ?? [
        'tablet' => 'Tablets',
        'capsule' => 'Capsules',
        'suppository' => 'Suppositories',
    ];

    if ($unitBase && ! array_key_exists($unitBase, $bases)) {
        $bases[$unitBase] = ucfirst($unitBase);
    }
@endphp

<div class="rounded-xl border border-gray-200 p-4 space-y-4" id="packing">
    <p class="text-xs uppercase tracking-wide text-[#64748b] font-semibold">How you buy it</p>

    <div>
        <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Bought as</label>
        <select name="unit_top" id="unit_top"
                class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm">
            @foreach($tops as $value => $label)
                <option value="{{ $value }}" @selected($unitTop === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <p class="text-xs text-[#64748b] mt-1">Whichever word your supplier's invoice uses.</p>
    </div>

    <div id="inner-row">
        <label class="block text-sm font-medium text-[#1E2530] mb-1.5">
            One <span id="top-word">pack</span> contains
        </label>
        <div class="flex gap-2">
            <input type="number" min="1" max="10000" name="top_holds" id="top_holds"
                   value="{{ $topHolds }}"
                   class="w-28 border border-gray-200 rounded-lg px-3 py-2.5 text-sm text-center">
            <select name="inner" id="inner"
                    class="flex-1 border border-gray-200 rounded-lg px-3 py-2.5 text-sm">
                <option value="strip" @selected($inner === 'strip')>Strips</option>
                <option value="sachet" @selected($inner === 'sachet')>Sachets</option>
                <option value="ampoule" @selected($inner === 'ampoule')>Ampoules</option>
                <option value="bottle" @selected($inner === 'bottle')>Bottles</option>
                <option value="vial" @selected($inner === 'vial')>Vials</option>
                <option value="tin" @selected($inner === 'tin')>Tins</option>
                <option value="tube" @selected($inner === 'tube')>Tubes</option>
                <option value="loose" @selected($inner === 'loose')>Loose items (tablets, pieces)</option>
                <option value="none" @selected($inner === 'none')>Nothing — sold as one unit</option>
            </select>
        </div>
    </div>

    <div id="strip-row">
        <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Each strip holds</label>
        <input type="number" min="1" max="10000" name="middle_holds" id="middle_holds"
               value="{{ $middleHolds }}"
               class="w-28 border border-gray-200 rounded-lg px-3 py-2.5 text-sm text-center">
    </div>

    <div id="base-row">
        <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Counted in</label>
        <select name="unit_base" id="unit_base"
                class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm">
            @foreach($bases as $value => $label)
                <option value="{{ $value }}" @selected($unitBase === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <p class="text-sm text-[#0B6B60] bg-[#F0FBF9] border border-[#C7EDE6] rounded-lg px-3 py-2"
       id="packing-summary">—</p>

    <div>
        <label class="block text-sm font-medium text-[#1E2530] mb-1.5">What you may sell it as</label>
        <div class="flex flex-wrap gap-2">
            <label class="inline-flex items-center gap-2 border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-[#64748b]">
                <input type="checkbox" checked disabled>
                <span id="sell-top-label">Pack</span>
            </label>
            <label id="sell-middle-wrap" class="inline-flex items-center gap-2 border border-gray-200 rounded-lg px-3 py-2 text-sm cursor-pointer">
                <input type="checkbox" name="sell_middle" value="1" @checked($sellMiddle)>
                <span id="sell-middle-label">Strip</span>
            </label>
            <label id="sell-base-wrap" class="inline-flex items-center gap-2 border border-gray-200 rounded-lg px-3 py-2 text-sm cursor-pointer">
                <input type="checkbox" name="sell_base" value="1" @checked($sellBase)>
                <span id="sell-base-label">Capsule</span>
            </label>
        </div>
        <p class="text-xs text-[#64748b] mt-1">Unticked units never appear at the till.</p>
    </div>

    <div>
        <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Minimum stock level</label>
        <div class="flex items-center gap-3">
            <input type="number" step="any" min="0" name="minimum_stock_level" id="minimum_stock_level"
                   value="{{ old('minimum_stock_level', $packing ? (0 + $packing->minimum_stock_level) : 0) }}"
                   class="w-28 border border-gray-200 rounded-lg px-3 py-2.5 text-sm text-center">
            <span class="text-sm text-[#64748b]">in <b id="min-unit" class="text-[#1E2530]">packs</b></span>
            <span class="text-xs text-[#94a3b8]" id="min-conv"></span>
        </div>
        <p class="text-xs text-[#64748b] mt-1">The alert fires when the shelf falls to this many.</p>
    </div>
</div>

<script>
(function () {
    var top = document.getElementById('unit_top');
    var inner = document.getElementById('inner');
    var topHolds = document.getElementById('top_holds');
    var middleHolds = document.getElementById('middle_holds');
    var base = document.getElementById('unit_base');
    var topWord = document.getElementById('top-word');
    var innerRow = document.getElementById('inner-row');
    var stripRow = document.getElementById('strip-row');
    var baseRow = document.getElementById('base-row');
    var summary = document.getElementById('packing-summary');
    var sellMiddleWrap = document.getElementById('sell-middle-wrap');
    var sellBaseWrap = document.getElementById('sell-base-wrap');
    var sellTopLabel = document.getElementById('sell-top-label');
    var sellMiddleLabel = document.getElementById('sell-middle-label');
    var sellBaseLabel = document.getElementById('sell-base-label');
    var minUnit = document.getElementById('min-unit');
    var minConv = document.getElementById('min-conv');
    var minStock = document.getElementById('minimum_stock_level');

    function plural(word, n) {
        if (n === 1) return word;
        if (word === 'box') return 'boxes';
        if (word === 'suppository') return 'suppositories';
        if (word === 'pessary') return 'pessaries';
        return word + 's';
    }

    // A container cannot contain itself, and a strip cannot hold strips. Hide
    // those impossible choices instead of letting the server reject the form
    // after the person has typed everything else.
    function disableOption(select, value) {
        [].forEach.call(select.options, function (option) {
            option.disabled = option.value !== '' && option.value === value;
        });
    }

    function firstEnabled(select) {
        for (var i = 0; i < select.options.length; i++) {
            if (!select.options[i].disabled) return select.options[i];
        }

        return null;
    }

    function render() {
        var t = top.value;
        // A piece is one piece. Every container - pack, box, bottle, tin,
        // vial or tube - may hold something countable, or choose "Nothing" to
        // be sold whole. The record's count, not its container word, decides.
        var soldWhole = (t === 'piece');
        var bulk = !soldWhole;

        if (bulk) {
            disableOption(inner, t);
        }

        var i = inner.value;

        if (bulk && i === t) {
            inner.value = 'none';
            i = inner.value;
        }

        if (bulk && (i === 'strip' || i === 'loose')) {
            disableOption(base, t);

            if (base.value === t) {
                var firstBase = firstEnabled(base);
                if (firstBase) base.value = firstBase.value;
            }
        } else {
            [].forEach.call(base.options, function (option) {
                option.disabled = false;
            });
        }

        var isStrip = bulk && i === 'strip';
        var needsCount = bulk && i !== 'none';
        var word = t;

        topWord.textContent = word;

        innerRow.style.display = bulk ? '' : 'none';
        topHolds.style.display = needsCount ? '' : 'none';
        stripRow.style.display = isStrip ? '' : 'none';
        baseRow.style.display = (isStrip || (bulk && i === 'loose')) ? '' : 'none';

        var holds = parseInt(topHolds.value, 10) || 1;
        var per = parseInt(middleHolds.value, 10) || 1;
        var b = base.value;

        // What is actually being counted: the container itself when sold whole,
        // the chosen base unit for strips and loose tablets, and otherwise
        // whatever sits inside (sachets, ampoules, bottles).
        var name;
        if (!bulk || i === 'none') {
            name = word;
        } else if (isStrip) {
            name = b;
        } else {
            name = (i === 'loose') ? b : i;
        }

        var text;
        var perTop = 1;

        if (!bulk || i === 'none') {
            text = 'Sold whole — 1 ' + word + ' is one ' + word + '.';
        } else if (isStrip) {
            perTop = holds * per;
            text = '1 ' + word + ' = ' + holds + ' ' + plural('strip', holds) + ' × ' + per + ' ' + plural(name, per)
                 + ' = ' + perTop.toLocaleString() + ' ' + plural(name, perTop);
        } else {
            perTop = holds;
            text = '1 ' + word + ' = ' + holds + ' ' + plural(name, holds);
        }

        summary.textContent = text;

        sellTopLabel.textContent = word;
        sellMiddleLabel.textContent = 'strip';
        sellBaseLabel.textContent = name;
        sellMiddleWrap.style.display = isStrip ? '' : 'none';
        sellBaseWrap.style.display = (!bulk || i === 'none') ? 'none' : '';

        minUnit.textContent = plural(word, 2);
        var min = parseInt(minStock.value, 10) || 0;
        minConv.textContent = perTop > 1
            ? '= ' + (min * perTop).toLocaleString() + ' ' + plural(name, min * perTop)
            : '';
    }

    ['unit_top', 'inner', 'unit_base'].forEach(function (id) {
        document.getElementById(id).addEventListener('change', render);
    });
    ['top_holds', 'middle_holds', 'minimum_stock_level'].forEach(function (id) {
        document.getElementById(id).addEventListener('input', render);
    });

    render();
})();
</script>
