@props([
    'name' => 'sparkles',
    'size' => 24,
    'stroke' => 1.8,
])

<svg
    {{ $attributes->merge([
        'viewBox' => '0 0 24 24',
        'fill' => 'none',
        'stroke' => 'currentColor',
        'stroke-width' => $stroke,
        'stroke-linecap' => 'round',
        'stroke-linejoin' => 'round',
        'aria-hidden' => 'true',
    ]) }}
    width="{{ $size }}"
    height="{{ $size }}"
>
    @switch($name)

        @case('burger')
            <path d="M4 10.5c.4-3.2 3.4-5.5 8-5.5s7.6 2.3 8 5.5H4Z"/>
            <path d="M4 14h16"/>
            <path d="M5 14c.5 1.1 1.1 2 2 2.7V19h10v-2.3c.9-.7 1.5-1.6 2-2.7"/>
            <path d="M7.5 10.5 9 12l1.8-1.5L12.5 12l1.7-1.5L16 12l1.4-1.5"/>
            @break

        @case('pizza')
            <path d="M5 4.8c5.6-1.7 10.4-.8 14 2.2L12 20 5 4.8Z"/>
            <path d="M5 4.8c4.7 1 9.2 1.8 14 2.2"/>
            <circle cx="11" cy="10" r="1"/>
            <circle cx="14.8" cy="13.2" r="1"/>
            @break

        @case('coffee')
            <path d="M5 8h11v5.5A4.5 4.5 0 0 1 11.5 18h-2A4.5 4.5 0 0 1 5 13.5V8Z"/>
            <path d="M16 10h1.5a2.5 2.5 0 0 1 0 5H16"/>
            <path d="M8 5c0-1 1-1.2 1-2"/>
            <path d="M12 5c0-1 1-1.2 1-2"/>
            <path d="M5 20h13"/>
            @break

        @case('cake')
            <path d="M5 10h14v9H5z"/>
            <path d="M5 14h14"/>
            <path d="M7 10c0-2 1.4-3 3-3s2.2 1 4 1 2.5-1 3-2"/>
            <path d="M12 7V4"/>
            <path d="M11 4c.4-1 1-1.5 1-2 .8.8 1 1.4 0 2"/>
            @break

        @case('croissant')
            <path d="M4.5 14.5c2.5-5.2 4.7-7.1 7.5-7.1s5 1.9 7.5 7.1"/>
            <path d="M4.5 14.5c1.2 3.2 3.1 4.5 5.4 3.4 1-.5 1.4-1.3 2.1-2.1.7.8 1.1 1.6 2.1 2.1 2.3 1.1 4.2-.2 5.4-3.4"/>
            <path d="M8 9.2c.5 2 1.2 4 2.2 5.8"/>
            <path d="M16 9.2c-.5 2-1.2 4-2.2 5.8"/>
            @break

        @case('donut')
            <circle cx="12" cy="12" r="7.5"/>
            <circle cx="12" cy="12" r="2.2"/>
            <path d="M6.5 9c1.6.2 2.2-.8 3.1-.5 1 .3 1.1 1.6 2.2 1.7 1.1.1 1.5-1.1 2.7-1 1 .1 1.6.8 2.9.4"/>
            @break

        @case('icecream')
            <path d="m8 11 4 10 4-10"/>
            <path d="M7 9a3 3 0 0 1 3-3 3 3 0 0 1 5.7 1.2A2.5 2.5 0 0 1 17 12H7a2 2 0 0 1 0-3Z"/>
            @break

        @case('drink')
            <path d="M7 4h10l-1.2 16H8.2L7 4Z"/>
            <path d="M8 8h8"/>
            <path d="m14 4 2-2"/>
            <path d="M10 12c1.2-.8 2.8-.8 4 0"/>
            @break

        @case('juice')
            <path d="M7 7h10l-1 13H8L7 7Z"/>
            <path d="M9 4h5"/>
            <path d="m14 7 2-4"/>
            <circle cx="11.5" cy="12" r="2.2"/>
            @break

        @case('fries')
            <path d="M7 8 6 20h12L17 8H7Z"/>
            <path d="M8 8 7 3"/>
            <path d="m11 8 .2-5"/>
            <path d="m14 8 .8-5"/>
            <path d="m17 8 1-4"/>
            @break

        @case('chicken')
            <path d="M8.5 16.5c-2.3-2.3-2.5-5.8-.5-7.8s5.5-1.8 7.8.5 2.5 5.8.5 7.8-5.5 1.8-7.8-.5Z"/>
            <path d="m16.5 16.5 2.2 2.2"/>
            <circle cx="20" cy="20" r="1.3"/>
            @break

        @case('salad')
            <path d="M5 11c.5 5 3 8 7 8s6.5-3 7-8H5Z"/>
            <path d="M7 11c.5-2 1.5-3 3-3 .4-2 1.3-3 2.5-3 1.5 0 2.2 1.3 2.5 3 1.4.1 2.3 1.1 2.5 3"/>
            <path d="M9 15h6"/>
            @break

        @case('sandwich')
            <path d="M5 9 12 4l7 5H5Z"/>
            <path d="M5 12h14"/>
            <path d="M6 12c1 2 2 2 3 0 1 2 2 2 3 0 1 2 2 2 3 0 1 2 2 2 3 0"/>
            <path d="M5 16h14v3H5z"/>
            @break

        @case('chocolate')
            <rect x="6" y="4" width="12" height="16" rx="2"/>
            <path d="M10 4v16M14 4v16M6 9h12M6 14h12"/>
            @break

        @case('gift')
            <path d="M4 9h16v11H4z"/>
            <path d="M3 6h18v4H3z"/>
            <path d="M12 6v14"/>
            <path d="M12 6c-2.2 0-4-.8-4-2 0-1 1-1.6 2-1 1.2.7 2 3 2 3Z"/>
            <path d="M12 6c2.2 0 4-.8 4-2 0-1-1-1.6-2-1-1.2.7-2 3-2 3Z"/>
            @break

        @case('dessert')
            <path d="M5 13h14l-2 6H7l-2-6Z"/>
            <path d="M8 13c0-3 1.8-5 4-5s4 2 4 5"/>
            <path d="M12 8c0-1.6 1-2.5 2-3"/>
            <circle cx="15" cy="4" r="1"/>
            @break

        @case('tea')
            <path d="M5 8h11v6a4 4 0 0 1-4 4H9a4 4 0 0 1-4-4V8Z"/>
            <path d="M16 10h2a2 2 0 0 1 0 4h-2"/>
            <path d="M9 4c0 1 1 1 1 2"/>
            <path d="M13 4c0 1 1 1 1 2"/>
            @break

        @case('breakfast')
            <circle cx="12" cy="12" r="8"/>
            <circle cx="12" cy="12" r="3"/>
            <path d="M4 12h2M18 12h2M12 4v2M12 18v2"/>
            @break

        @default
            <path d="m12 3 1.4 4.1L17.5 8.5l-4.1 1.4L12 14l-1.4-4.1-4.1-1.4 4.1-1.4L12 3Z"/>
            <path d="m18.2 14.2.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8.8-2.2Z"/>
    @endswitch
</svg>
