@php
    $iconName = (string) ($name ?? '');
    $size = (int) ($size ?? 20);
    $class = trim((string) ($class ?? ''));
    $strokeWidth = $strokeWidth ?? 1.8;
@endphp

<svg
    @if($class !== '') class="{{ $class }}" @endif
    width="{{ $size }}"
    height="{{ $size }}"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="{{ $strokeWidth }}"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
>
    @switch($iconName)
        @case('chevron')
            <path d="m9 18 6-6-6-6"/>
            @break

        @case('search')
            <circle cx="11" cy="11" r="7"/>
            <path d="m20 20-3.5-3.5"/>
            @break

        @case('filter')
            <path d="M4 6h16"/>
            <path d="M7 12h10"/>
            <path d="M10 18h4"/>
            @break

        @case('arrow')
            <path d="M5 12h14"/>
            <path d="m13 6 6 6-6 6"/>
            @break

        @case('heart')
            <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/>
            @break

        @case('close')
            <path d="M6 6l12 12"/>
            <path d="M18 6 6 18"/>
            @break

        @case('menu')
            <path d="M4 7h16"/>
            <path d="M4 12h16"/>
            <path d="M4 17h16"/>
            @break

        @case('cart')
            <circle cx="9" cy="20" r="1"/>
            <circle cx="18" cy="20" r="1"/>
            <path d="M3 4h2l2.5 11h10l2-8H6"/>
            @break

        @default
            <circle cx="12" cy="12" r="8"/>
    @endswitch
</svg>
