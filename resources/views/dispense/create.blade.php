@extends('layouts.app')

@section('content')

@php
    // Packing, marker and shelf price per item, filled in by the browser as the
    // cashier picks. Only the picked item's rungs are fetched, so the page does
    // not ship the whole catalogue's numbers.
    $packingData = [];

    // The dropdown itself lists the whole catalogue. Key and label only:
    // enough to pick from, a few KB for a few hundred items.
    $itemOptionsHtml = collect($items)
        ->map(fn (array $item) => '<option value="' . e($item['key']) . '">' . e($item['label']) . '</option>')
        ->implode('');
@endphp

{{-- Wide enough that the item dropdown can show the full name plus its
     dispensing marker ("#1 · 40 left") without truncating. --}}
<div class="max-w-5xl mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-1">New Sale</h1>

    <p class="text-sm text-[#64748b] mb-6">
        Add drugs or products to the cart, then check out as a single sale.
    </p>

    <form method="POST" action="{{ route('dispense.store') }}" autocomplete="off" data-form-type="other" class="space-y-5">
        @csrf

        <!-- ========================================================= -->
        <!-- CART -->
        <!-- ========================================================= -->

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">

            <label class="block text-sm font-medium text-[#1E2530] mb-3">
                Cart
            </label>

            <div class="relative mb-4">
                <input id="catalog-search" type="search" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                       data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other"
                       placeholder="Search drugs or products by name, strength, or SKU" class="w-full border border-gray-200 rounded-lg px-4 py-3 text-sm focus:border-[var(--brand-primary)] focus:ring-4 focus:ring-[var(--brand-primary)]/10">
                <span id="catalog-search-status" class="pointer-events-none absolute right-3 top-3 text-xs text-[#64748b]"></span>
                <div id="catalog-results" class="absolute left-0 right-0 top-full z-20 mt-1 hidden max-h-64 overflow-y-auto rounded-xl border border-[#dfe7ed] bg-white p-1 shadow-lg"></div>
            </div>

            <!-- Cart column headings -->
            <div class="hidden sm:grid grid-cols-[repeat(14,minmax(0,1fr))] gap-3 mb-2 px-1">
                <div class="col-span-7 text-xs font-medium text-[#64748b]">
                    Item
                </div>

                <div class="col-span-2 text-xs font-medium text-[#64748b]">
                    Unit
                </div>

                <div class="col-span-2 text-xs font-medium text-[#64748b]">
                    Qty
                </div>

                <div class="col-span-2 text-xs font-medium text-[#64748b]">
                    Price
                </div>

                <div class="col-span-1"></div>
            </div>

            <div id="cart-lines" class="space-y-2">

                <!-- First cart line -->
                <div class="grid grid-cols-[repeat(14,minmax(0,1fr))] gap-3 items-center cart-line">

                    <!-- Drug -->
                    <div class="col-span-7">
                        <select
                            name="items[0][drug_id]"
                            class="drug-select w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[42px]"
                            required
                        >
                            {{-- Every item, so the list works on its own. The
                                 search box above only narrows the choice. --}}
                            <option value="">-- Select an item --</option>
                            {!! $itemOptionsHtml !!}
                        </select>
                    </div>

                    <!-- Unit -->
                    <div class="col-span-2">
                        <select
                            name="items[0][unit]"
                            class="unit-select w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[42px]"
                            required
                        >
                            {{-- Filled when an item is picked, from that item's
                                 own sellable rungs. Never a fixed list. --}}
                            <option value="">Unit</option>
                        </select>
                    </div>

                    <!-- Quantity -->
                    <div class="col-span-2">
                        <input
                            type="number"
                            min="1"
                            name="items[0][quantity]"
                            placeholder="Qty"
                            value="1"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[42px]"
                            required
                        >
                    </div>

                    <!-- Price — locked. Comes from the newest batch in stock;
                         change it on the batch price screen, not per sale. -->
                    <div class="col-span-2">
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="items[0][selling_price]"
                            placeholder="Price"
                            readonly
                            title="Taken from the newest batch in stock. To change it, edit that batch's price."
                            class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2.5 text-sm min-h-[42px] cursor-not-allowed"
                            required
                        >
                        @if(auth()->user()->role === \App\Models\User::ROLE_OWNER_ADMIN)
                            <input type="number" step="0.01" min="0" name="items[0][discount]"
                                   placeholder="Discount (owner only)"
                                   class="mt-2 w-full border border-amber-200 bg-amber-50 rounded-lg px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-amber-300/50">
                        @endif
                    </div>

                    <!-- Remove -->
                    <div class="col-span-1 text-center"></div>

                </div>

            </div>

            <p class="text-xs text-[#64748b] mt-3">
                Qty and price are for the unit you pick — pack, strip or tablet,
                whichever this drug allows.
                Stock is deducted in base units.
            </p>

            <button
                type="button"
                id="add-line-btn"
                class="mt-3 text-[var(--brand-primary)] text-sm font-semibold hover:underline"
            >
                + Add another item
            </button>

            @if(! $hasCatalogueItems)
                <p class="text-sm text-red-600 mt-2">
                    No drugs or products in the catalog yet —
                    <a
                        href="{{ route('drugs.create') }}"
                        class="underline"
                    >
                        add one first
                    </a>.
                </p>
            @endif

        </div>


        <!-- ========================================================= -->
        <!-- CUSTOMER -->
        <!-- ========================================================= -->

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">

            <label class="block text-sm font-medium text-[#1E2530] mb-2.5">
                Customer
            </label>

            <div class="flex flex-wrap gap-4 mb-3 text-sm">

                <label class="flex items-center gap-1.5 cursor-pointer">
                    <input
                        type="radio"
                        name="customer_mode"
                        value="anonymous"
                        checked
                        class="customer-mode-radio text-[var(--brand-primary)]"
                    >
                    Walk-in (no name)
                </label>

                <label class="flex items-center gap-1.5 cursor-pointer">
                    <input
                        type="radio"
                        name="customer_mode"
                        value="new"
                        class="customer-mode-radio text-[var(--brand-primary)]"
                    >
                    New customer
                </label>

                @if($recurringCustomers->count() > 0)

                    <label class="flex items-center gap-1.5 cursor-pointer">
                        <input
                            type="radio"
                            name="customer_mode"
                            value="existing"
                            class="customer-mode-radio text-[var(--brand-primary)]"
                        >
                        Returning (recurring) customer
                    </label>

                @endif

            </div>


            <!-- New Customer -->

            <div
                id="panel-new"
                class="space-y-3 hidden border border-gray-100 rounded-lg p-4 bg-gray-50/50"
            >

                <div>
                    <label class="block text-xs font-medium text-[#64748b] mb-1">
                        Name
                    </label>

                    <input
                        type="text"
                        name="new_customer_name"
                        autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                        data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm"
                    >
                </div>


                <div>
                    <label class="block text-xs font-medium text-[#64748b] mb-1">
                        Phone
                    </label>

                    <input
                        type="text"
                        name="new_customer_phone"
                        autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                        data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm"
                    >
                </div>


                <label class="flex items-center gap-2 text-sm cursor-pointer">

                    <input
                        type="checkbox"
                        name="is_recurring"
                        value="1"
                        id="is-recurring-checkbox"
                    >

                    Mark as recurring customer
                    (get a refill reminder)

                </label>


                <div
                    id="recurring-fields"
                    class="hidden space-y-2 pl-6"
                >

                    <div>

                        <label class="block text-xs font-medium text-[#64748b] mb-1">
                            Remind every (days)
                        </label>

                        <input
                            type="number"
                            name="reminder_interval_days"
                            value="30"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm"
                        >

                    </div>


                    <div>

                        <label class="block text-xs font-medium text-[#64748b] mb-1">
                            Note (optional)
                        </label>

                        <input
                            type="text"
                            name="condition_note"
                            placeholder="e.g. Diabetic — monthly refill"
                            autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                            data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm"
                        >

                    </div>

                </div>

            </div>


            <!-- Existing Customer -->

            @if($recurringCustomers->count() > 0)

                <div
                    id="panel-existing"
                    class="hidden"
                >

                    <select
                        name="customer_id"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                    >

                        <option value="">
                            -- Select Customer --
                        </option>

                        @foreach($recurringCustomers as $customer)

                            <option value="{{ $customer->id }}">
                                {{ $customer->name }}
                                ({{ $customer->phone ?? 'no phone' }})
                            </option>

                        @endforeach

                    </select>

                </div>

            @endif

        </div>


        <!-- ========================================================= -->
        <!-- PAYMENT -->
        <!-- ========================================================= -->

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">

            <div class="flex justify-between items-baseline mb-2.5">

                <label class="block text-sm font-medium text-[#1E2530]">
                    Payment
                </label>

                <p class="text-sm text-[#64748b]">

                    Cart total:

                    <span
                        id="cart-total"
                        class="font-semibold text-[#1E2530]"
                    >
                        0.00
                    </span>

                </p>

            </div>


            <p class="text-xs text-[#64748b] mb-3">
                One method for a normal sale, or add another row to split
                (must sum to the cart total).
            </p>


            <div
                id="payment-lines"
                class="space-y-3"
            >

                <div
                    class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end payment-line"
                >

                    <div class="sm:col-span-5">

                        <label class="block text-xs text-[#64748b] mb-1">
                            Payment method
                        </label>

                        <select
                            name="payments[0][payment_method]"
                            class="payment-method-select w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[42px]"
                            required
                        >

                            @foreach($paymentMethods as $value => $label)

                                <option
                                    value="{{ $value }}"
                                    @selected($value === 'cash')
                                >
                                    {{ $label }}
                                </option>

                            @endforeach

                        </select>

                    </div>


                    <div class="sm:col-span-3">

                        <label class="block text-xs text-[#64748b] mb-1">
                            Amount
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            name="payments[0][amount]"
                            placeholder="Amount"
                            class="payment-amount w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[42px]"
                            required
                        >

                    </div>


                    <div class="sm:col-span-3 transaction-ref-field hidden">

                        <label class="block text-xs text-[#64748b] mb-1">
                            Transaction ref
                        </label>

                        <input
                            type="text"
                            name="payments[0][transaction_reference]"
                            placeholder="Provider txn ID"
                            autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                            data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[42px]"
                        >

                    </div>


                    <div class="sm:col-span-1"></div>

                </div>

            </div>


            <button
                type="button"
                id="add-payment-btn"
                class="mt-3 text-[var(--brand-primary)] text-sm font-semibold hover:underline"
            >
                + Split with another method
            </button>

        </div>


        <!-- ========================================================= -->
        <!-- BUTTONS -->
        <!-- ========================================================= -->

        <div class="flex gap-3">

            <button
                type="submit"
                class="bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-medium text-sm py-2.5 px-5 rounded-lg transition-colors"
            >
                Complete Sale
            </button>

            <a
                href="{{ route('dispense.index') }}"
                class="text-[#64748b] hover:text-[#1E2530] text-sm py-2.5 px-4"
            >
                Cancel
            </a>

        </div>

    </form>

