<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PMAS | Pharmacy operations</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .onboarding { background: #edf4f3; min-height: 100vh; overflow: hidden; }
        .shape { position: absolute; pointer-events: none; }
        .shape-one { width: 34rem; height: 34rem; border-radius: 48% 52% 62% 38%; background: #bce7de; left: -12rem; top: -10rem; transform: rotate(24deg); }
        .shape-two { width: 24rem; height: 24rem; border-radius: 50%; background: #f6d49a; right: -7rem; top: 10%; opacity: .8; }
        .shape-three { width: 18rem; height: 18rem; background: #b6c9e8; left: 38%; bottom: -9rem; transform: rotate(45deg); }
        .glass { background: rgba(255,255,255,.67); border: 1px solid rgba(255,255,255,.78); box-shadow: 0 24px 70px rgba(26,65,70,.14); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px); }
        .rise { animation: rise .7s ease both; }
        .rise-delay { animation: rise .7s .12s ease both; }
        @keyframes rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
        @media (prefers-reduced-motion: reduce) { .rise, .rise-delay { animation: none; } }
    </style>
</head>
<body class="onboarding text-[#182230]">
    <div class="relative isolate mx-auto flex min-h-screen max-w-7xl items-center px-5 py-8 sm:px-8 lg:px-12">
        <div class="shape shape-one"></div><div class="shape shape-two"></div><div class="shape shape-three"></div>
        <main class="relative grid w-full items-center gap-10 lg:grid-cols-[.8fr_1.2fr] lg:gap-16">
            <section class="rise max-w-xl">
                <div class="mb-8 flex items-center gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#10253b] text-sm font-bold text-[#72d4c5]">Rx</span><span class="text-lg font-bold tracking-tight">PMAS</span><span class="rounded-full bg-white/70 px-3 py-1 text-[10px] font-bold uppercase tracking-[.16em] text-[#6b7a93]">Offline</span></div>
                <p class="mb-3 text-xs font-bold uppercase tracking-[.2em] text-[#0f9f8f]">Pharmacy Management & Administration</p>
                <h1 class="max-w-lg text-4xl font-bold leading-[1.04] tracking-tight sm:text-6xl">Your pharmacy, clearly in hand.</h1>
                <p class="mt-5 max-w-md text-base leading-7 text-[#52657a]">Keep medicines, retail products, stock, sales, and branches moving together from one calm workspace.</p>
                <a href="{{ route('login') }}" class="mt-8 inline-flex items-center gap-3 rounded-xl bg-[#10253b] px-5 py-3.5 text-sm font-semibold text-white shadow-lg shadow-[#10253b]/15 hover:-translate-y-0.5 hover:bg-[#183753]">Enter PMAS <span class="text-[#72d4c5]">→</span></a>
                <p class="mt-5 text-xs text-[#6b7a93]">Built for local pharmacy teams. No cloud connection required.</p>
            </section>
            <section class="rise-delay relative min-h-[28rem] sm:min-h-[34rem]">
                <div class="absolute right-2 top-3 h-10 w-10 rounded-xl border border-white/70 bg-white/35"></div>
                <div class="glass absolute left-0 top-10 w-[88%] rounded-3xl p-5 sm:p-7">
                    <div class="flex items-center justify-between border-b border-[#dce8e7] pb-5"><div><p class="text-[10px] font-bold uppercase tracking-[.18em] text-[#6b7a93]">Today</p><h2 class="mt-1 text-xl font-bold">Good morning, team.</h2></div><span class="h-9 w-9 rounded-full bg-[#0f9f8f] ring-4 ring-[#d9f0eb]"></span></div>
                    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4"><div class="rounded-xl bg-[#e7f7f4] p-3"><p class="text-[10px] text-[#08796f]">DRUGS</p><p class="mt-2 text-2xl font-bold">128</p></div><div class="rounded-xl bg-[#fff2dc] p-3"><p class="text-[10px] text-[#8a5700]">PRODUCTS</p><p class="mt-2 text-2xl font-bold">64</p></div><div class="rounded-xl bg-[#eef1f5] p-3"><p class="text-[10px] text-[#52657a]">SALES</p><p class="mt-2 text-xl font-bold">ETB 8.4k</p></div><div class="rounded-xl bg-[#fcebea] p-3"><p class="text-[10px] text-[#a33b37]">ALERTS</p><p class="mt-2 text-2xl font-bold">03</p></div></div>
                    <div class="mt-6 rounded-xl bg-white/75 p-4"><div class="flex items-center justify-between"><p class="text-sm font-semibold">Stock movement</p><p class="text-xs text-[#0f9f8f]">This week</p></div><div class="mt-5 flex h-20 items-end gap-2"><span class="h-8 flex-1 rounded-t bg-[#bce7de]"></span><span class="h-12 flex-1 rounded-t bg-[#7acdbd]"></span><span class="h-10 flex-1 rounded-t bg-[#bce7de]"></span><span class="h-16 flex-1 rounded-t bg-[#0f9f8f]"></span><span class="h-14 flex-1 rounded-t bg-[#7acdbd]"></span><span class="h-20 flex-1 rounded-t bg-[#0f9f8f]"></span><span class="h-12 flex-1 rounded-t bg-[#bce7de]"></span></div></div>
                </div>
                <div class="glass absolute bottom-4 right-0 w-56 rounded-2xl p-4 sm:w-64"><div class="flex items-center gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#fff2dc] text-[#b36b00]">!</span><div><p class="text-xs font-bold">Expiry watch</p><p class="mt-0.5 text-[11px] text-[#6b7a93]">3 items need attention</p></div></div><div class="mt-4 h-1.5 rounded-full bg-[#f3e6cf]"><div class="h-full w-2/3 rounded-full bg-[#e8a43a]"></div></div></div>
            </section>
        </main>
    </div>
</body>
</html>
