@php
    // Same shell as the sign-in screen: this is a door on the way in, not a
    // page inside the app, so it should not look like the app.
    $brandName = \App\Models\Setting::value('business_name', config('pmas.default_business_name'));
    $brandPrimary = \App\Models\Setting::value('brand_primary_color', config('pmas.default_brand_primary_color'));
    $brandAccent = \App\Models\Setting::value('brand_accent_color', config('pmas.default_brand_accent_color'));
    $brandLogo = \App\Models\Setting::logoPath();
@endphp
<!doctype html>
<html lang="en" style="@include('partials.brand-vars')">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $brandPrimary }}">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="/favicon.ico" sizes="any">
    @if($brandLogo)<link rel="apple-touch-icon" href="{{ Storage::url($brandLogo) }}">@endif
    <title>Start your shift | {{ $brandName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .login-shell { background: #edf4f3; min-height: 100vh; overflow: hidden; }
        .login-shape { position: absolute; pointer-events: none; }
        .login-shape-a { width: 30rem; height: 30rem; left: -12rem; top: -10rem; border-radius: 48% 52% 62% 38%; background: #bce7de; transform: rotate(24deg); }
        .login-shape-b { width: 21rem; height: 21rem; right: -7rem; bottom: -5rem; border-radius: 50%; background: #f6d49a; opacity: .8; }
        .login-glass { background: rgba(255,255,255,.72); border: 1px solid rgba(255,255,255,.82); box-shadow: 0 24px 70px rgba(26,65,70,.14); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px); }
        .login-fade { animation: login-rise .65s ease both; }
        @keyframes login-rise { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        @media (prefers-reduced-motion: reduce) { .login-fade { animation: none; } }
    </style>
</head>
<body class="login-shell text-[#182230]">
    <div id="offline-banner" class="hidden fixed top-0 inset-x-0 z-[9999] bg-red-600 text-white text-center text-sm py-2">You are offline. Sales and other actions cannot be saved until you reconnect.</div>
    <script>(function(){var b=document.getElementById('offline-banner');if(!b)return;function u(){b.classList.toggle('hidden',navigator.onLine);}u();window.addEventListener('online',u);window.addEventListener('offline',u);})();</script>

    <div class="relative isolate flex min-h-screen flex-col items-center justify-center px-5 py-8 sm:px-8">
        <div class="login-shape login-shape-a"></div><div class="login-shape login-shape-b"></div>

        <main class="relative w-full max-w-md overflow-hidden rounded-3xl login-glass">
            <section class="login-fade p-7 sm:p-9">
                <div class="mb-7 flex items-center gap-3">
                    @if($brandLogo)
                        <img src="{{ Storage::url($brandLogo) }}" alt="{{ $brandName }} logo" class="h-10 w-10 rounded-xl bg-white object-contain p-1">
                    @else
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#10253b] text-sm font-bold text-[#72d4c5]">{{ mb_strtoupper(mb_substr($brandName, 0, 1)) }}</span>
                    @endif
                    <span class="flex flex-col leading-tight">
                        <span class="text-lg font-bold tracking-tight">{{ $brandName }}</span>
                        <span class="text-[11px] font-medium uppercase tracking-[.18em] text-[#5b6a7d]">by {{ config('pmas.vendor_name') }}</span>
                    </span>
                </div>

                <p class="text-xs font-bold uppercase tracking-[.2em] text-[var(--brand-primary)]">Good day, {{ auth()->user()->name }}</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-[#182230]">Count the drawer</h1>
                <p class="mt-2 text-sm leading-6 text-[#64748b]">
                    Before you start, how much cash is in the till right now? This is the
                    starting point your end-of-shift count is measured against.
                </p>

                @if($errors->any())
                    <div role="alert" class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
                @endif

                @if($lastClosed)
                    <p class="mt-5 rounded-xl border border-[#edf1f3] bg-white/60 px-4 py-3 text-xs leading-5 text-[#5b6a7d]">
                        Your last shift ended {{ $lastClosed->closed_at->diffForHumans() }}
                        with {{ number_format((float) $lastClosed->opening_cash, 2) }} ETB counted at the start.
                    </p>
                @endif

                @if($current)
                    {{-- Already counted (second tab, or a back button). Show it. --}}
                    <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">Shift already open</p>
                        <p class="mt-1 text-2xl font-bold text-emerald-900">ETB {{ number_format((float) $current->opening_cash, 2) }}</p>
                        <p class="mt-1 text-xs text-emerald-700">Counted {{ $current->opened_at->format('g:i A') }}.</p>
                    </div>
                    <a href="{{ $intendedUrl }}" class="mt-6 flex w-full items-center justify-center gap-3 rounded-xl bg-[#10253b] px-5 py-3.5 text-sm font-semibold text-white shadow-lg shadow-[#10253b]/15 hover:bg-[#183753]">
                        Carry on <span class="text-[#72d4c5]">&rarr;</span>
                    </a>
                @else
                    <form action="{{ route('shift.store') }}" method="POST" class="mt-6 space-y-5">
                        @csrf
                        <input type="hidden" name="intended_url" value="{{ $intendedUrl }}">
                        <div>
                            <label for="opening_cash" class="mb-2 block text-sm font-semibold text-[#182230]">Cash in the drawer (ETB)</label>
                            <input id="opening_cash" type="number" step="0.01" min="0" inputmode="decimal"
                                   name="opening_cash" value="{{ old('opening_cash') }}"
                                   placeholder="0.00" required autofocus
                                   class="w-full rounded-xl border border-[#d8e1e6] px-4 py-3.5 text-lg font-semibold shadow-sm focus:border-[var(--brand-primary)] focus:ring-4 focus:ring-[var(--brand-primary)]/10">
                            <p class="mt-2 text-xs leading-5 text-[#5b6a7d]">
                                Count the notes and coins only. Leave it at 0 if the drawer is empty.
                            </p>
                        </div>
                        <button type="submit" class="flex w-full items-center justify-center gap-3 rounded-xl bg-[#10253b] px-5 py-3.5 text-sm font-semibold text-white shadow-lg shadow-[#10253b]/15 hover:-translate-y-0.5 hover:bg-[#183753]">
                            Start shift <span class="text-[#72d4c5]">&rarr;</span>
                        </button>
                    </form>
                @endif

                <p class="mt-7 border-t border-[#edf1f3] pt-5 text-center text-xs leading-5 text-[#5b6a7d]">
                    This is not a payment and does not change your accounts. It is only the number you started from.
                </p>
            </section>
        </main>

        <form action="{{ route('logout') }}" method="POST" class="relative mt-6">
            @csrf
            <button type="submit" class="text-xs font-medium text-[#5b6a7d] underline hover:text-[#182230]">Sign out instead</button>
        </form>
    </div>
</body>
</html>
