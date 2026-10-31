@php
    // PART D — branding from settings (never hard-coded; falls back to config default).
    // One cache read for all four values instead of four separate reads.
    $settings = \App\Models\Setting::allCached();
    $brandName = $settings['business_name'] ?? config('pmas.default_business_name');
    $brandPrimary = $settings['brand_primary_color'] ?? config('pmas.default_brand_primary_color');
    $brandAccent = $settings['brand_accent_color'] ?? config('pmas.default_brand_accent_color');
    $brandLogo = \App\Models\Setting::logoPath($settings['logo_path'] ?? null);
@endphp
<!DOCTYPE html>
{{-- Part D: brand colors applied through CSS variables. --}}
<html lang="en" style="@include('partials.brand-vars')">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $brandPrimary }}">
    <meta name="robots" content="noindex, nofollow">
    {{-- The desktop sidebar folds to a rail. The choice is read before the
         first paint so a collapsed menu never flashes open on every page. --}}
    <script>
    (function () {
        try {
            if (localStorage.getItem('pmas.sidebarCollapsed') === '1') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        } catch (e) {}
    })();
    </script>
    <style>
        /* Desktop fold: width only. Mobile keeps the existing off-canvas menu. */
        .sidebar-fold-open { display: none; }
        @media (min-width: 1024px) {
            aside[data-mobile-sidebar] { transition: width .2s ease, transform .2s ease; }
            html.sidebar-collapsed aside[data-mobile-sidebar] { width: 4rem !important; }
            html.sidebar-collapsed aside[data-mobile-sidebar] > div:first-child { padding-left: .5rem; padding-right: .5rem; }
            html.sidebar-collapsed aside[data-mobile-sidebar] .sidebar-full { display: none !important; }
            html.sidebar-collapsed aside[data-mobile-sidebar] .sidebar-fold-open { display: flex !important; }
            html.sidebar-collapsed .app-shell-main { padding-left: 4rem !important; }
            .app-shell-main { transition: padding-left .2s ease; }
        }
        @media (max-width: 1023.98px) {
            .sidebar-fold-close, .sidebar-fold-open { display: none !important; }
        }
    </style>
    @include('partials.head-icons')
    <title>{{ $brandName }} - Pharmacy System</title>
    <meta name="description" content="{{ $brandName }} pharmacy operations: medicines, retail products, stock, sales and suppliers.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#F5F7FA] text-[#1E2530]">
    <div id="offline-banner" class="hidden fixed top-0 inset-x-0 z-[9999] bg-red-600 text-white text-center text-sm py-2">You are offline. Sales and other actions cannot be saved until you reconnect.</div>
    <script>(function(){var b=document.getElementById('offline-banner');if(!b)return;function u(){b.classList.toggle('hidden',navigator.onLine);}u();window.addEventListener('online',u);window.addEventListener('offline',u);})();</script>

    <script>
    (function () {
        if (!('serviceWorker' in navigator)) return;
        var host = location.hostname;
        if (location.protocol !== 'https:' && host !== 'localhost' && host !== '127.0.0.1') return;
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js').catch(function () {});
        });
    })();
    </script>

    @auth
    {{-- Mobile top bar --}}
    <div class="lg:hidden flex items-center justify-between text-white px-4 py-3" style="background-color: var(--brand-accent);">
        <a href="{{ route('dashboard') }}" class="font-bold text-lg tracking-tight">{{ $brandName }}</a>
        <button data-sidebar-toggle aria-expanded="false" class="p-2 -mr-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand-primary)] rounded" aria-label="Toggle menu">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>

    {{-- Mobile overlay --}}
    <div data-sidebar-overlay class="hidden fixed inset-0 bg-black/40 z-30 lg:hidden"></div>

    {{-- Sidebar --}}
    <aside
        id="app-sidebar"
        data-mobile-sidebar class="fixed top-0 left-0 h-full w-64 text-[#B9C2D0] z-40 transform transition-transform duration-200 ease-in-out -translate-x-full lg:translate-x-0 flex flex-col shadow-[8px_0_24px_rgba(16,27,45,0.08)]" style="background-color: var(--brand-accent);">

        <div class="px-5 py-5 border-b border-white/10 flex items-center justify-between">
            <a href="{{ route('dashboard') }}" class="sidebar-full flex items-center gap-2">
                @if($brandLogo)
                    <img src="{{ Storage::url($brandLogo) }}" alt="{{ $brandName }} logo" class="h-8 w-8 object-contain rounded-lg bg-white p-0.5">
                @else
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm" style="background-color: var(--brand-primary);">{{ mb_strtoupper(mb_substr($brandName, 0, 1)) }}</span>
                @endif
                <span class="text-white font-bold text-lg tracking-tight">{{ $brandName }}</span>
            </a>
            <div class="sidebar-full flex items-center gap-1">
                <button type="button" data-sidebar-fold aria-controls="app-sidebar" aria-expanded="true" class="sidebar-fold-close inline-flex items-center justify-center text-white/60 hover:text-white focus:outline-none p-2 rounded" aria-label="Collapse menu">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <button data-sidebar-close class="lg:hidden text-white/60 hover:text-white focus:outline-none" aria-label="Close menu">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <button type="button" data-sidebar-unfold aria-controls="app-sidebar" aria-expanded="false" class="sidebar-fold-open mx-auto inline-flex items-center justify-center text-white/60 hover:text-white focus:outline-none p-2 rounded" aria-label="Expand menu">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>

        {{-- data-sidebar-nav: the element that actually scrolls (Part E) --}}
        <nav data-sidebar-nav class="sidebar-full flex-1 overflow-y-auto px-3 py-4 space-y-6 text-sm">

            {{-- Overview --}}
            <x-nav-section label="Overview" :routes="['dashboard']">
                <x-nav-link route="dashboard" label="Dashboard">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </x-nav-link>
            </x-nav-section>

            {{-- Operations. Till Close is deliberately absent: ending a shift is
                 the last thing you do in a day, so it is the "End shift" button
                 on the dashboard rather than a line in the sidebar. --}}
            <x-nav-section label="Operations" :routes="['dispense.*', 'purchases.*', 'adjustments.*', 'returns.*']">
                <x-nav-link route="dispense.index" label="Dispense">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </x-nav-link>
                <x-nav-link route="purchases.index" label="Purchases">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </x-nav-link>
                <x-nav-link route="adjustments.index" label="Adjustments">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </x-nav-link>
                <x-nav-link route="returns.index" label="Returns">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                </x-nav-link>
            </x-nav-section>

            {{-- Catalog --}}
            <x-nav-section label="Catalog" :badge="$owedBadgeCount ?? 0" :routes="['drugs.*', 'products.*', 'product-categories.*', 'categories.*', 'suppliers.*', 'supplier-owed.*', 'supplier-payments.*', 'expenses.*', 'expense-categories.*', 'customers.*', 'batch-prices.*']">
                <x-nav-link route="drugs.index" label="Drugs">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 4H9L8 4zm0 4v10a2 2 0 002 2h4a2 2 0 002-2V8H8z" />
                </x-nav-link>
                <x-nav-link route="products.index" label="Products">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0v10l-8 4-8-4V7m16 0l-8 4-8-4m8 4v10" />
                </x-nav-link>
                <x-nav-link route="product-categories.index" label="Product Categories">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16" />
                </x-nav-link>
                <x-nav-link route="categories.index" label="Categories">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-5.586 5.586a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 10V5a2 2 0 012-2z" />
                </x-nav-link>
                <x-nav-link route="suppliers.index" label="Suppliers">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13h13V6H3v7zm0 0l-1 5h16l-2-5m-4 5a2 2 0 11-4 0m8 0a2 2 0 11-4 0M16 9h3l2 3v4h-2" />
                </x-nav-link>
                @if(auth()->user()->role === 'owner_admin')
                {{-- PART B — plain language: "Owed to suppliers", never accounting terms --}}
                @php $owedActive = request()->routeIs('supplier-owed.*') || request()->routeIs('supplier-payments.*'); @endphp
                <a href="{{ route('supplier-owed.index') }}" @if($owedActive) data-nav-active @endif
                   class="flex items-center gap-3 px-3 py-2 rounded-lg mb-0.5 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand-primary)]
                   {{ $owedActive
                        ? 'bg-[var(--brand-primary)]/15 text-white border-l-2 border-[var(--brand-primary)]'
                        : 'text-[#B9C2D0] hover:bg-white/5 hover:text-white border-l-2 border-transparent' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 flex-shrink-0 {{ $owedActive ? 'text-[var(--brand-primary)]' : 'text-[#64748b]' }}"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m0-2c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-sm {{ $owedActive ? 'font-medium' : '' }} flex-1">Owed to suppliers</span>
                    @if(($owedBadgeCount ?? 0) > 0)
                        <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-red-500 text-white text-xs font-bold">{{ $owedBadgeCount }}</span>
                    @endif
                </a>
                {{-- PART C — Expenses (owner/admin only, same guard as Owed to suppliers) --}}
                <x-nav-link route="expenses.index" label="Expenses">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h11m-11 5h11M4.5 5.5h15a1 1 0 011 1v11a1 1 0 01-1 1h-15a1 1 0 01-1-1v-11a1 1 0 011-1z" />
                </x-nav-link>
                <x-nav-link route="expense-categories.index" label="Expense Categories">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-5.586 5.586a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 10V5a2 2 0 012-2z" />
                </x-nav-link>
                @endif
                <x-nav-link route="customers.index" label="Customers">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m5-4a4 4 0 100-8 4 4 0 000 8zm6 4a4 4 0 00-8 0" />
                </x-nav-link>
                {{-- Batch Prices is Owner/Admin only. The link is hidden rather
                     than left to 403, so a clerk or branch manager is never shown
                     a door they cannot open. --}}
                @if(auth()->user()->role === 'owner_admin')
                <x-nav-link route="batch-prices.index" label="Batch Prices">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h11m0 0l-3-3m3 3l-3 3M7 17H4m3 0l-3-3m3 3l3-3" />
                </x-nav-link>
                @endif
            </x-nav-section>

            {{-- Finance — the accounting screens use accounting terms, so the
                 group is only shown to the roles that can open them (clerks
                 cannot), keeping their sidebar in plain language. --}}
            @if(in_array(auth()->user()->role, ['owner_admin', 'branch_manager'], true))
            <x-nav-section label="Finance" :routes="['accounts.*', 'journal-entries.*', 'reports.*', 'transfers.*', 'branches.*']">
                <x-nav-link route="accounts.index" label="Chart of Accounts">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m0-2c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </x-nav-link>
                <x-nav-link route="transfers.index" label="Transfers">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4" />
                </x-nav-link>
                <x-nav-link route="branches.index" label="Branches">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 9h1m4 0h1M9 13h1m4 0h1M9 17h1m4 0h1" />
                </x-nav-link>
                {{-- Plain language in the navigation; the page itself keeps
                     its accounting wording. --}}
                <x-nav-link route="journal-entries.index" label="Accounting Records">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </x-nav-link>
                {{-- Opening Balances is gone: the drawer count is asked at the
                     start of every shift instead. --}}
                <x-nav-link route="reports.inventory" label="Reports">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l7 7-7 7zM3 5v14" />
                </x-nav-link>
                <x-nav-link route="reports.export.form" label="Export Excel">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" />
                </x-nav-link>
            </x-nav-section>
            @endif

            {{-- System --}}
            <x-nav-section label="System" :routes="['alerts.*', 'users.*', 'activity-logs.*', 'settings.*', 'backups.*']">
                <x-nav-link route="alerts.index" label="Alerts" :badge="$billsDueCount ?? 0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </x-nav-link>
                <x-nav-link route="users.index" label="Users">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m5-4a4 4 0 100-8 4 4 0 000 8zm6 4a4 4 0 00-8 0" />
                </x-nav-link>
                <x-nav-link route="activity-logs.index" label="Activity Log">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </x-nav-link>
                {{-- PART D — Business settings (owner/admin only) --}}
                @if(auth()->user()->role === 'owner_admin')
                <x-nav-link route="settings.branding" label="Business Settings">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </x-nav-link>
                <x-nav-link route="settings.integration" label="P-Check">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </x-nav-link>
                <x-nav-link route="backups.index" label="Backups" :alert="$backupStale ?? false">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                </x-nav-link>
                @endif
            </x-nav-section>
        </nav>

        <div class="sidebar-full border-t border-white/10 px-4 py-4">
            <a href="{{ route('profile.show') }}" class="flex items-center gap-2 mb-3 hover:bg-white/5 -mx-2 px-2 py-1.5 rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand-primary)]">
                <span class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center text-white text-xs font-semibold flex-shrink-0">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                </span>
                <span class="text-white/80 text-sm truncate">{{ auth()->user()->name }}</span>
            </a>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="w-full text-left text-sm text-white/60 hover:text-white flex items-center gap-2 px-1 py-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand-primary)] rounded">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Logout
                </button>
            </form>
        </div>
    </aside>

    {{-- PART E — keep the sidebar where the user left it across page loads:
         which section is open, and how far it is scrolled. Inline (not app.js)
         so both are applied before the browser paints, with no flash of the
         wrong sections. --}}
    <script>
    (function () {
        var SCROLL_KEY = 'pmas.sidebarScroll';
        var GROUP_KEY = 'pmas.sidebarOpenSection';
        var nav = document.querySelector('[data-sidebar-nav]');
        if (!nav) return;

        function read(key) { try { return sessionStorage.getItem(key); } catch (e) { return null; } }
        function write(key, value) { try { sessionStorage.setItem(key, value); } catch (e) {} }

        var sections = [].slice.call(nav.querySelectorAll('[data-nav-section]'));

        // One section open at a time; '' collapses them all.
        function setOpen(name) {
            sections.forEach(function (section) {
                var open = section.getAttribute('data-nav-section') === name;
                var toggle = section.querySelector('[data-nav-section-toggle]');
                var items = section.querySelector('[data-nav-section-items]');
                if (toggle) toggle.setAttribute('aria-expanded', String(open));
                if (items) items.hidden = !open;
            });
        }

        var active = nav.querySelector('[data-nav-active]');
        var activeSection = active && active.closest ? active.closest('[data-nav-section]') : null;
        // Wherever you are, that section has to be open — even from a bookmark.
        var openName = activeSection
            ? activeSection.getAttribute('data-nav-section')
            : (read(GROUP_KEY) || '');
        if (sections.length) setOpen(openName);

        var restored = parseInt(read(SCROLL_KEY), 10);
        if (isFinite(restored) && restored > 0) nav.scrollTop = restored;

        // Reveal the current page's item only if the restored position hides it.
        if (active) {
            var box = nav.getBoundingClientRect();
            var item = active.getBoundingClientRect();
            if (item.top < box.top) nav.scrollTop -= (box.top - item.top);
            else if (item.bottom > box.bottom) nav.scrollTop += (item.bottom - box.bottom);
        }

        function save() { write(SCROLL_KEY, String(nav.scrollTop)); }

        var queued = false;
        nav.addEventListener('scroll', function () {
            if (queued) return;
            queued = true;
            (window.requestAnimationFrame || function (fn) { setTimeout(fn, 100); })(function () {
                queued = false;
                save();
            });
        }, { passive: true });

        nav.addEventListener('click', function (event) {
            if (!event.target.closest) return;

            var toggle = event.target.closest('[data-nav-section-toggle]');
            if (toggle) {
                var name = toggle.closest('[data-nav-section]').getAttribute('data-nav-section');
                var next = toggle.getAttribute('aria-expanded') === 'true' ? '' : name;
                setOpen(next);
                write(GROUP_KEY, next);
                save();
                return;
            }

            // Links and form submits (e.g. saving a purchase) navigate away — capture
            // catches both, on the final scroll position.
            if (event.target.closest('a')) save();
        }, true);
        nav.addEventListener('submit', save, true);
        window.addEventListener('pagehide', save);
    })();
    </script>

    {{-- Desktop fold toggle. The saved choice is applied in <head>; this only
         flips it and remembers the new value. --}}
    <script>
    (function () {
        var root = document.documentElement;
        var fold = document.querySelector('[data-sidebar-fold]');
        var unfold = document.querySelector('[data-sidebar-unfold]');
        if (!fold || !unfold) return;

        function setCollapsed(collapsed) {
            root.classList.toggle('sidebar-collapsed', collapsed);
            try { localStorage.setItem('pmas.sidebarCollapsed', collapsed ? '1' : '0'); } catch (e) {}
            fold.setAttribute('aria-expanded', String(!collapsed));
            unfold.setAttribute('aria-expanded', String(!collapsed));
        }

        fold.addEventListener('click', function () { setCollapsed(true); });
        unfold.addEventListener('click', function () { setCollapsed(false); });
    })();
    </script>
    @endauth

    {{-- Main content --}}
    <div class="{{ auth()->check() ? 'lg:pl-64 app-shell-main' : '' }} flex min-h-screen flex-col">
        <main class="px-4 py-6 sm:px-6 lg:px-8 max-w-[1440px] mx-auto w-full flex-1">
            @if(session('success'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg mb-4 text-sm">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg mb-4 text-sm">
                    {{ session('error') }}
                </div>
            @endif
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-4 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="px-4 py-5 text-center text-xs text-[#5b6a7d]">
            Powered by
            <a href="{{ config('pmas.vendor_url') }}" target="_blank" rel="noopener"
               class="font-semibold text-[var(--brand-primary)] hover:underline">{{ config('pmas.vendor_name') }}</a>
        </footer>
    </div>

    @include('partials.no-browser-autofill')
</body>
</html>