</div>


<script>

(function () {

    // ============================================================
    // CUSTOMER MODE
    // ============================================================

    const radios =
        document.querySelectorAll('.customer-mode-radio');

    const panelNew =
        document.getElementById('panel-new');

    const panelExisting =
        document.getElementById('panel-existing');

    const recurringCheckbox =
        document.getElementById('is-recurring-checkbox');

    const recurringFields =
        document.getElementById('recurring-fields');


    function updatePanels() {

        const checkedRadio =
            document.querySelector(
                '.customer-mode-radio:checked'
            );

        if (!checkedRadio) return;

        const mode =
            checkedRadio.value;


        panelNew.classList.toggle(
            'hidden',
            mode !== 'new'
        );


        if (panelExisting) {

            panelExisting.classList.toggle(
                'hidden',
                mode !== 'existing'
            );

        }

    }


    radios.forEach(function (radio) {

        radio.addEventListener(
            'change',
            updatePanels
        );

    });


    updatePanels();


    if (recurringCheckbox) {

        recurringCheckbox.addEventListener(
            'change',
            function () {

                recurringFields.classList.toggle(
                    'hidden',
                    !this.checked
                );

            }
        );

    }


    // ============================================================
    // CART
    // ============================================================

    const cartLines =
        document.getElementById('cart-lines');

    const addLineBtn =
        document.getElementById('add-line-btn');

    // ============================================================
    // CATALOGUE SEARCH
    //
    // The catalogue used to be rendered into the first line's <select> and
    // filtered in the browser. That cost roughly 450 KB and 1.2 seconds with
    // 800 drugs, and grew from there. Rows are now fetched a few at a time as
    // the cashier types, and each match arrives already carrying its unit
    // packing, dispensing marker and locked shelf price - so choosing an item
    // still costs exactly one request.
    // ============================================================

    const catalogSearch = document.getElementById('catalog-search');
    const catalogStatus = document.getElementById('catalog-search-status');
    const catalogResults = document.getElementById('catalog-results');
    const SEARCH_URL = @json(route('search.drug'));
    const ITEM_URL = @json(route('item.data'));

    // New lines get the same list as the first one.
    const drugOptionsHtml = @json('<option value="">-- Select an item --</option>' . $itemOptionsHtml);

    // Packing data for items already seen this session. Empty on load.
    const packing = @json((object) $packingData);

    function rememberItem(item) {
        packing[item.key] = {
            levels: item.levels || [],
            price: item.price,
            label: item.label,
            marker: item.marker
        };
    }

    // The dropdown carries only key and label, so the rungs, price and stock
    // marker for the picked item are fetched once and remembered for this page.
    function loadItem(key, done) {
        if (!key || packing[key]) {
            done();
            return;
        }

        fetch(ITEM_URL + '?key=' + encodeURIComponent(key), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Could not load that item');
                }
                return response.json();
            })
            .then(rememberItem)
            .catch(function () {
                catalogStatus.textContent = 'Could not load that item';
            })
            .finally(done);
    }

    // Each cart line has its own <select>, so an option offered by one line is
    // not automatically offered by another.
    function ensureOption(select, item) {
        if (select.querySelector('option[value="' + CSS.escape(item.key) + '"]')) {
            return;
        }

        const option = document.createElement('option');
        option.value = item.key;
        option.textContent = item.marker ? item.label + ' — ' + item.marker : item.label;
        select.appendChild(option);
    }

    function addCatalogItem(item) {
        let row = [...document.querySelectorAll('.cart-line')].find(function (cartRow) {
            return !cartRow.querySelector('.drug-select').value;
        });

        if (!row) {
            addLineBtn.click();
            row = cartLines.lastElementChild;
        }

        rememberItem(item);

        const select = row.querySelector('.drug-select');
        ensureOption(select, item);
        select.value = item.key;
        syncLineUnits(row);
        select.dispatchEvent(new Event('change', { bubbles: true }));
        catalogSearch.value = '';
        catalogResults.innerHTML = '';
        catalogResults.classList.add('hidden');
        catalogStatus.textContent = 'Added ' + item.label;
        updateCartTotal();
        row.querySelector('input[name*="[quantity]"]')?.focus();
    }

    function renderResults(items) {
        catalogResults.innerHTML = '';

        if (!items.length) {
            catalogStatus.textContent = 'No match';
            catalogResults.classList.add('hidden');
            return;
        }

        catalogStatus.textContent = items.length + ' match' + (items.length === 1 ? '' : 'es');

        items.slice(0, 8).forEach(function (item) {
            const result = document.createElement('button');
            result.type = 'button';
            result.className = 'block w-full rounded-lg px-3 py-2 text-left text-sm text-[#182230] hover:bg-[#e7f7f4]';

            // textContent, never innerHTML: item names come from the catalogue
            // and a name like "<script>" must not become markup.
            const title = document.createElement('span');
            title.textContent = item.label;
            result.appendChild(title);

            if (item.marker) {
                const marker = document.createElement('span');
                marker.className = 'ml-2 text-xs text-[#64748b]';
                marker.textContent = item.marker;
                result.appendChild(marker);
            }

            if (item.price !== null && item.price !== undefined) {
                const price = document.createElement('span');
                price.className = 'float-right text-xs text-[#64748b]';
                // The locked price is per base unit, so say which one. A box
                // of 100 tablets at 6.00 a tablet must not read as 6.00 a box.
                const smallest = (item.levels && item.levels.length)
                    ? item.levels[item.levels.length - 1].name
                    : 'unit';
                price.textContent = 'ETB ' + Number(item.price).toFixed(2) + ' / ' + smallest;
                result.appendChild(price);
            }

            result.addEventListener('click', function () {
                addCatalogItem(item);
            });

            catalogResults.appendChild(result);
        });

        catalogResults.classList.remove('hidden');
    }

    let searchTimer = null;
    let searchController = null;

    function searchCatalog() {
        const term = catalogSearch.value.trim();

        // Debounced, so typing "amoxicillin" fires one request instead of ten,
        // and the previous one is aborted rather than racing the newest.
        clearTimeout(searchTimer);

        if (term.length < 2) {
            catalogResults.innerHTML = '';
            catalogResults.classList.add('hidden');
            catalogStatus.textContent = term ? 'Keep typing' : '';
            return;
        }

        catalogStatus.textContent = 'Searching...';

        searchTimer = setTimeout(function () {
            if (searchController) {
                searchController.abort();
            }

            searchController = new AbortController();

            fetch(SEARCH_URL + '?q=' + encodeURIComponent(term), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: searchController.signal
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Search failed');
                    }
                    return response.json();
                })
                .then(function (data) {
                    const items = data.results || [];
                    items.forEach(rememberItem);
                    renderResults(items);
                })
                .catch(function (error) {
                    if (error.name === 'AbortError') {
                        return;
                    }
                    catalogStatus.textContent = 'Search failed';
                    catalogResults.classList.add('hidden');
                });
        }, 200);
    }

    catalogSearch.addEventListener('input', searchCatalog);
    catalogSearch.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            const firstMatch = catalogResults.querySelector('button');
            if (firstMatch) firstMatch.click();
        }
    });
    document.addEventListener('click', function (event) {
        if (!event.target.closest('#catalog-search') && !event.target.closest('#catalog-results')) catalogResults.classList.add('hidden');
    });


    // Discounts are an Owner/Admin-only lever, so the field is only rendered
    // for them. The server refuses a discount from anyone else regardless.
    const canDiscount =
        @json(auth()->user()->role === \App\Models\User::ROLE_OWNER_ADMIN);

    const discountField =
        canDiscount
            ? `<input type="number" step="0.01" min="0" name="items[\${lineIndex}][discount]" placeholder="Discount (owner only)" class="mt-2 w-full border border-amber-200 bg-amber-50 rounded-lg px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-amber-300/50">`
            : '';


    let lineIndex = 1;


    // The units this item may be sold as, biggest first, each showing how many
    // base units it holds. Built from the item's own record, so a drug that is
    // sold whole offers one option and a pack of strips offers three.
    function unitOptionsHtml(itemKey) {

        const p = packing[itemKey];

        if (!p || !p.levels || !p.levels.length) {
            return '<option value="">Unit</option>';
        }

        return p.levels.map(function (level) {

            const name = level.name.charAt(0).toUpperCase() + level.name.slice(1);
            const holds = level.factor > 1 ? ' (' + level.factor + ')' : '';

            return '<option value="' + level.key + '">' + name + holds + '</option>';

        }).join('');

    }


    function syncLineUnits(row) {

        const drugSelect =
            row.querySelector(
                'select[name*="[drug_id]"]'
            );


        const unitSelect =
            row.querySelector(
                '.unit-select'
            );


        if (!drugSelect || !unitSelect) {
            return;
        }


        const current =
            unitSelect.value;


        unitSelect.innerHTML =
            unitOptionsHtml(
                drugSelect.value
            );


        const values =
            [...unitSelect.options].map(
                function (option) {
                    return option.value;
                }
            );


        // Keep the cashier's choice when the newly picked item has the same
        // rung. Otherwise start at the smallest unit: the safe default for a
        // counter sale, and what the old screen did with "base".
        if (
            current && values.includes(current)
        ) {

            unitSelect.value = current;

        } else if (values.includes('base')) {

            unitSelect.value = 'base';

        } else if (values.length) {

            unitSelect.selectedIndex = 0;

        }

        syncPrice(row);

    }


    // The price is locked: it comes from the newest batch that still has stock
    // (per base unit) and is only shown so the seller can see it. The server
    // re-derives the same number on submit, so this box cannot be used to
    // change what a customer is charged.
    function syncPrice(row) {

        const drugSelect = row.querySelector('select[name*="[drug_id]"]');
        const priceInput = row.querySelector('input[name*="[selling_price]"]');

        if (!drugSelect || !priceInput) {
            return;
        }

        if (!drugSelect.value) {
            priceInput.value = '';
            return;
        }

        const data = packing[drugSelect.value];
        if (!data || data.price === null || data.price === undefined) {
            priceInput.value = '';
            return;
        }

        const unitSelect = row.querySelector('.unit-select');
        const unit = unitSelect ? unitSelect.value : 'base';
        const level = (data.levels || []).find(function (entry) {
            return entry.key === unit;
        });
        const factor = level ? level.factor : 1;

        priceInput.value = (parseFloat(data.price) * factor).toFixed(2);

    }


    // ============================================================
    // ADD DRUG
    // ============================================================

    addLineBtn.addEventListener(
        'click',
        function () {

            const row =
                document.createElement('div');


            // IMPORTANT:
            // Always keep every drug on ONE horizontal row.
            row.className =
                'grid grid-cols-[repeat(14,minmax(0,1fr))] gap-3 items-center cart-line';


            row.innerHTML = `

                <div class="col-span-7">

                    <select
                        name="items[${lineIndex}][drug_id]"
                        class="drug-select w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[42px]"
                        required
                    >
                        ${drugOptionsHtml}
                    </select>

                </div>


                <div class="col-span-2">

                    <select
                        name="items[${lineIndex}][unit]"
                        class="unit-select w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[42px]"
                        required
                    >

                        <option value="">
                            Unit
                        </option>

                    </select>

                </div>


                <div class="col-span-2">

                    <input
                        type="number"
                        min="1"
                        name="items[${lineIndex}][quantity]"
                        placeholder="Qty"
                        value="1"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[42px]"
                        required
                    >

                </div>


                <div class="col-span-2">

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="items[${lineIndex}][selling_price]"
                        placeholder="Price"
                        readonly
                        title="Taken from the newest batch in stock. To change it, edit that batch's price."
                        class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2.5 text-sm min-h-[42px] cursor-not-allowed"
                        required
                    >

                    ${discountField}

                </div>


                <div class="col-span-1 text-center">

                    <button
                        type="button"
                        class="remove-line-btn text-red-500 hover:text-red-700 text-xl leading-none"
                        title="Remove drug"
                    >
                        &times;
                    </button>

                </div>

            `;


            cartLines.appendChild(row);


            syncLineUnits(row);


            lineIndex++;


            updateCartTotal();

        }
    );


    // ============================================================
    // DRUG CHANGE
    // ============================================================

    cartLines.addEventListener(
        'change',
        function (e) {

            if (
                e.target.matches(
                    'select[name*="[drug_id]"]'
                )
            ) {

                const row =
                    e.target.closest('.cart-line');

                const select =
                    e.target;

                // The dropdown carries the whole catalogue but only the
                // picked item's rungs and price, so fetch first, then fill
                // the unit and price boxes.
                loadItem(
                    select.value,
                    function () {
                        const info =
                            packing[select.value];

                        const option =
                            select.selectedOptions[0];

                        // Show the batch the sale will take from ("#1 - 40
                        // left") once it is known, as the search list does.
                        if (option && info && info.label) {
                            option.textContent =
                                info.marker
                                    ? info.label + ' — ' + info.marker
                                    : info.label;
                        }

                        syncLineUnits(row);
                    }
                );

            }


            // Changing the unit changes what one of them costs, so the locked
            // price box has to follow. It used to only update on item change,
            // which showed the base price for a strip.
            if (
                e.target.matches(
                    '.unit-select'
                )
            ) {

                syncPrice(
                    e.target.closest('.cart-line')
                );

            }

        }
    );


    // ============================================================
    // REMOVE DRUG
    // ============================================================

    cartLines.addEventListener(
        'click',
        function (e) {

            if (
                e.target.classList.contains(
                    'remove-line-btn'
                )
            ) {

                e.target
                    .closest('.cart-line')
                    .remove();


                updateCartTotal();

            }

        }
    );


    // ============================================================
    // CART INPUT
    // ============================================================

    cartLines.addEventListener(
        'input',
        function (e) {

            if (
                e.target.matches(
                    'input[name*="[quantity]"]'
                )
                ||
                e.target.matches(
                    'input[name*="[selling_price]"]'
                )
                ||
                e.target.matches(
                    'input[name*="[discount]"]'
                )
            ) {

                updateCartTotal();

            }

        }
    );


    // ============================================================
    // PAYMENTS
    // ============================================================

    const paymentLines =
        document.getElementById(
            'payment-lines'
        );


    const addPaymentBtn =
        document.getElementById(
            'add-payment-btn'
        );


    const paymentSelect =
        paymentLines.querySelector(
            'select'
        );


    const paymentMethodOptionsHtml =
        paymentSelect
            ? paymentSelect.innerHTML
            : '';


    let paymentIndex = 1;


    function cartTotal() {

        let total = 0;


        cartLines
            .querySelectorAll('.cart-line')
            .forEach(function (row) {

                const quantityInput =
                    row.querySelector(
                        'input[name*="[quantity]"]'
                    );


                const priceInput =
                    row.querySelector(
                        'input[name*="[selling_price]"]'
                    );


                const qty =
                    quantityInput
                        ? parseFloat(
                            quantityInput.value
                        ) || 0
                        : 0;


                const price =
                    priceInput
                        ? parseFloat(
                            priceInput.value
                        ) || 0
                        : 0;


                const discountInput =
                    row.querySelector(
                        'input[name*="[discount]"]'
                    );


                // Discount is entered per selected unit, same as the price.
                const discount =
                    discountInput
                        ? parseFloat(
                            discountInput.value
                        ) || 0
                        : 0;


                total +=
                    qty * Math.max(0, price - discount);

            });


        return Math.round(
            total * 100
        ) / 100;

    }


    function updateCartTotal() {

        const total =
            cartTotal();


        document.getElementById(
            'cart-total'
        ).textContent =
            total.toFixed(2);


        const amounts =
            paymentLines.querySelectorAll(
                '.payment-amount'
            );


        updatePaymentBalance(total);

    }


    function updatePaymentBalance(total = cartTotal()) {

        const lines = [...paymentLines.querySelectorAll('.payment-line')];
        const amounts = lines.map(row => row.querySelector('.payment-amount'));

        if (amounts.length === 1) {
            amounts[0].value = total > 0 ? total.toFixed(2) : '';
            return;
        }

        const cashLine = lines.find(row => row.querySelector('select')?.value === 'cash') || lines[0];
        const cashAmount = cashLine?.querySelector('.payment-amount');
        const otherTotal = lines
            .filter(row => row !== cashLine)
            .reduce((sum, row) => sum + (parseFloat(row.querySelector('.payment-amount')?.value) || 0), 0);

        if (cashAmount) {
            cashAmount.value = Math.max(0, total - otherTotal).toFixed(2);
        }

    }


    // ============================================================
    // ADD PAYMENT METHOD
    // ============================================================

    addPaymentBtn.addEventListener(
        'click',
        function () {

            const row =
                document.createElement('div');


            row.className =
                'grid grid-cols-1 sm:grid-cols-12 gap-3 items-end payment-line';


            row.innerHTML = `

                <div class="sm:col-span-5">

                    <label class="block text-xs text-[#64748b] mb-1">
                        Payment method
                    </label>


                    <select
                        name="payments[${paymentIndex}][payment_method]"
                        class="payment-method-select w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[42px]"
                        required
                    >
                        ${paymentMethodOptionsHtml}
                    </select>

                </div>


                <div class="sm:col-span-3">

                    <label class="block text-xs text-[#64748b] mb-1">
                        Amount
                    </label>


                    <input
                        type="number"
                        step="0.01"
                        min="0.01"
                        name="payments[${paymentIndex}][amount]"
                        placeholder="Amount"
                        class="payment-amount w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[42px]"
                        required
                    >

                </div>


                <div class="sm:col-span-3 transaction-ref-field hidden">

                    <label class="block text-xs text-[#64748b] mb-1">
                        Transaction ref
                    </label>

                    <input
                        type="text"
                        name="payments[${paymentIndex}][transaction_reference]"
                        placeholder="Provider txn ID"
                        autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                        data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[42px]"
                    >

                </div>


                <div class="sm:col-span-1 text-center">

                    <button
                        type="button"
                        class="remove-payment-btn text-red-500 hover:text-red-700 text-xl leading-none"
                        title="Remove payment"
                    >
                        &times;
                    </button>

                </div>

            `;


            paymentLines.appendChild(
                row
            );

            const newPaymentMethod = row.querySelector('select');
            if (newPaymentMethod.options.length > 1) {
                newPaymentMethod.selectedIndex = 1;
            }

            paymentIndex++;
            updatePaymentBalance();

        }
    );


    // ============================================================
    // REMOVE PAYMENT
    // ============================================================

    paymentLines.addEventListener(
        'click',
        function (e) {

            if (
                e.target.classList.contains(
                    'remove-payment-btn'
                )
            ) {

                e.target
                    .closest('.payment-line')
                    .remove();


                updateCartTotal();

            }

        }
    );


    paymentLines.addEventListener(
        'input',
        function (e) {
            if (e.target.matches('.payment-amount') && e.target.closest('.payment-line')?.querySelector('select')?.value !== 'cash') {
                updatePaymentBalance();
            }
        }
    );


    paymentLines.addEventListener(
        'change',
        function (e) {
            if (e.target.matches('select')) {
                updatePaymentBalance();
                toggleTransactionRef(e.target.closest('.payment-line'));
            }
        }
    );


    function toggleTransactionRef(line) {
        if (!line) return;
        const select = line.querySelector('.payment-method-select');
        const refField = line.querySelector('.transaction-ref-field');
        if (!select || !refField) return;
        if (select.value === 'cash' || select.value === 'check') {
            refField.classList.add('hidden');
        } else {
            refField.classList.remove('hidden');
        }
    }

    document.querySelectorAll('.payment-line').forEach(toggleTransactionRef);


    // ============================================================
    // INITIAL SETUP
    // ============================================================

    updateCartTotal();


    cartLines
        .querySelectorAll('.cart-line')
        .forEach(
            syncLineUnits
        );

})();

</script>

@endsection
