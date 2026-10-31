@php
    $slug = Str::slug($label);
    // The section holding the page you are on is the one that starts open.
    $isOpen = collect($routes)->contains(fn ($pattern) => request()->routeIs($pattern));
@endphp
<div data-nav-section="{{ $slug }}">
    <button type="button" data-nav-section-toggle aria-controls="nav-{{ $slug }}-items"
            aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
            class="flex w-full items-center gap-2 px-3 py-2 -my-1.5 rounded-lg text-left text-xs font-semibold uppercase tracking-wider text-white/30 transition-colors hover:bg-white/5 hover:text-white/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand-primary)]">
        <svg xmlns="http://www.w3.org/2000/svg" class="nav-chevron w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
        <span class="flex-1">{{ $label }}</span>
        @if(($badge ?? 0) > 0)
            {{-- Stays visible while the section is closed, so reminders are not hidden. --}}
            <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-red-500 text-white text-[10px] font-bold">{{ $badge }}</span>
        @endif
    </button>
    <div id="nav-{{ $slug }}-items" data-nav-section-items class="mt-0.5" @if(! $isOpen) hidden @endif>
        {{ $slot }}
    </div>
</div>
