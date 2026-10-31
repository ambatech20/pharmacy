{{--
    Shared month / year filter for the reports screens.

    Included with the route it posts back to:
    @include('partials.period-filter', ['action' => route('reports.sales')])

    Months are tickboxes, not a range: ticking January and March means those
    two months only. Ticking none and choosing a year covers all twelve.
--}}
<form method="GET" action="{{ $action }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-4">
    <div class="flex flex-wrap items-start gap-3">
        <div class="w-32">
            <label for="year" class="block text-xs font-semibold text-[#64748b] uppercase tracking-wide mb-1">Year</label>
            <select name="year" id="year" class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
                <option value="">All years</option>
                @foreach($period->years() as $year)
                    <option value="{{ $year }}" @selected($period->year === $year)>{{ $year }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex-1 min-w-[300px]">
            <span class="block text-xs font-semibold text-[#64748b] uppercase tracking-wide mb-1">Months</span>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-1.5">
                @foreach(\App\Support\ReportPeriod::MONTH_NAMES as $number => $name)
                    <label class="inline-flex items-center gap-1.5 min-h-[36px] px-2 rounded-lg border border-gray-200 bg-gray-50/60 text-sm text-[#1E2530] cursor-pointer hover:border-[var(--brand-primary)]/40">
                        <input type="checkbox"
                               name="months[]"
                               value="{{ $number }}"
                               @checked(in_array($number, $period->months, true))
                               class="rounded border-gray-300 text-[var(--brand-primary)] focus:ring-[var(--brand-primary)]">
                        <span>{{ $name }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="flex items-center gap-2 pt-5">
            <button type="submit" class="px-4 min-h-[44px] rounded-lg bg-[var(--brand-primary)] text-white text-sm font-semibold hover:opacity-90">Filter</button>
            @if($period->isFiltered())
                <a href="{{ $action }}" class="px-3 min-h-[44px] inline-flex items-center text-sm font-medium text-[#64748b] hover:text-[#1E2530]">Clear</a>
            @endif
        </div>
    </div>
    <p class="text-xs text-[#64748b] mt-2">
        {{ $hint ?? 'Tick any months to combine them into one total. Ticking none and choosing a year covers all twelve; leaving both blank covers everything on record.' }}
    </p>
</form>
