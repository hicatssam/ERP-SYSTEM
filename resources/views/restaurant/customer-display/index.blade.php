<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    @if(!empty($branding['faviconUrl']))
        <link
            rel="icon"
            href="{{ $branding['faviconUrl'] }}"
        >
        <link
            rel="shortcut icon"
            href="{{ $branding['faviconUrl'] }}"
        >
    @endif

    <title>
        شاشة الطلبات — {{ $branding['name'] }}
    </title>

    @php
        $backgroundPath =
            trim(
                (string)
                ($theme['backgroundImage'] ?? '')
            );

        $backgroundUrl =
            $backgroundPath !== ''
                ? asset($backgroundPath)
                : null;

        $hexToRgb = static function (
            string $hex,
            string $fallback = '17,17,17'
        ): string {
            $hex = ltrim(trim($hex), '#');

            if (
                ! preg_match(
                    '/^[0-9a-fA-F]{6}$/',
                    $hex
                )
            ) {
                return $fallback;
            }

            return implode(
                ',',
                [
                    hexdec(substr($hex, 0, 2)),
                    hexdec(substr($hex, 2, 2)),
                    hexdec(substr($hex, 4, 2)),
                ]
            );
        };

        $panelRgb =
            $hexToRgb(
                $theme['panelBg'] ?? '#111111'
            );

        $headerRgb =
            $hexToRgb(
                $theme['headerBg'] ?? '#090909',
                '9,9,9'
            );

        $panelAlpha =
            max(
                .35,
                min(
                    1,
                    (
                        (int)
                        ($theme['panelOpacity'] ?? 92)
                    ) / 100
                )
            );

        $overlayAlpha =
            max(
                0,
                min(
                    .95,
                    (
                        (int)
                        ($theme['overlayOpacity'] ?? 72)
                    ) / 100
                )
            );
    @endphp

    <style>
        :root {
            --cod-bg:
                {{ $theme['backgroundColor'] ?? '#090909' }};

            --cod-header:
                {{ $theme['headerBg'] ?? '#090909' }};

            --cod-panel:
                {{ $theme['panelBg'] ?? '#111111' }};

            --cod-card:
                {{ $theme['cardBg'] ?? '#181818' }};

            --cod-text:
                {{ $theme['textColor'] ?? '#FFFFFF' }};

            --cod-muted:
                {{ $theme['mutedColor'] ?? '#A3A3A3' }};

            --cod-preparing:
                {{ $theme['preparingColor'] ?? '#F0B429' }};

            --cod-ready:
                {{ $theme['readyColor'] ?? '#24C36B' }};

            --cod-accent:
                {{ $theme['accentColor'] ?? '#D7A51D' }};

            --cod-border:
                {{ $theme['borderColor'] ?? '#2A2A2A' }};

            --cod-radius:
                {{ (int) ($theme['radius'] ?? 22) }}px;

            --cod-blur:
                {{ (int) ($theme['glassBlur'] ?? 8) }}px;

            --cod-logo:
                {{ (int) ($theme['logoSize'] ?? 54) }}px;

            --cod-order-size:
                {{ (int) ($theme['orderNumberSize'] ?? 70) }}px;

            --cod-panel-rgb:
                {{ $panelRgb }};

            --cod-header-rgb:
                {{ $headerRgb }};

            --cod-panel-alpha:
                {{ number_format($panelAlpha, 2, '.', '') }};
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
            background: var(--cod-bg);
            color: var(--cod-text);
            font-family:
                "Cairo",
                "Segoe UI",
                Tahoma,
                Arial,
                sans-serif;
        }

        body {
            min-height: 100vh;
            overflow: hidden;
        }

        button,
        select {
            font: inherit;
        }

        .cod-shell {
            position: relative;
            isolation: isolate;
            min-height: 100vh;
            display: grid;
            grid-template-rows: auto 1fr auto;
            background:
                var(--cod-bg);
        }

        .cod-shell::before {
            content: "";
            position: fixed;
            z-index: -3;
            inset: 0;

            @if($backgroundUrl)
            background-image:
                url(@json($backgroundUrl));
            background-position: center;
            background-repeat: no-repeat;
            background-size: cover;
            @else
            background:
                radial-gradient(
                    circle at 85% -10%,
                    color-mix(
                        in srgb,
                        var(--cod-accent) 14%,
                        transparent
                    ),
                    transparent 37%
                ),
                var(--cod-bg);
            @endif
        }

        .cod-shell::after {
            content: "";
            position: fixed;
            z-index: -2;
            inset: 0;
            background:
                {{
                    $theme['overlayColor']
                    ?? '#000000'
                }};
            opacity:
                {{
                    number_format(
                        $overlayAlpha,
                        2,
                        '.',
                        ''
                    )
                }};
            pointer-events: none;
        }

        .cod-topbar {
            min-height: 88px;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            border-bottom:
                1px solid var(--cod-border);
            background:
                rgba(
                    var(--cod-header-rgb),
                    .94
                );
            backdrop-filter:
                blur(var(--cod-blur));
            -webkit-backdrop-filter:
                blur(var(--cod-blur));
        }

        .cod-brand,
        .cod-actions,
        .cod-clock-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .cod-logo {
            width: var(--cod-logo);
            height: var(--cod-logo);
            object-fit: contain;
            border-radius:
                max(
                    10px,
                    calc(var(--cod-radius) * .55)
                );
            background:
                rgba(255,255,255,.055);
            padding: 5px;
        }

        .cod-brand-text strong {
            display: block;
            font-size: 1.08rem;
            font-weight: 950;
        }

        .cod-brand-text span {
            display: block;
            margin-top: 2px;
            color: var(--cod-muted);
            font-size: .72rem;
        }

        .cod-location {
            color: var(--cod-accent) !important;
            font-weight: 900;
        }

        .cod-clock {
            direction: ltr;
            text-align: left;
        }

        .cod-clock strong {
            display: block;
            font-size: 1.15rem;
            letter-spacing: .03em;
        }

        .cod-clock span {
            display: block;
            margin-top: 2px;
            color: var(--cod-muted);
            font-size: .68rem;
        }

        .cod-btn,
        .cod-select {
            min-height: 40px;
            border:
                1px solid var(--cod-border);
            border-radius:
                max(
                    10px,
                    calc(var(--cod-radius) * .5)
                );
            background:
                rgba(
                    var(--cod-panel-rgb),
                    .82
                );
            color: var(--cod-text);
            padding: 0 12px;
            outline: none;
            backdrop-filter:
                blur(var(--cod-blur));
        }

        .cod-btn {
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }

        .cod-btn:hover {
            border-color:
                color-mix(
                    in srgb,
                    var(--cod-accent) 60%,
                    var(--cod-border)
                );
        }

        .cod-btn.is-on {
            color: var(--cod-ready);
            border-color:
                color-mix(
                    in srgb,
                    var(--cod-ready) 42%,
                    var(--cod-border)
                );
        }

        .cod-main {
            min-height: 0;
            padding: 18px;
            display: grid;
            grid-template-columns:
                minmax(0, 1.08fr)
                minmax(0, .92fr);
            gap: 18px;
        }

        .cod-column {
            min-height: 0;
            display: grid;
            grid-template-rows: auto 1fr;
            overflow: hidden;
            border:
                1px solid var(--cod-border);
            border-radius:
                var(--cod-radius);
            background:
                rgba(
                    var(--cod-panel-rgb),
                    var(--cod-panel-alpha)
                );
            backdrop-filter:
                blur(var(--cod-blur));
            -webkit-backdrop-filter:
                blur(var(--cod-blur));
            box-shadow:
                0 18px 55px rgba(0,0,0,.16);
        }

        .cod-column-head {
            min-height: 72px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            border-bottom:
                1px solid var(--cod-border);
        }

        .cod-title-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cod-dot {
            width: 13px;
            height: 13px;
            border-radius: 999px;
            background:
                var(--cod-preparing);
            box-shadow:
                0 0 0 6px
                color-mix(
                    in srgb,
                    var(--cod-preparing) 12%,
                    transparent
                );
        }

        .ready-column .cod-dot {
            background:
                var(--cod-ready);
            box-shadow:
                0 0 0 6px
                color-mix(
                    in srgb,
                    var(--cod-ready) 12%,
                    transparent
                );
        }

        .cod-column-head h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 1000;
        }

        .cod-count {
            min-width: 44px;
            min-height: 38px;
            padding: 5px 12px;
            display: grid;
            place-items: center;
            border-radius: 999px;
            font-size: 1rem;
            font-weight: 950;
            background:
                rgba(255,255,255,.075);
        }

        .ready-column .cod-count {
            color:
                var(--cod-ready);
            background:
                color-mix(
                    in srgb,
                    var(--cod-ready) 11%,
                    transparent
                );
        }

        .cod-list {
            min-height: 0;
            overflow: auto;
            padding: 15px;
            display: grid;
            grid-template-columns:
                repeat(
                    3,
                    minmax(0,1fr)
                );
            align-content: start;
            gap: 12px;
        }

        .ready-column .cod-list {
            grid-template-columns:
                repeat(
                    2,
                    minmax(0,1fr)
                );
        }

        .cod-card {
            position: relative;
            min-height: 142px;
            overflow: hidden;
            padding: 16px 18px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 7px;
            border:
                1px solid var(--cod-border);
            border-radius:
                max(
                    12px,
                    calc(var(--cod-radius) * .82)
                );
            background:
                color-mix(
                    in srgb,
                    var(--cod-card) 94%,
                    transparent
                );
            box-shadow:
                0 8px 24px rgba(0,0,0,.12);
        }

        .cod-card::before {
            content: "";
            position: absolute;
            inset-block: 0;
            inset-inline-start: 0;
            width: 5px;
            background:
                var(--cod-preparing);
        }

        .cod-card.is-ready {
            min-height: 166px;
            border-color:
                color-mix(
                    in srgb,
                    var(--cod-ready) 32%,
                    var(--cod-border)
                );
            background:
                linear-gradient(
                    135deg,
                    color-mix(
                        in srgb,
                        var(--cod-ready) 13%,
                        var(--cod-card)
                    ),
                    var(--cod-card) 63%
                );
        }

        .cod-card.is-ready::before {
            background:
                var(--cod-ready);
        }

        .cod-number {
            direction: ltr;
            line-height: 1;
            font-size:
                clamp(
                    2.4rem,
                    4vw,
                    var(--cod-order-size)
                );
            font-weight: 1000;
            letter-spacing: .025em;
        }

        .is-ready .cod-number {
            font-size:
                clamp(
                    2.8rem,
                    5vw,
                    calc(
                        var(--cod-order-size)
                        + 12px
                    )
                );
        }

        .cod-status {
            color:
                var(--cod-preparing);
            font-size: .86rem;
            font-weight: 900;
        }

        .is-ready .cod-status {
            color:
                var(--cod-ready);
            font-size: 1rem;
        }

        .cod-meta {
            margin-top: 4px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
            color: var(--cod-muted);
            font-size: .7rem;
        }

        .cod-pill {
            padding: 4px 8px;
            border-radius: 999px;
            background:
                rgba(255,255,255,.06);
        }

        .cod-empty,
        .cod-loading {
            grid-column: 1 / -1;
            min-height: 190px;
            display: grid;
            place-items: center;
            text-align: center;
            color: var(--cod-muted);
            border:
                1px dashed var(--cod-border);
            border-radius:
                max(
                    14px,
                    calc(var(--cod-radius) * .75)
                );
            background:
                rgba(0,0,0,.055);
        }

        .cod-footer {
            min-height: 42px;
            padding: 7px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border-top:
                1px solid var(--cod-border);
            color: var(--cod-muted);
            font-size: .66rem;
            background:
                rgba(
                    var(--cod-header-rgb),
                    .94
                );
            backdrop-filter:
                blur(var(--cod-blur));
        }

        .cod-connection {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .cod-connection i {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background:
                var(--cod-preparing);
        }

        .cod-connection.online i {
            background:
                var(--cod-ready);
        }

        .cod-connection.error {
            color: #fca5a5;
        }

        .cod-connection.error i {
            background: #ef4444;
        }

        .cod-card.just-ready {
            animation:
                readyPulse .8s ease-in-out
                0s 3;
        }

        @keyframes readyPulse {
            0%,
            100% {
                transform: scale(1);
                box-shadow:
                    0 8px 24px
                    rgba(0,0,0,.12);
            }

            50% {
                transform: scale(1.025);
                box-shadow:
                    0 0 0 4px
                    color-mix(
                        in srgb,
                        var(--cod-ready) 14%,
                        transparent
                    ),
                    0 16px 38px
                    color-mix(
                        in srgb,
                        var(--cod-ready) 14%,
                        transparent
                    );
            }
        }

        :fullscreen .cod-shell {
            min-height: 100vh;
        }

        @media (max-width: 1180px) {
            .cod-list {
                grid-template-columns:
                    repeat(
                        2,
                        minmax(0,1fr)
                    );
            }
        }

        @media (max-width: 900px) {
            body {
                overflow: auto;
            }

            .cod-main {
                grid-template-columns: 1fr;
            }

            .cod-column {
                min-height: 420px;
            }

            .cod-topbar {
                align-items: flex-start;
                flex-wrap: wrap;
            }
        }

        @media (max-width: 620px) {
            .cod-topbar {
                padding: 12px;
            }

            .cod-main {
                padding: 10px;
            }

            .cod-list,
            .ready-column .cod-list {
                grid-template-columns:
                    1fr 1fr;
                padding: 10px;
            }

            .cod-card {
                min-height: 112px;
                padding: 12px;
            }
        }
    </style>
</head>

<body>
<div
    class="cod-shell"
    id="customerDisplayShell"
>
    <header class="cod-topbar">
        <div class="cod-brand">
            @if(!empty($branding['logoUrl']))
                <img
                    src="{{ $branding['logoUrl'] }}"
                    alt="{{ $branding['name'] }}"
                    class="cod-logo"
                    onerror="
                        this.style.display='none'
                    "
                >
            @endif

            <div class="cod-brand-text">
                <strong>
                    {{ $branding['name'] }}
                </strong>

                @if(filled($branding['nameEn'] ?? ''))
                    <span>
                        {{ $branding['nameEn'] }}
                    </span>
                @endif

                <span class="cod-location">
                    {{ $location->name }}
                </span>
            </div>
        </div>

        <div class="cod-actions">
            @if($locations->count() > 1)
                <select
                    class="cod-select"
                    id="locationSelect"
                    aria-label="اختر الفرع"
                >
                    @foreach(
                        $locations as $candidate
                    )
                        <option
                            value="{{ $candidate->id }}"
                            @selected(
                                (int)
                                $candidate->id
                                ===
                                (int)
                                $location->id
                            )
                        >
                            {{ $candidate->name }}
                        </option>
                    @endforeach
                </select>
            @endif

            <button
                type="button"
                class="cod-btn"
                id="soundButton"
            >
                <span id="soundIcon">
                    🔇
                </span>

                <span id="soundLabel">
                    تفعيل الصوت
                </span>
            </button>

            <button
                type="button"
                class="cod-btn"
                id="fullscreenButton"
            >
                ⛶ ملء الشاشة
            </button>

            @if(
                $theme['showClock']
                ?? true
            )
                <div class="cod-clock-wrap">
                    <div class="cod-clock">
                        <strong id="clockTime">
                            --:--
                        </strong>

                        <span id="clockDate">
                            ----
                        </span>
                    </div>
                </div>
            @endif
        </div>
    </header>

    <main class="cod-main">
        <section class="cod-column">
            <div class="cod-column-head">
                <div class="cod-title-wrap">
                    <span class="cod-dot"></span>

                    <h2>
                        قيد التحضير
                    </h2>
                </div>

                <span
                    class="cod-count"
                    id="preparingCount"
                >
                    0
                </span>
            </div>

            <div
                class="cod-list"
                id="preparingList"
            >
                <div class="cod-loading">
                    جاري تحميل الطلبات...
                </div>
            </div>
        </section>

        <section
            class="cod-column ready-column"
        >
            <div class="cod-column-head">
                <div class="cod-title-wrap">
                    <span class="cod-dot"></span>

                    <h2>
                        جاهز للاستلام
                    </h2>
                </div>

                <span
                    class="cod-count"
                    id="readyCount"
                >
                    0
                </span>
            </div>

            <div
                class="cod-list"
                id="readyList"
            >
                <div class="cod-loading">
                    جاري تحميل الطلبات...
                </div>
            </div>
        </section>
    </main>

    <footer class="cod-footer">
        <div
            class="cod-connection"
            id="connectionState"
        >
            <i></i>

            <span>
                جاري الاتصال...
            </span>
        </div>

        <span>
            {{
                filled($branding['footerText'] ?? '')
                    ? $branding['footerText']
                    : 'يتم تحديث الشاشة تلقائيًا'
            }}
        </span>
    </footer>
</div>

<script>
(() => {
    'use strict';

    const preparingList =
        document.getElementById(
            'preparingList'
        );

    const readyList =
        document.getElementById(
            'readyList'
        );

    const preparingCount =
        document.getElementById(
            'preparingCount'
        );

    const readyCount =
        document.getElementById(
            'readyCount'
        );

    const connectionState =
        document.getElementById(
            'connectionState'
        );

    const locationSelect =
        document.getElementById(
            'locationSelect'
        );

    const soundButton =
        document.getElementById(
            'soundButton'
        );

    const soundIcon =
        document.getElementById(
            'soundIcon'
        );

    const soundLabel =
        document.getElementById(
            'soundLabel'
        );

    const fullscreenButton =
        document.getElementById(
            'fullscreenButton'
        );

    const shell =
        document.getElementById(
            'customerDisplayShell'
        );

    const clockTime =
        document.getElementById(
            'clockTime'
        );

    const clockDate =
        document.getElementById(
            'clockDate'
        );

    const showServiceType =
        @json(
            (bool)
            ($theme['showServiceType'] ?? true)
        );

    const showTable =
        @json(
            (bool)
            ($theme['showTable'] ?? true)
        );

    const pollMilliseconds =
        {{ (int) $pollSeconds * 1000 }};

    const selectedLocationId =
        {{ (int) $location->id }};

    const feedUrl =
        @json(
            route(
                'restaurant.customer-display.feed'
            )
        );

    let requestRunning = false;
    let firstSuccessfulLoad = false;
    let previousReadyIds =
        new Set();

    let audioContext = null;

    let soundEnabled =
        localStorage.getItem(
            'customer_display_sound'
        ) === 'on';

    const escapeHtml = (value) => {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    };

    const updateSoundButton = () => {
        soundButton?.classList.toggle(
            'is-on',
            soundEnabled
        );

        if (soundIcon) {
            soundIcon.textContent =
                soundEnabled
                    ? '🔊'
                    : '🔇';
        }

        if (soundLabel) {
            soundLabel.textContent =
                soundEnabled
                    ? 'الصوت مفعّل'
                    : 'تفعيل الصوت';
        }
    };

    const unlockAudio = async () => {
        if (!audioContext) {
            audioContext =
                new (
                    window.AudioContext
                    ||
                    window.webkitAudioContext
                )();
        }

        if (
            audioContext.state
            === 'suspended'
        ) {
            await audioContext.resume();
        }
    };

    const playReadySound = async () => {
        if (!soundEnabled) {
            return;
        }

        try {
            await unlockAudio();

            const now =
                audioContext.currentTime;

            [
                [740, 0],
                [980, .16],
            ].forEach(
                ([frequency, offset]) => {
                    const oscillator =
                        audioContext
                            .createOscillator();

                    const gain =
                        audioContext
                            .createGain();

                    oscillator.type = 'sine';

                    oscillator.frequency
                        .setValueAtTime(
                            frequency,
                            now + offset
                        );

                    gain.gain
                        .setValueAtTime(
                            .0001,
                            now + offset
                        );

                    gain.gain
                        .exponentialRampToValueAtTime(
                            .18,
                            now + offset + .02
                        );

                    gain.gain
                        .exponentialRampToValueAtTime(
                            .0001,
                            now + offset + .24
                        );

                    oscillator.connect(gain);

                    gain.connect(
                        audioContext.destination
                    );

                    oscillator.start(
                        now + offset
                    );

                    oscillator.stop(
                        now + offset + .26
                    );
                }
            );
        } catch (error) {
            console.debug(
                'Customer display sound failed.',
                error
            );
        }
    };

    const metaHtml = (order) => {
        const parts = [];

        if (
            showTable
            &&
            order.table?.name
        ) {
            parts.push(
                `<span class="cod-pill">طاولة ${escapeHtml(order.table.name)}</span>`
            );
        }

        if (
            showServiceType
            &&
            order.service_label
        ) {
            parts.push(
                `<span class="cod-pill">${escapeHtml(order.service_label)}</span>`
            );
        }

        return parts.join('');
    };

    const orderCard = (
        order,
        justReady = false
    ) => {
        const readyClass =
            order.display_status === 'ready'
                ? 'is-ready'
                : '';

        const pulseClass =
            justReady
                ? 'just-ready'
                : '';

        return `
            <article
                class="cod-card ${readyClass} ${pulseClass}"
                data-order-id="${Number(order.id)}"
            >
                <div class="cod-number">
                    ${escapeHtml(order.display_number)}
                </div>

                <div class="cod-status">
                    ${escapeHtml(order.status_label)}
                </div>

                <div class="cod-meta">
                    ${metaHtml(order)}
                </div>
            </article>
        `;
    };

    const emptyHtml = (message) => {
        return `
            <div class="cod-empty">
                ${escapeHtml(message)}
            </div>
        `;
    };

    const render = (data) => {
        const preparing =
            Array.isArray(data.preparing)
                ? data.preparing
                : [];

        const ready =
            Array.isArray(data.ready)
                ? data.ready
                : [];

        const currentReadyIds =
            new Set(
                ready.map(
                    (order) =>
                        String(order.id)
                )
            );

        const newlyReadyIds =
            new Set();

        if (firstSuccessfulLoad) {
            currentReadyIds.forEach(
                (id) => {
                    if (
                        !previousReadyIds
                            .has(id)
                    ) {
                        newlyReadyIds.add(id);
                    }
                }
            );
        }

        preparingList.innerHTML =
            preparing.length
                ? preparing
                    .map(
                        (order) =>
                            orderCard(order)
                    )
                    .join('')
                : emptyHtml(
                    'لا توجد طلبات قيد التحضير الآن'
                );

        readyList.innerHTML =
            ready.length
                ? ready
                    .map(
                        (order) =>
                            orderCard(
                                order,
                                newlyReadyIds.has(
                                    String(order.id)
                                )
                            )
                    )
                    .join('')
                : emptyHtml(
                    'لا توجد طلبات جاهزة الآن'
                );

        preparingCount.textContent =
            String(
                data.counts?.preparing
                ?? preparing.length
            );

        readyCount.textContent =
            String(
                data.counts?.ready
                ?? ready.length
            );

        if (
            firstSuccessfulLoad
            &&
            newlyReadyIds.size > 0
        ) {
            playReadySound();
        }

        previousReadyIds =
            currentReadyIds;

        firstSuccessfulLoad = true;
    };

    const loadFeed = async () => {
        if (requestRunning) {
            return;
        }

        requestRunning = true;

        try {
            const url =
                new URL(
                    feedUrl,
                    window.location.origin
                );

            url.searchParams.set(
                'location_id',
                String(
                    selectedLocationId
                )
            );

            const response =
                await fetch(
                    url.toString(),
                    {
                        headers: {
                            'Accept':
                                'application/json',

                            'X-Requested-With':
                                'XMLHttpRequest',
                        },

                        credentials:
                            'same-origin',

                        cache:
                            'no-store',
                    }
                );

            if (!response.ok) {
                throw new Error(
                    `HTTP ${response.status}`
                );
            }

            const data =
                await response.json();

            render(data);

            connectionState.className =
                'cod-connection online';

            connectionState
                .querySelector('span')
                .textContent =
                'متصل — آخر تحديث '
                +
                new Date()
                    .toLocaleTimeString(
                        'ar',
                        {
                            hour:
                                '2-digit',
                            minute:
                                '2-digit',
                            second:
                                '2-digit',
                        }
                    );
        } catch (error) {
            connectionState.className =
                'cod-connection error';

            connectionState
                .querySelector('span')
                .textContent =
                'تعذر تحديث الشاشة — إعادة المحاولة تلقائيًا';

            console.debug(
                'Customer display feed failed.',
                error
            );
        } finally {
            requestRunning = false;
        }
    };

    const updateClock = () => {
        if (!clockTime || !clockDate) {
            return;
        }

        const now = new Date();

        clockTime.textContent =
            now.toLocaleTimeString(
                'ar',
                {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                }
            );

        clockDate.textContent =
            now.toLocaleDateString(
                'ar',
                {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                }
            );
    };

    soundButton?.addEventListener(
        'click',
        async () => {
            soundEnabled =
                !soundEnabled;

            localStorage.setItem(
                'customer_display_sound',
                soundEnabled
                    ? 'on'
                    : 'off'
            );

            updateSoundButton();

            if (soundEnabled) {
                await unlockAudio();
            }
        }
    );

    fullscreenButton?.addEventListener(
        'click',
        async () => {
            try {
                if (
                    !document.fullscreenElement
                ) {
                    await shell
                        .requestFullscreen();
                } else {
                    await document
                        .exitFullscreen();
                }
            } catch (error) {
                console.debug(
                    'Fullscreen failed.',
                    error
                );
            }
        }
    );

    locationSelect?.addEventListener(
        'change',
        () => {
            const locationId =
                Number(
                    locationSelect.value
                );

            if (!locationId) {
                return;
            }

            const url =
                new URL(
                    window.location.href
                );

            url.searchParams.set(
                'location_id',
                String(locationId)
            );

            window.location.href =
                url.toString();
        }
    );

    document.addEventListener(
        'fullscreenchange',
        () => {
            if (!fullscreenButton) {
                return;
            }

            fullscreenButton.textContent =
                document.fullscreenElement
                    ? '⤢ خروج من ملء الشاشة'
                    : '⛶ ملء الشاشة';
        }
    );

    document.addEventListener(
        'visibilitychange',
        () => {
            if (!document.hidden) {
                loadFeed();
            }
        }
    );

    updateSoundButton();
    updateClock();

    if (clockTime && clockDate) {
        window.setInterval(
            updateClock,
            1000
        );
    }

    loadFeed();

    window.setInterval(
        () => {
            if (!document.hidden) {
                loadFeed();
            }
        },
        pollMilliseconds
    );
})();
</script>
</body>
</html>
