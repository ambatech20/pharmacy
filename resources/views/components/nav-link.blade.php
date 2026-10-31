@php
    $isActive = request()->routeIs($route) || request()->routeIs(str($route)->before('.').'.*');
@endphp
<a href="{{ route($route) }}" @if($isActive) data-nav-active @endif
   class="flex items-center gap-3 px-3 py-2 rounded-lg mb-0.5 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand-primary)]
   {{ $isActive
        ? 'bg-[var(--brand-primary)]/15 text-white border-l-2 border-[var(--brand-primary)]'
        : 'text-[#B9C2D0] hover:bg-white/5 hover:text-white border-l-2 border-transparent' }}">
    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 flex-shrink-0 {{ $isActive ? 'text-[var(--brand-primary)]' : 'text-[#64748b]' }}"
         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        {{ $slot }}
    </svg>
    <span class="text-sm {{ $isActive ? 'font-medium' : '' }}">{{ $label }}</span>
    @if($alert ?? false)
        {{-- A mark rather than a number: "something here needs you", not a
             tally that would be read as a count of backups. --}}
        <span class="ml-auto inline-flex items-center justify-center w-5 h-5 rounded-full bg-red-500 text-white text-[11px] font-bold"
              title="Needs attention" aria-label="Needs attention">!</span>
    @elseif(($badge ?? 0) > 0)
        {{-- A count on the link itself, so a reminder is visible without
             having to open the page. --}}
        <span class="ml-auto inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-red-500 text-white text-[10px] font-bold">{{ $badge }}</span>
    @endif
</a>
