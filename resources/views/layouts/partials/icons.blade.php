@php($name = $name ?? 'spark')
<span class="icon icon--{{ $name }}" aria-hidden="true">
    @switch($name)
        @case('menu')
            <svg viewBox="0 0 24 24" fill="none"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            @break
        @case('close')
            <svg viewBox="0 0 24 24" fill="none"><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            @break
        @case('search')
            <svg viewBox="0 0 24 24" fill="none"><circle cx="10.8" cy="10.8" r="6.8" stroke="currentColor" stroke-width="1.8"/><path d="m16 16 4.2 4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            @break
        @case('bell')
            <svg viewBox="0 0 24 24" fill="none"><path d="M6.5 17.5h11l-1.2-1.8v-4.4a4.3 4.3 0 0 0-8.6 0v4.4l-1.2 1.8Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M10 20h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            @break
        @case('bag')
            <svg viewBox="0 0 24 24" fill="none"><path d="M5.5 8.5h13l.8 11H4.7l.8-11Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 9V6.8a3 3 0 0 1 6 0V9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            @break
        @case('arrow')
            <svg viewBox="0 0 24 24" fill="none"><path d="M5 12h13M13 6l6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            @break
        @case('chevron')
            <svg viewBox="0 0 24 24" fill="none"><path d="m7 9 5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            @break
        @case('filter')
            <svg viewBox="0 0 24 24" fill="none"><path d="M4 7h16M7 12h10M10 17h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            @break
        @case('heart')
            <svg viewBox="0 0 24 24" fill="none"><path d="M20.2 8.8c0 4.2-8.2 9.4-8.2 9.4s-8.2-5.2-8.2-9.4A4.2 4.2 0 0 1 12 6.4a4.2 4.2 0 0 1 8.2 2.4Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
            @break
        @case('user')
            <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.2" stroke="currentColor" stroke-width="1.6"/><path d="M5.5 19.2c.8-3 3-4.6 6.5-4.6s5.7 1.6 6.5 4.6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            @break
        @case('home')
            <svg viewBox="0 0 24 24" fill="none"><path d="m4.5 10.5 7.5-6 7.5 6v8.4a1 1 0 0 1-1 1h-13a1 1 0 0 1-1-1v-8.4Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9.5 19.8v-5h5v5" stroke="currentColor" stroke-width="1.6"/></svg>
            @break
        @case('grid')
            <svg viewBox="0 0 24 24" fill="none"><rect x="4.5" y="4.5" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.5"/><rect x="14.5" y="4.5" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.5"/><rect x="4.5" y="14.5" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.5"/><rect x="14.5" y="14.5" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.5"/></svg>
            @break
        @case('tag')
            <svg viewBox="0 0 24 24" fill="none"><path d="m4.8 5.2 7.1-.5 7.4 7.4-6.7 6.7-7.4-7.4.5-7.1Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="9" cy="9" r="1" fill="currentColor"/></svg>
            @break
        @case('spark')
            <svg viewBox="0 0 24 24" fill="none"><path d="m12 3 1.3 5.7L19 10l-5.7 1.3L12 17l-1.3-5.7L5 10l5.7-1.3L12 3Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="m18.5 15 .6 2.4 2.4.6-2.4.6-.6 2.4-.6-2.4-2.4-.6 2.4-.6.6-2.4Z" fill="currentColor"/></svg>
            @break
        @default
            <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="7" stroke="currentColor" stroke-width="1.6"/></svg>
    @endswitch
</span>