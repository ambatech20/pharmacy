@extends('layouts.app')

@section('content')
@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
@endphp
<div class="space-y-6">
    <section class="relative overflow-hidden rounded-2xl bg-[#10253b] px-6 py-7 text-white shadow-sm sm:px-8">
        <div class="absolute -right-12 -top-20 h-56 w-56 rounded-full border border-[#58c6b6]/20"></div>
        <div class="absolute right-16 -bottom-24 h-48 w-48 rounded-full border border-white/10"></div>
        <div class="relative flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-[.18em] text-[#72d4c5]">Pharmacy operations</p>
                <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $greeting }}, {{ explode(' ', trim(auth()->user()->name))[0] }}.</h1>
                <p class="mt-2 max-w-xl text-sm text-white/65">Here is the live picture of your pharmacy today.</p>
            </div>
            <div class="shrink-0 rounded-xl border border-white/10 bg-white/10 px-4 py-3 text-left backdrop-blur-sm sm:text-right">
                <p class="text-xs text-white/55">{{ now()->format('l, d M Y') }}</p>
                <p class="mt-1 text-sm font-semibold">{{ \App\Models\Setting::value('business_name', config('pmas.default_business_name')) }}</p>
            </div>
        </div>
    </section>

    {{-- Ending a shift is the last thing you do in a day. On its own row at
         the top right rather than inside the dark banner, so the button can be
         a colour you can actually read. --}}
    @if($shift)
        <div class="flex justify-end">
            <a href="{{ route('till-closings.index') }}"
               class="inline-flex items-center rounded-lg bg-[#15803d] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#166534]">
                End shift
            </a>
        </div>
    @endif

    @if($backupWarning ?? null)
        {{-- Deliberately on the screen everyone lands on after signing in.
             A backup nobody remembers to take is the same as no backup. --}}
        <div class="flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                <div>
                    <p class="text-sm font-semibold text-[#92400e]">Backup reminder</p>
                    <p class="mt-0.5 text-sm text-[#92400e]/90">{{ $backupWarning }}</p>
                </div>
            </div>
            <a href="{{ route('backups.index') }}"
               class="inline-flex flex-shrink-0 items-center rounded-lg bg-[#92400e] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#7c2d12]">
                Take a backup
            </a>
        </div>
    @endif

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5" aria-label="Pharmacy summary">
        <a href="{{ route('drugs.index') }}" class="group rounded-xl border border-[#dfe7ed] bg-white p-5 shadow-sm hover:-translate-y-0.5 hover:border-[var(--brand-primary)] hover:shadow-md">
            <span class="text-xs font-semibold uppercase tracking-wide text-[#64748b]">Drugs</span>
            <p class="mt-5 text-3xl font-bold text-[#182230] group-hover:text-[var(--brand-primary)]">{{ $totalDrugs }}</p><p class="mt-1 text-xs text-[#64748b]">View drug catalogue</p>
        </a>
        <a href="{{ route('products.index') }}" class="group rounded-xl border border-[#dfe7ed] bg-white p-5 shadow-sm hover:-translate-y-0.5 hover:border-[var(--brand-primary)] hover:shadow-md">
            <span class="text-xs font-semibold uppercase tracking-wide text-[#64748b]">Products</span>
            <p class="mt-5 text-3xl font-bold text-[#182230] group-hover:text-[var(--brand-primary)]">{{ $totalProducts }}</p><p class="mt-1 text-xs text-[#64748b]">View retail catalogue</p>
        </a>
        <div class="rounded-xl border border-[#dfe7ed] bg-white p-5 shadow-sm"><span class="text-xs font-semibold uppercase tracking-wide text-[#64748b]">Today's sales</span><p class="mt-5 text-2xl font-bold text-[#182230]">ETB {{ number_format($todaySales, 2) }}</p><p class="mt-1 text-xs text-[#64748b]">Collected today</p></div>
        <a href="{{ route('alerts.index') }}" class="group rounded-xl border border-[#dfe7ed] bg-white p-5 shadow-sm hover:-translate-y-0.5 hover:border-amber-400 hover:shadow-md"><span class="text-xs font-semibold uppercase tracking-wide text-[#64748b]">Low stock</span><p class="mt-5 text-3xl font-bold {{ $lowStockCount ? 'text-amber-600' : 'text-[#182230]' }}">{{ $lowStockCount }}</p><p class="mt-1 text-xs text-[#64748b]">Open stock alerts</p></a>
        <a href="{{ route('alerts.index') }}" class="group rounded-xl border border-[#dfe7ed] bg-white p-5 shadow-sm hover:-translate-y-0.5 hover:border-red-400 hover:shadow-md"><span class="text-xs font-semibold uppercase tracking-wide text-[#64748b]">Expiring soon</span><p class="mt-5 text-3xl font-bold {{ $expiringSoonCount ? 'text-red-600' : 'text-[#182230]' }}">{{ $expiringSoonCount }}</p><p class="mt-1 text-xs text-[#64748b]">Next {{ $expiryDays }} days</p></a>
    </section>

    @php
        $activeKeys = array_column($shortcuts, 'key');
        $pickable = array_values(array_filter(
            $shortcutChoices,
            fn ($choice) => ! in_array($choice['key'], $activeKeys, true)
        ));
    @endphp
    <section class="rounded-xl border border-[#dfe7ed] bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-[#64748b]">Shortcuts</p>
                <h2 class="mt-1 text-xl font-bold text-[#182230]">Common tasks</h2>
            </div>
            <div class="flex items-center gap-2">
                @if(count($activeKeys) !== count(\App\Http\Controllers\DashboardController::DEFAULT_SHORTCUTS))
                    <form method="POST" action="{{ route('dashboard.shortcuts.reset') }}">
                        @csrf
                        <button type="submit" class="rounded-lg px-3 py-2 text-xs font-semibold text-[#64748b] hover:bg-gray-100 hover:text-[#1E2530]">Reset</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-3">
            @forelse($shortcuts as $shortcut)
                <div class="flex items-stretch overflow-hidden rounded-lg {{ $shortcut['classes'] }}">
                    <a href="{{ $shortcut['url'] }}" class="min-h-[44px] flex-1 px-4 py-3 text-sm font-semibold">{{ $shortcut['label'] }} <span class="float-right">&rarr;</span></a>
                    <form method="POST" action="{{ route('dashboard.shortcuts.remove') }}" class="flex">
                        @csrf
                        <input type="hidden" name="shortcut" value="{{ $shortcut['key'] }}">
                        <button type="submit" title="Remove {{ $shortcut['label'] }}" aria-label="Remove {{ $shortcut['label'] }}"
                                class="px-2 text-lg leading-none opacity-50 transition hover:opacity-100">&times;</button>
                    </form>
                </div>
            @empty
                <p class="text-sm text-[#64748b]">No shortcuts yet. Add one below.</p>
            @endforelse
        </div>

        {{-- The "+" is a disclosure, not decoration: it opens the full list,
             ticked for what is already on the dashboard. --}}
        @if(!empty($pickable))
            <details class="mt-4">
                <summary class="inline-flex min-h-[44px] cursor-pointer items-center rounded-lg border border-dashed border-[#cbd5e1] px-4 py-2 text-sm font-semibold text-[#64748b] hover:border-[var(--brand-primary)] hover:text-[var(--brand-primary)]">
                    + Add a shortcut
                </summary>
                <form method="POST" action="{{ route('dashboard.shortcuts.update') }}" class="mt-4">
                    @csrf
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach($shortcutChoices as $choice)
                            <label class="flex min-h-[44px] cursor-pointer items-center gap-3 rounded-lg border border-[#e8eef1] px-4 py-2 hover:bg-gray-50">
                                <input type="checkbox" name="shortcuts[]" value="{{ $choice['key'] }}"
                                       @checked(in_array($choice['key'], $activeKeys, true))
                                       class="h-4 w-4 rounded border-[#cbd5e1] text-[var(--brand-primary)] focus:ring-[var(--brand-primary)]">
                                <span class="text-sm font-medium text-[#1E2530]">{{ $choice['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="submit" class="min-h-[44px] rounded-lg bg-[var(--brand-primary)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Save shortcuts</button>
                        <a href="{{ route('dashboard') }}" class="inline-flex min-h-[44px] items-center rounded-lg px-3 py-2 text-sm font-semibold text-[#64748b] hover:bg-gray-100">Cancel</a>
                    </div>
                </form>
            </details>
        @else
            <p class="mt-4 text-xs text-[#64748b]">Every available shortcut is already on your dashboard.</p>
        @endif
    </section>

    {{--
        One card, several segments. Stock/expiry, bills and supplier debt used
        to be three separate cards, which meant three headings and three "see
        all" links scattered down the page for one idea: things that need you.
        Each segment now disappears entirely when it has nothing in it, and
        when every segment is empty you get the all-clear line instead.
    --}}
    @php
        $stockAlerts = array_filter([
            $expiringSoonCount ? 'expiring' : null,
            $lowStockCount ? 'low' : null,
        ]);
        $supplierOverdue = !empty($supplierReminders) ? count($supplierReminders['overdue']) : 0;
        $supplierSoon = !empty($supplierReminders) ? count($supplierReminders['due_soon']) : 0;
        $supplierAlerts = ($supplierOverdue + $supplierSoon) > 0;
        $billCount = !empty($billsDue) ? $billsDue->count() : 0;
        $anyAlerts = $stockAlerts || $billCount || $supplierAlerts;
    @endphp
    <section class="rounded-xl border border-[#dfe7ed] bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 p-6 pb-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-[#64748b]">Attention needed</p>
                <h2 class="mt-1 text-xl font-bold text-[#182230]">
                    {{ $anyAlerts ? 'Things that need you' : 'All clear' }}
                </h2>
            </div>
            <a href="{{ route('alerts.index') }}" class="text-sm font-semibold text-[var(--brand-primary)] hover:underline">View all</a>
        </div>

        @if(!$anyAlerts)
            <div class="px-6 pb-6">
                <div class="rounded-lg bg-[#e7f7f4] px-4 py-3 text-sm text-[#08796f]">
                    Nothing needs attention right now. Stock levels, expiry dates and bills are all in order.
                </div>
            </div>
        @else
            <div class="divide-y divide-[#edf1f3] border-t border-[#edf1f3]">
                @if($stockAlerts)
                    <div class="p-5">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-[#64748b]">Stock and expiry</p>
                            <a href="{{ route('alerts.index') }}" class="text-xs font-semibold text-[var(--brand-primary)] hover:underline">See all</a>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @if(in_array('expiring', $stockAlerts, true))
                                <a href="{{ route('alerts.index') }}" class="flex items-center gap-3 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-800 hover:border-red-300 min-h-[44px]">
                                    <span class="h-2 w-2 shrink-0 rounded-full bg-red-500"></span>
                                    {{ $expiringSoonCount }} drug{{ $expiringSoonCount === 1 ? ' expires' : 's expire' }} within {{ $expiryDays }} days
                                </a>
                            @endif
                            @if(in_array('low', $stockAlerts, true))
                                <a href="{{ route('alerts.index') }}" class="flex items-center gap-3 rounded-lg border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800 hover:border-amber-300 min-h-[44px]">
                                    <span class="h-2 w-2 shrink-0 rounded-full bg-amber-500"></span>
                                    {{ $lowStockCount }} item{{ $lowStockCount === 1 ? ' needs' : 's need' }} restocking
                                </a>
                            @endif
                        </div>
                    </div>
                @endif

                @if($billCount)
                    <div class="p-5">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-[#64748b]">Bills to pay</p>
                            <a href="{{ route('alerts.index') }}" class="text-xs font-semibold text-[var(--brand-primary)] hover:underline">See all</a>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach($billsDue as $bill)
                                <a href="{{ route('expenses.create', ['bill' => $bill->id]) }}" class="flex items-center justify-between gap-3 rounded-lg border px-4 py-3 text-sm min-h-[44px] {{ $bill->isOverdue() ? 'border-red-100 bg-red-50 text-red-800 hover:border-red-300' : 'border-sky-100 bg-sky-50 text-sky-900 hover:border-sky-300' }}">
                                    <span class="flex items-center gap-2">
                                        <span class="h-2 w-2 shrink-0 rounded-full {{ $bill->isOverdue() ? 'bg-red-500' : 'bg-sky-500' }}"></span>
                                        {{ $bill->category->name ?? 'Bill' }} — ETB {{ number_format($bill->reminderAmount(), 2) }}<span class="block text-xs opacity-80">{{ $bill->paid_months > 1 ? 'per month' : '' }}</span>
                                    </span>
                                    <span class="text-xs font-medium whitespace-nowrap">
                                        @if($bill->isOverdue()) Overdue @else Due {{ $bill->reminderDate()?->format('d M') }} @endif
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($supplierAlerts)
                    <div class="p-5">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-[#64748b]">Owed to suppliers</p>
                            <a href="{{ route('supplier-owed.index') }}" class="text-xs font-semibold text-[var(--brand-primary)] hover:underline">See all</a>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @if($supplierOverdue)
                                <a href="{{ route('supplier-owed.index') }}" class="flex items-center gap-3 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-800 hover:border-red-300 min-h-[44px]">
                                    <span class="h-2 w-2 shrink-0 rounded-full bg-red-500"></span>
                                    {{ $supplierOverdue }} payment{{ $supplierOverdue === 1 ? '' : 's' }} overdue — ETB {{ number_format(array_sum(array_column($supplierReminders['overdue'], 'balance')), 2) }}
                                </a>
                            @endif
                            @if($supplierSoon)
                                <a href="{{ route('supplier-owed.index') }}" class="flex items-center gap-3 rounded-lg border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800 hover:border-amber-300 min-h-[44px]">
                                    <span class="h-2 w-2 shrink-0 rounded-full bg-amber-500"></span>
                                    {{ $supplierSoon }} due within {{ $supplierReminders['soon_days'] }} days — ETB {{ number_format(array_sum(array_column($supplierReminders['due_soon'], 'balance')), 2) }}
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </section>
</div>
@endsection
