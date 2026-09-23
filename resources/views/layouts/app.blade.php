@php


$brandNameAr = trim(
    (string) \App\Models\SystemSetting::get(
        'system_name',
        ''
    )
);

$brandNameEn = trim(
    (string) \App\Models\SystemSetting::get(
        'system_name_en',
        ''
    )
);
   $moduleService = app(\App\Services\ModuleService::class);
    $purchasingModuleEnabled = $moduleService->isEnabled('purchasing');
    $suppliersModuleEnabled = $moduleService->isEnabled('suppliers');
    $procurementRoutesAvailable = \Illuminate\Support\Facades\Route::has('procurement.dashboard');

$brandName =
    $brandNameAr !== ''
        ? $brandNameAr
        : (
            $brandNameEn !== ''
                ? $brandNameEn
                : 'اسم النظام'
        );

  

    $brandLogoPath = \App\Models\SystemSetting::get(

        'brand_logo'

    );

    $brandLogoSmallPath = \App\Models\SystemSetting::get(

        'brand_logo_small'

    );

    $brandFaviconPath = \App\Models\SystemSetting::get(

        'brand_favicon'

    );

    $brandLogoUrl = $brandLogoPath

        ? asset($brandLogoPath)

        : (

            $brandLogoSmallPath

                ? asset($brandLogoSmallPath)

                : (

                    file_exists(public_path('assets/images/logo.png'))

                        ? asset('assets/images/logo.png')

                        : null

                )

        );

    $brandLogoSmallUrl = $brandLogoSmallPath

        ? asset($brandLogoSmallPath)

        : $brandLogoUrl;

    $brandFaviconUrl = $brandFaviconPath

        ? asset($brandFaviconPath)

        : null;

    $pwaBrandVersion = substr(
        sha1(implode('|', [
            (string) $brandLogoPath,
            (string) $brandLogoSmallPath,
            (string) $brandFaviconPath,
            (string) \App\Models\SystemSetting::get('theme_primary', ''),
        ])),
        0,
        12
    );

    $pwaThemePrimary = (string) \App\Models\SystemSetting::get(
        'theme_primary',
        '#0A2948'
    );

    if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $pwaThemePrimary)) {
        $pwaThemePrimary = '#0A2948';
    }

    $moduleService = app(\App\Services\ModuleService::class);

    $moduleEnabled = static fn (string $code): bool => $moduleService->isEnabled($code);

    $chatEnabled = $moduleEnabled('chat');

@endphp

<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="manifest" href="{{ route('pwa.manifest') }}">

    <meta name="theme-color" content="{{ $pwaThemePrimary }}">

    <meta name="mobile-web-app-capable" content="yes">

    <meta name="apple-mobile-web-app-capable" content="yes">

    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <link
        rel="icon"
        type="image/png"
        sizes="32x32"
        href="{{ route('pwa.icon', ['size' => 32, 'v' => $pwaBrandVersion]) }}"
    >

    <link
        rel="icon"
        type="image/png"
        sizes="192x192"
        href="{{ route('pwa.icon', ['size' => 192, 'v' => $pwaBrandVersion]) }}"
    >

    <link
        rel="shortcut icon"
        href="{{ route('pwa.icon', ['size' => 32, 'v' => $pwaBrandVersion]) }}"
    >

    <link
        rel="apple-touch-icon"
        sizes="180x180"
        href="{{ route('pwa.icon', ['size' => 180, 'v' => $pwaBrandVersion]) }}"
    >

    <title>@yield('title', $brandName) — نظام الإدارة</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&family=Amiri:wght@400;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">

   <script src="{{ asset('assets/js/live-updates.js') }}" defer></script>

    <style>

        /* =========================================

           Dynamic Branding / Theme bridge

        ========================================= */

        .dahab-body {

            background: var(--theme-bg);

            color: var(--theme-text);

        }

        .dahab-sidebar {

            background: var(--theme-sidebar-bg);

            color: var(--theme-sidebar-text);

        }

        .dahab-sidebar .nav-item {

            color: var(--theme-sidebar-text);

        }

        .dahab-sidebar .nav-section-title {

            color: color-mix(

                in srgb,

                var(--theme-sidebar-text) 48%,

                transparent

            );

        }

        .dahab-sidebar .nav-item:hover {

            background: color-mix(

                in srgb,

                var(--theme-sidebar-text) 7%,

                transparent

            );

        }

        .dahab-sidebar .nav-item.active {

            color: var(--theme-sidebar-active);

            background: color-mix(

                in srgb,

                var(--theme-sidebar-active) 12%,

                transparent

            );

            border-color: color-mix(

                in srgb,

                var(--theme-sidebar-active) 25%,

                transparent

            );

        }

        .dahab-sidebar .nav-item.active::before {

            background: var(--theme-sidebar-active);

        }

        .dahab-topbar {

            background: var(--theme-header-bg);

            color: var(--theme-text);

            border-color: var(--theme-border);

        }

        .dahab-content {

            background: var(--theme-bg);

            color: var(--theme-text);

        }

        .topbar-title {

            color: var(--theme-text);

        }

        .dahab-sidebar .sidebar-footer {

            border-top-color: color-mix(

                in srgb,

                var(--theme-sidebar-active) 18%,

                transparent

            );

        }

        .dahab-sidebar {

            display: flex;

            flex-direction: column;

        }

        .dahab-sidebar .dahab-logo-area {

    min-height: 112px;

    padding: 12px 16px;

}

.dahab-sidebar .dahab-logo-link {

    min-height: 86px;

}

.dahab-sidebar .dahab-main-logo {

    width: auto !important;

    height: auto !important;

    max-width: 185px !important;

    max-height: 88px !important;

    object-fit: contain;

    object-position: center;

}

        .dahab-sidebar .sidebar-nav {

            flex: 1;

            overflow-y: auto;

            overscroll-behavior: contain;

            padding: 14px 12px 22px;

            scrollbar-width: thin;

            scrollbar-color: color-mix(in srgb, var(--theme-sidebar-active) 35%, transparent) transparent;

        }

        .dahab-sidebar .sidebar-nav::-webkit-scrollbar {

            width: 5px;

        }

        .dahab-sidebar .sidebar-nav::-webkit-scrollbar-thumb {

            background: color-mix(in srgb, var(--theme-sidebar-active) 35%, transparent);

            border-radius: 999px;

        }

        .dahab-sidebar .nav-section {

            margin: 0;

            padding: 11px 0;

        }

        .dahab-sidebar .nav-section:first-child {

            padding-top: 0;

        }

        .dahab-sidebar .nav-section+.nav-section {

            border-top: 1px solid rgba(255, 255, 255, 0.06);

        }

        .dahab-sidebar .nav-section-title {

            margin: 0 0 7px;

            padding: 0 12px;

            color: rgba(255, 255, 255, 0.46);

            font-size: 0.7rem;

            font-weight: 700;

            letter-spacing: 0.02em;

        }

        .dahab-sidebar .nav-section.has-collapse {
            padding-block: 7px;
        }

        .dahab-sidebar .nav-section.has-collapse > .nav-section-title {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            min-height: 38px;
            margin: 0;
            padding: 8px 12px;
            border: 0;
            border-radius: 10px;
            color: color-mix(in srgb, var(--theme-sidebar-text) 68%, transparent);
            background: transparent;
            font: inherit;
            font-size: 0.72rem;
            font-weight: 800;
            text-align: start;
            cursor: pointer;
            transition: color .18s ease, background-color .18s ease;
        }

        .dahab-sidebar .nav-section.has-collapse > .nav-section-title:hover,
        .dahab-sidebar .nav-section.has-collapse > .nav-section-title:focus-visible {
            color: var(--theme-sidebar-text);
            background: color-mix(in srgb, var(--theme-sidebar-text) 7%, transparent);
            outline: none;
        }

        .dahab-sidebar .nav-section.has-collapse > .nav-section-title::after {
            content: '';
            width: 7px;
            height: 7px;
            flex: 0 0 7px;
            margin-inline-start: 10px;
            border-inline-end: 1.8px solid currentColor;
            border-block-end: 1.8px solid currentColor;
            transform: rotate(45deg);
            transition: transform .2s ease;
        }

        .dahab-sidebar .nav-section.has-collapse.is-open > .nav-section-title {
            color: var(--theme-sidebar-active, var(--gold, #d4af37));
        }

        .dahab-sidebar .nav-section.has-collapse.is-open > .nav-section-title::after {
            transform: rotate(225deg);
        }

        .dahab-sidebar .nav-section-body {
            display: grid;
            grid-template-rows: 0fr;
            opacity: 0;
            transition: grid-template-rows .22s ease, opacity .18s ease;
        }

        .dahab-sidebar .nav-section-body-inner {
            min-height: 0;
            overflow: hidden;
        }

        .dahab-sidebar .nav-section.has-collapse.is-open > .nav-section-body {
            grid-template-rows: 1fr;
            opacity: 1;
        }

        @media (prefers-reduced-motion: reduce) {
            .dahab-sidebar .nav-section-body,
            .dahab-sidebar .nav-section.has-collapse > .nav-section-title::after {
                transition: none;
            }
        }

        .dahab-sidebar .nav-item {

            position: relative;

            display: flex;

            align-items: center;

            min-height: 43px;

            margin: 3px 0;

            padding: 9px 12px;

            gap: 11px;

            border: 1px solid transparent;

            border-radius: 12px;

            transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease, transform 0.2s ease;

        }

        .dahab-sidebar .nav-item:hover {

            background: rgba(255, 255, 255, 0.055);

            transform: translateX(-2px);

        }

        .dahab-sidebar .nav-item.active {

            color: var(--theme-sidebar-active, var(--gold, #d4af37));

            background: color-mix(in srgb, var(--theme-sidebar-active) 12%, transparent);

            border-color: color-mix(in srgb, var(--theme-sidebar-active) 26%, transparent);

        }

        .dahab-sidebar .nav-item.active::before {

            content: '';

            position: absolute;

            inset-block: 9px;

            inset-inline-start: 0;

            width: 3px;

            border-radius: 999px;

            background: var(--theme-sidebar-active, var(--gold, #d4af37));

        }

        .dahab-sidebar .nav-item:focus-visible {

            outline: 2px solid var(--theme-sidebar-active, var(--gold, #d4af37));

            outline-offset: 2px;

        }

        .dahab-sidebar .nav-icon {

            width: 20px;

            height: 20px;

            flex: 0 0 20px;

        }

        .dahab-sidebar .sidebar-footer {

            flex-shrink: 0;

            border-top: 1px solid color-mix(in srgb, var(--theme-sidebar-active) 18%, transparent);

        }

        .user-avatar-sm {

            overflow: hidden;

            flex-shrink: 0;

        }

        .user-avatar-sm img {

            display: block;

            width: 100%;

            height: 100%;

            object-fit: cover;

            border-radius: 50%;

        }

       @media (max-width: 768px) {

    .dahab-sidebar .dahab-logo-area {

        min-height: 100px;

        padding: 10px 14px;

    }

    .dahab-sidebar .dahab-logo-link {

        min-height: 76px;

    }

    .dahab-sidebar .dahab-main-logo {

        max-width: 165px !important;

        max-height: 76px !important;

    }

}

        /* =========================================

   Global Pagination

========================================= */

.app-pagination {

    direction: rtl;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    width: 100%;

    margin-top: 1.4rem;

    padding: .5rem 0;

    flex-wrap: wrap;

}

.app-page-btn {

    width: 38px;

    height: 38px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    flex: 0 0 38px;

    padding: 0;

    border:

        1px solid

        var(--border);

    border-radius: 9px;

    background:

        var(--surface);

    color:

        var(--text);

    text-decoration: none;

    font-size: .82rem;

    font-weight: 700;

    line-height: 1;

    transition:

        background .18s ease,

        color .18s ease,

        border-color .18s ease,

        transform .18s ease,

        box-shadow .18s ease;

}

a.app-page-btn:hover {

    color:

        var(--gold);

    border-color:

        var(--gold);

    background:

        rgba(212,175,55,.10);

    transform:

        translateY(-2px);

    box-shadow:

        0 4px 12px

        rgba(0,0,0,.08);

}

.app-page-btn.active {

    color: #111;

    background:

        var(--gold);

    border-color:

        var(--gold);

    box-shadow:

        0 4px 12px

        rgba(212,175,55,.25);

    cursor: default;

}

.app-page-arrow {

    width: 40px;

    height: 40px;

    flex-basis: 40px;

}

.app-page-arrow svg {

    display: block;

    width: 14px;

    height: 14px;

}

.app-page-btn.disabled {

    opacity: .35;

    color:

        var(--text-muted);

    cursor:

        not-allowed;

    pointer-events: none;

}

.app-page-dots {

    height: 38px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    padding: 0 4px;

    color:

        var(--text-muted);

    font-size: .75rem;

}

@media(max-width:600px) {

    .app-pagination {

        gap: 5px;

    }

    .app-page-btn {

        width: 34px;

        height: 34px;

        flex-basis: 34px;

        font-size: .75rem;

    }

    .app-page-arrow {

        width: 36px;

        height: 36px;

        flex-basis: 36px;

    }

}

/* =========================================
   PWA install action
========================================= */
.install-app-btn {
    min-height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 0 13px;
    border: 1px solid var(--theme-border, #d7dde3);
    border-radius: 10px;
    background: var(--theme-card-bg, #ffffff);
    color: var(--theme-text, #0d3150);
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
    transition:
        background-color .2s ease,
        border-color .2s ease,
        color .2s ease,
        transform .2s ease;
}

.install-app-btn:hover {
    color: var(--theme-sidebar-active, var(--gold, #d4af37));
    border-color: var(--theme-sidebar-active, var(--gold, #d4af37));
    transform: translateY(-1px);
}

.install-app-btn svg {
    width: 18px;
    height: 18px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

@media(max-width:768px) {
    .install-app-btn {
        width: 38px;
        min-width: 38px;
        padding: 0;
    }

    .install-app-btn span {
        display: none;
    }
}

.pwa-install-dialog {
    width: min(92vw, 460px);
    padding: 0;
    border: 0;
    border-radius: 18px;
    background: var(--theme-card-bg, #fff);
    color: var(--theme-text, #0d3150);
    box-shadow: 0 24px 70px rgba(4, 28, 48, .28);
}

.pwa-install-dialog::backdrop {
    background: rgba(3, 20, 35, .62);
    backdrop-filter: blur(3px);
}

.pwa-install-dialog__body {
    padding: 1.35rem;
}

.pwa-install-dialog__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: .8rem;
}

.pwa-install-dialog__head h3 {
    margin: 0;
    font-size: 1rem;
}

.pwa-install-dialog__close {
    width: 34px;
    height: 34px;
    border: 1px solid var(--theme-border, #d7dde3);
    border-radius: 9px;
    background: transparent;
    color: inherit;
    cursor: pointer;
}

.pwa-install-dialog__message {
    margin: 0;
    color: var(--text-muted, #667085);
    font-size: .84rem;
    line-height: 1.9;
}

.pwa-install-dialog__hint {
    margin-top: .85rem;
    padding: .75rem;
    border-radius: 10px;
    background: rgba(212, 175, 55, .1);
    color: var(--theme-text, #0d3150);
    font-size: .75rem;
    line-height: 1.8;
}

    </style>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.1/dist/apexcharts.min.js" defer></script>

    @stack('styles')

    {{-- الثيم الديناميكي يجب أن يكون آخر CSS حتى يطبق على النظام كاملاً --}}

    @include('layouts.partials.dynamic-theme')
  

</head>

<body class="dahab-body">

    @php

        $headerUser = auth()->user();

        $headerProfileImage = $headerUser->employee?->profile_image ?? $headerUser->profile_image;

        $headerDisplayName = $headerUser->display_name;

        $headerRoleName = \App\Support\ArabicDisplay::role(
            $headerUser->getRoleNames()->first()
        );

        $headerInitial = mb_strtoupper(mb_substr($headerDisplayName ?: 'م', 0, 1));

    @endphp

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="dahab-sidebar" id="sidebar">

        <div class="sidebar-brand dahab-logo-area">

            <a

                href="{{ auth()->user()->can('dashboard.view') ? route('dashboard') : route('profile.show') }}"

                class="brand-link dahab-logo-link"

                title="{{ $brandName }}"

            >

                @if($brandLogoUrl)

                    <img

                        src="{{ $brandLogoUrl }}"

                        alt="{{ $brandName }}"

                        class="brand-logo dahab-main-logo"

                    >

                @else

                    <div class="brand-text-logo">

                        <span class="brand-ar">{{ $brandName }}</span>

                        <span class="brand-en">{{ $brandNameEn }}</span>

                    </div>

                @endif

            </a>

        </div>

        <nav class="sidebar-nav">

            @if($moduleEnabled('dashboard'))

            @can('dashboard.view')

                <div class="nav-section">

                    <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">

                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                            <rect x="3" y="3" width="7" height="7" />

                            <rect x="14" y="3" width="7" height="7" />

                            <rect x="14" y="14" width="7" height="7" />

                            <rect x="3" y="14" width="7" height="7" />

                        </svg>

                        <span>لوحة التحكم</span>

                    </a>

                </div>

            @endcan

            @endif

            @if(

                $chatEnabled

                && \Illuminate\Support\Facades\Route::has('chat.index')

                && auth()->user()->is_active

            )

                <div class="nav-section">

                    <a

                        href="{{ route('chat.index') }}"

                        class="nav-item {{ request()->routeIs('chat.*') ? 'active' : '' }}"

                    >

                        <svg

                            class="nav-icon"

                            viewBox="0 0 24 24"

                            fill="none"

                            stroke="currentColor"

                            stroke-width="2"

                        >

                            <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z" />

                            <path d="M8 9h8" />

                            <path d="M8 13h5" />

                        </svg>

                        <span>المحادثات</span>

                    </a>

                </div>

            @endif

            @canany(['locations.manage', 'employees.view', 'employees.manage', 'users.manage'])

                <div class="nav-section">

                    <div class="nav-section-title">المواقع والموظفون</div>

                    @can('locations.manage')

                        <a href="{{ route('locations.index') }}" class="nav-item {{ request()->routeIs('locations.*') ? 'active' : '' }}">

                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />

                                <circle cx="12" cy="10" r="3" />

                            </svg>

                            <span>الفروع والمصنع</span>

                        </a>

                    @endcan

                    @canany(['employees.view', 'employees.manage'])

                        <a href="{{ route('employees.index') }}" class="nav-item {{ request()->routeIs('employees.*') ? 'active' : '' }}">

                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />

                                <circle cx="9" cy="7" r="4" />

                                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />

                                <path d="M16 3.13a4 4 0 0 1 0 7.75" />

                            </svg>

                            <span>الموظفون</span>

                        </a>

                    @endcanany

                     @include('layouts.partials.payroll-navigation')

                     @include('layouts.partials.attendance-navigation')

                    @can('users.manage')

                        <a href="{{ route('users.index') }}" class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }}">

                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <circle cx="12" cy="8" r="4" />

                                <path d="M20 21a8 8 0 1 0-16 0" />

                            </svg>

                            <span>المستخدمون</span>

                        </a>

                    @endcan

                </div>

            @endcanany

            @if($moduleEnabled('products') || $moduleEnabled('categories'))

            @can('products.view')

                <div class="nav-section">

                    <div class="nav-section-title">كتالوج المنتجات</div>

                    <a href="{{ route('categories.index') }}" class="nav-item {{ request()->routeIs('categories.*') ? 'active' : '' }}">

                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                            <path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2z" />

                        </svg>

                        <span>الفئات</span>

                    </a>

                    <a href="{{ route('products.index') }}" class="nav-item {{ request()->routeIs('products.*') ? 'active' : '' }}">

                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />

                            <line x1="3" y1="6" x2="21" y2="6" />

                            <path d="M16 10a4 4 0 0 1-8 0" />

                        </svg>

                        <span>المنتجات</span>

                    </a>

                    @can('products.update')

                        @if(\Illuminate\Support\Facades\Route::has('catalog.index'))

                            <a href="{{ route('catalog.index') }}" class="nav-item {{ request()->routeIs('catalog.*') ? 'active' : '' }}">

                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                    <circle cx="12" cy="12" r="3" />

                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06-2.83 2.83-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21h-4v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06-2.83-2.83.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3v-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06 2.83-2.83.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3h4v.09A1.65 1.65 0 0 0 15 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06 2.83 2.83-.06.06A1.65 1.65 0 0 0 19.4 9c.12.35.39.64.74.79.24.1.5.16.77.16H21v4h-.09c-.68 0-1.28.42-1.51 1.05z" />

                                </svg>

                                <span>إعدادات الكتالوج</span>

                            </a>

                        @endif

                    @endcan

                </div>

            @endcan

            @endif

            @if($moduleEnabled('inventory'))

            @canany([

                'inventory.view',

                'inventory.adjust',

                'inventory.count',

                'stock_requests.view',

                'stock_requests.create',

                'stock_requests.review',

                'stock_transfers.view',

                'stock_transfers.dispatch',

                'stock_transfers.receive',

            ])

                <div class="nav-section">

                    <div class="nav-section-title">المخزون</div>

                    @can('inventory.view')

                        <a href="{{ route('inventory.index') }}"

                            class="nav-item {{ request()->routeIs('inventory.*')
                                && !request()->routeIs('inventory.movements')
                                && !request()->routeIs('inventory.expiry.index*')
                                ? 'active'
                                : '' }}">

                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2" />

                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />

                            </svg>

                            <span>المخزون الحالي</span>

                        </a>

                        <a href="{{ route('inventory.movements') }}" class="nav-item {{ request()->routeIs('inventory.movements') ? 'active' : '' }}">

                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <polyline points="17 1 21 5 17 9" />

                                <path d="M3 11V9a4 4 0 0 1 4-4h14" />

                                <polyline points="7 23 3 19 7 15" />

                                <path d="M21 13v2a4 4 0 0 1-4 4H3" />

                            </svg>

                            <span>حركات المخزون</span>

                        </a>

                        {{-- Sprint 14 — Batch & Expiry Management --}}
                        @if(\Illuminate\Support\Facades\Route::has('inventory.expiry.index'))
                            @canany(['inventory.view', 'inventory.expiry-alerts.view'])
                                <a
                                    href="{{ route('inventory.expiry.index') }}"
                                    class="nav-item {{ request()->routeIs('inventory.expiry.index*') ? 'active' : '' }}"
                                >
                                    <svg
                                        class="nav-icon"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path d="M12 8v4l3 2" />
                                        <circle cx="12" cy="12" r="9" />
                                        <path d="M18.5 5.5 20 4" />
                                        <path d="M5.5 5.5 4 4" />
                                    </svg>

                                    <span>صلاحية المخزون</span>
                                </a>
                            @endcanany
                        @endif

                    @endcan

                    @can('inventory.count')

                        <a href="{{ route('stock-counts.index') }}" class="nav-item {{ request()->routeIs('stock-counts.*') ? 'active' : '' }}">

                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <polyline points="9 11 12 14 22 4" />

                                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />

                            </svg>

                            <span>جرد المخزون</span>

                        </a>

                    @endcan

                    @canany(['stock_requests.view', 'stock_requests.create', 'stock_requests.review'])

                        <a href="{{ route('stock-requests.index') }}" class="nav-item {{ request()->routeIs('stock-requests.*') ? 'active' : '' }}">

                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />

                                <polyline points="14 2 14 8 20 8" />

                                <line x1="12" y1="18" x2="12" y2="12" />

                                <line x1="9" y1="15" x2="15" y2="15" />

                            </svg>

                            <span>طلبات المخزون</span>

                        </a>

                    @endcanany

                    @canany(['stock_transfers.view', 'stock_transfers.dispatch', 'stock_transfers.receive'])

                        <a href="{{ route('stock-transfers.index') }}" class="nav-item {{ request()->routeIs('stock-transfers.*') ? 'active' : '' }}">

                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <rect x="1" y="3" width="15" height="13" />

                                <polygon points="16 8 20 8 23 11 23 16 16 16 16 8" />

                                <circle cx="5.5" cy="18.5" r="2.5" />

                                <circle cx="18.5" cy="18.5" r="2.5" />

                            </svg>

                            <span>تحويلات المخزون</span>

                        </a>

                    @endcanany

                </div>

            @endcanany

            @endif

            @include('layouts.partials.procurement-navigation')
           
            @if($moduleEnabled('sales') || $moduleEnabled('cake_orders') || $moduleEnabled('bakery'))

            @canany([

                'customers.view',

                'orders.view',

                'cake_orders.view',

                'showroom_cake_requests.view',

                'showroom_sweets_requests.view',

            ])

                <div class="nav-section">

                    <div class="nav-section-title">المبيعات</div>

                    @if($moduleEnabled('customers'))

                    @can('customers.view')

                        <a href="{{ route('customers.index') }}" class="nav-item {{ request()->routeIs('customers.*') ? 'active' : '' }}">

                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />

                                <circle cx="12" cy="7" r="4" />

                            </svg>

                            <span>العملاء</span>

                        </a>

                    @endcan

                    @endif

                    @if($moduleEnabled('sales'))

                    @can('orders.view')

                        <a href="{{ route('orders.index') }}" class="nav-item {{ request()->routeIs('orders.*') ? 'active' : '' }}">

                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" />

                                <rect x="9" y="3" width="6" height="4" rx="2" />

                            </svg>

                            <span>الطلبات</span>

                        </a>

                    @endcan

                    @endif

                    @if($moduleEnabled('cake_orders'))

                    @can('cake_orders.view')

                        <a href="{{ route('cake-orders.index') }}" class="nav-item {{ request()->routeIs('cake-orders.*') ? 'active' : '' }}">

                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <path d="M20 10c0-5.523-8-10-8-10S4 4.477 4 10a4 4 0 0 0 8 0 4 4 0 0 0 8 0z" />

                                <path d="M4 10v4a8 8 0 0 0 16 0v-4" />

                            </svg>

                            <span>طلبات الكيك الخاصة</span>

                        </a>

                    @endcan

                    @endif

                    @if($moduleEnabled('cake_orders'))

                    @can('showroom_cake_requests.view')

                        <a href="{{ route('showroom-cake-requests.index') }}" class="nav-item {{ request()->routeIs('showroom-cake-requests.*') ? 'active' : '' }}">

                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />

                                <polyline points="9 22 9 12 15 12 15 22" />

                            </svg>

                            <span>طلبات كيك الفروع</span>

                        </a>

                    @endcan

                    @endif

                    @if($moduleEnabled('bakery'))

                    @can('showroom_sweets_requests.view')

                        <a href="{{ route('showroom-sweets-requests.index') }}" class="nav-item {{ request()->routeIs('showroom-sweets-requests.*') ? 'active' : '' }}">

                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <path d="M3 8h18" />

                                <path d="M5 8l1 11h12l1-11" />

                                <path d="M8 12h8" />

                                <path d="M9 4h6l2 4H7l2-4z" />

                            </svg>

                            <span>طلبات حلويات الفروع</span>

                        </a>

                    @endcan

                    @endif

                </div>

            @endcanany

            @endif

            {{-- Sprint 08 — CRM + Loyalty + Delivery --}}
            @include('layouts.partials.growth-navigation')


        

            @if($moduleEnabled('restaurant'))

            @canany([

                'restaurant.view',

                'restaurant_pos.use',

                'restaurant_menu.view',

                'restaurant_tables.view',

                'kitchen.view',

                'kitchen.stations.manage',

                'kds.view',

            ])

                <div class="nav-section">

                    <div class="nav-section-title">تشغيل المطعم</div>

                    @can('restaurant.view')

                        @if(\Illuminate\Support\Facades\Route::has('restaurant.dashboard'))

                            <a href="{{ route('restaurant.dashboard') }}" class="nav-item {{ request()->routeIs('restaurant.dashboard') ? 'active' : '' }}">

                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                    <path d="M3 11h18" />

                                    <path d="M5 11v9h14v-9" />

                                    <path d="M8 11V7a4 4 0 0 1 8 0v4" />

                                </svg>

                                <span>لوحة المطعم</span>

                            </a>

                        @endif

                    @endcan

                    @if($moduleEnabled('restaurant_pos'))

                    @can('restaurant_pos.use')

                        @if(\Illuminate\Support\Facades\Route::has('restaurant.pos.index'))

                            <a href="{{ route('restaurant.pos.index') }}" class="nav-item {{ request()->routeIs('restaurant.pos.*') ? 'active' : '' }}">

                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                    <rect x="3" y="3" width="18" height="14" rx="2" />

                                    <path d="M7 21h10" />

                                    <path d="M12 17v4" />

                                </svg>

                                <span>نقطة البيع POS</span>

                            </a>

                        @endif

                    @endcan

                    @endif

                    @can('restaurant_menu.view')

                        @if(\Illuminate\Support\Facades\Route::has('restaurant.menu.index'))

                            <a href="{{ route('restaurant.menu.index') }}"
                               class="nav-item {{ request()->routeIs('restaurant.menu.*') ? 'active' : '' }}">

                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                    <path d="M4 4h6a2 2 0 0 1 2 2v14a2 2 0 0 0-2-2H4z" />

                                    <path d="M20 4h-6a2 2 0 0 0-2 2v14a2 2 0 0 1 2-2h6z" />

                                </svg>

                                <span>منيو المطعم</span>

                            </a>

                        @endif

                    @endcan

                    @can('restaurant_menu.view')

                        @if(\Illuminate\Support\Facades\Route::has('restaurant.menu.banners.index'))

                            <a href="{{ route('restaurant.menu.banners.index') }}"
                               class="nav-item {{ request()->routeIs('restaurant.menu.banners.*') ? 'active' : '' }}">

                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                    <rect x="3" y="5" width="18" height="12" rx="2" />

                                    <path d="M3 9h18" />

                                    <path d="M7 15h4" />

                                </svg>

                                <span>بطاقات إعلانات المنيو</span>

                            </a>

                        @endif

                    @endcan

                    @if($moduleEnabled('restaurant_tables'))

                    @can('restaurant_tables.view')

                        @if(\Illuminate\Support\Facades\Route::has('restaurant.tables.index'))

                            <a href="{{ route('restaurant.tables.index') }}" class="nav-item {{ request()->routeIs('restaurant.tables.*') ? 'active' : '' }}">

                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                    <rect x="4" y="8" width="16" height="8" rx="2" />

                                    <path d="M7 16v4M17 16v4M7 8V4M17 8V4" />

                                </svg>

                                <span>الطاولات والجلسات</span>

                            </a>

                        @endif

                    @endcan

                    @endif

                    @if($moduleEnabled('kds'))

                    @can('kds.view')

                        @if(\Illuminate\Support\Facades\Route::has('kds.index'))

                            <a href="{{ route('kds.index') }}" class="nav-item {{ request()->routeIs('kds.*') ? 'active' : '' }}">

                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                    <rect x="3" y="4" width="18" height="13" rx="2" />

                                    <path d="M8 21h8M12 17v4" />

                                </svg>

                                <span>شاشة المطبخ KDS</span>

                            </a>

                        @endif

                    @endcan

                    @endif

                    @include('layouts.partials.customer-display-nav')

                    @if($moduleEnabled('kitchen'))

                    @can('kitchen.view')

                        @if(\Illuminate\Support\Facades\Route::has('kitchen.tickets.index'))

                            <a href="{{ route('kitchen.tickets.index') }}" class="nav-item {{ request()->routeIs('kitchen.tickets.*') ? 'active' : '' }}">

                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                    <path d="M4 12h16M6 12a6 6 0 0 1 12 0M12 6V3" />

                                    <path d="M3 16h18" />

                                </svg>

                                <span>تذاكر المطبخ</span>

                            </a>

                        @endif

                    @endcan

                    @can('kitchen.stations.manage')

                        @if(\Illuminate\Support\Facades\Route::has('kitchen.stations.index'))

                            <a href="{{ route('kitchen.stations.index') }}" class="nav-item {{ request()->routeIs('kitchen.stations.*') ? 'active' : '' }}">

                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                    <circle cx="12" cy="12" r="3" />

                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06-2.83 2.83-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 .98V21h-4v-.09a1.65 1.65 0 0 0-1-.98 1.65 1.65 0 0 0-1.82.33l-.06.06-2.83-2.83.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-.98-1H3v-4h.62a1.65 1.65 0 0 0 .98-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06L7.04 4.3l.06.06a1.65 1.65 0 0 0 1.82.33h.01a1.65 1.65 0 0 0 .98-1V3h4v.69a1.65 1.65 0 0 0 1 .98 1.65 1.65 0 0 0 1.82-.33l.06-.06 2.83 2.83-.06.06A1.65 1.65 0 0 0 19.4 9c.12.4.48.77.98 1H21v4h-.62a1.65 1.65 0 0 0-.98 1z" />

                                </svg>

                                <span>محطات المطبخ</span>

                            </a>

                        @endif

                    @endcan

                    @endif

                </div>

            @endcanany

            @endif

            @if($moduleEnabled('production') || $moduleEnabled('recipes'))

            @canany([

                'production.view',

                'production.create',

                'production.release',

                'production.start',

                'production.complete',

                'recipes.view',

                'recipes.create',

                'recipes.update',

                'recipes.activate',

                'recipes.cost',

            ])

                <div class="nav-section">

                    <div class="nav-section-title">الإنتاج والوصفات</div>

                    @if($moduleEnabled('production'))

                    @can('production.view')

                        @if(\Illuminate\Support\Facades\Route::has('production.dashboard'))

                            <a href="{{ route('production.dashboard') }}" class="nav-item {{ request()->routeIs('production.dashboard') ? 'active' : '' }}">

                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                    <path d="M3 21h18" />

                                    <path d="M5 21V9l5 3V9l5 3V4h4v17" />

                                </svg>

                                <span>لوحة الإنتاج</span>

                            </a>

                        @endif

                        @if(\Illuminate\Support\Facades\Route::has('production.orders.index'))

                            <a href="{{ route('production.orders.index') }}" class="nav-item {{ request()->routeIs('production.orders.*') ? 'active' : '' }}">

                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                    <rect x="4" y="4" width="16" height="16" rx="2" />

                                    <path d="M8 9h8M8 13h8M8 17h5" />

                                </svg>

                                <span>أوامر الإنتاج</span>

                            </a>

                        @endif

                    @endcan

                    @endif

                    @if($moduleEnabled('recipes'))

                    @can('recipes.view')

                        @if(\Illuminate\Support\Facades\Route::has('recipes.index'))

                            <a href="{{ route('production.recipes.index') }}" class="nav-item {{ request()->routeIs('production.recipes.*') ? 'active' : '' }}">

                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                    <path d="M6 2h9l3 3v17H6z" />

                                    <path d="M14 2v4h4M9 11h6M9 15h6M9 19h4" />

                                </svg>

                                <span>الوصفات وBOM</span>

                            </a>

                        @endif

                    @endcan

                    @endif

                </div>

            @endcanany

            @endif

            @if($moduleEnabled('finance') || $moduleEnabled('payments') || $moduleEnabled('invoices'))

            @canany([

                'payments.record',

                'payments.verify',

                'payments.correct',

                'payments.refund',

                'financial.branch.view',

                'financial.global.view',

                'financial.collections.view',

                'invoices.view',

                'financial.dashboard.view',

                'financial.periods.view',

            ])

                <div class="nav-section">

                    <div class="nav-section-title">المالية</div>

                    @if($moduleEnabled('payments'))

                    @canany([

                        'payments.record',

                        'payments.verify',

                        'payments.correct',

                        'payments.refund',

                        'financial.branch.view',

                        'financial.global.view',

                        'financial.collections.view',

                    ])

                        <a href="{{ route('payments.index') }}" class="nav-item {{ request()->routeIs('payments.index') ? 'active' : '' }}">

                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <line x1="12" y1="1" x2="12" y2="23" />

                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />

                            </svg>

                            <span>الحركات المالية</span>

                        </a>

                        <a
                            href="{{ route('payments.bank-sales') }}"
                            class="nav-item {{ request()->routeIs('payments.bank-sales') ? 'active' : '' }}"
                        >
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="10" width="18" height="10" rx="2" />
                                <path d="M7 10V7a5 5 0 0 1 10 0v3" />
                                <path d="M7 15h.01M11 15h2" />
                            </svg>
                            <span>المبيعات البنكية</span>
                        </a>

                    @endcanany

                    @endif

                    @if($moduleEnabled('invoices'))

                    @can('invoices.view')

                        <a href="{{ route('invoices.index') }}" class="nav-item {{ request()->routeIs('invoices.*') ? 'active' : '' }}">

                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />

                                <polyline points="14 2 14 8 20 8" />

                            </svg>

                            <span>الفواتير</span>

                        </a>

                    @endcan

                    @endif

                    
             



                     @if($purchasingModuleEnabled)
            @can('procurement.exchange_rates.view')
                <a href="{{ route('procurement.exchange-rates.index') }}"
                    class="nav-item {{ request()->routeIs('procurement.exchange-rates.*') ? 'active' : '' }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M15 8h-4a2 2 0 0 0 0 4h2a2 2 0 0 1 0 4H9" />
                        <line x1="12" y1="6" x2="12" y2="18" />
                    </svg>
                    <span>العملات وأسعار الصرف</span>
                </a>
            @endcan
            @endif

                    @if($moduleEnabled('finance'))

                    @canany(['payments.record', 'financial.branch.view', 'financial.global.view'])

                        <a href="{{ route('cash-sessions.index') }}" class="nav-item {{ request()->routeIs('cash-sessions.*') ? 'active' : '' }}">

                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <rect x="2" y="5" width="20" height="14" rx="2" />

                                <line x1="2" y1="10" x2="22" y2="10" />

                            </svg>

                            <span>جلسات الكاشير</span>

                        </a>

                    @endcanany

                    @endif

                    @if($moduleEnabled('finance'))

                    @can('financial.dashboard.view')

                        <a href="{{ route('financial.dashboard') }}" class="nav-item {{ request()->routeIs('financial.*') ? 'active' : '' }}">

                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <line x1="12" y1="1" x2="12" y2="23" />

                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />

                            </svg>

                            <span>لوحة المالية</span>

                        </a>

                    @endcan

                    @endif

                    @if($moduleEnabled('finance'))

                    @can('financial.periods.view')

                        <a href="{{ route('financial-periods.index') }}" class="nav-item {{ request()->routeIs('financial-periods.*') ? 'active' : '' }}">

                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />

                                <line x1="16" y1="2" x2="16" y2="6" />

                                <line x1="8" y1="2" x2="8" y2="6" />

                                <line x1="3" y1="10" x2="21" y2="10" />

                            </svg>

                            <span>الفترات المالية</span>

                        </a>

                    @endcan

                    @endif

                </div>

            @endcanany

            @endif

            @if($moduleEnabled('reports'))

            @can('reports.view')

                <div class="nav-section">

                    <div class="nav-section-title">التقارير</div>

                    <a href="{{ route('reports.index') }}"

                        class="nav-item {{ request()->routeIs('reports.index') || request()->routeIs('reports.show') || request()->routeIs('reports.export.*') ? 'active' : '' }}">

                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                            <path d="M18 20V10" />

                            <path d="M12 20V4" />

                            <path d="M6 20v-6" />

                        </svg>

                        <span>التقارير</span>

                    </a>

                    <a href="{{ route('report-schedules.index') }}" class="nav-item {{ request()->routeIs('report-schedules.*') ? 'active' : '' }}">

                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                            <rect x="3" y="4" width="18" height="18" rx="2" />

                            <line x1="16" y1="2" x2="16" y2="6" />

                            <line x1="8" y1="2" x2="8" y2="6" />

                            <line x1="3" y1="10" x2="21" y2="10" />

                            <line x1="8" y1="14" x2="8" y2="14" />

                            <line x1="12" y1="14" x2="16" y2="14" />

                            <line x1="8" y1="18" x2="8" y2="18" />

                            <line x1="12" y1="18" x2="16" y2="18" />

                        </svg>

                        <span>التقارير المجدولة</span>

                    </a>

                </div>

            @endcan

            @endif

            @canany(['settings.manage', 'payment_methods.view', 'roles.manage', 'sales_channels.view'])

                <div class="nav-section">

                    <div class="nav-section-title">النظام</div>

                    @can('roles.manage')

                        <a href="{{ route('roles.index') }}" class="nav-item {{ request()->routeIs('roles.*') ? 'active' : '' }}">

                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <circle cx="12" cy="12" r="3" />

                                <path d="M19.07 4.93l-1.41 1.41M5.34 5.34L3.93 6.75M19.07 19.07l-1.41-1.41M5.34 18.66L3.93 17.25M21 12h-2M5 12H3M12 21v-2M12 5V3" />

                            </svg>

                            <span>الأدوار والصلاحيات</span>

                        </a>

                    @endcan

                    @if($moduleEnabled('payment_methods'))

                    @can('payment_methods.view')

                        <a href="{{ route('payment-methods.index') }}" class="nav-item {{ request()->routeIs('payment-methods.*') ? 'active' : '' }}">

                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <rect x="2" y="5" width="20" height="14" rx="2" />

                                <line x1="2" y1="10" x2="22" y2="10" />

                            </svg>

                            <span>طرق الدفع</span>

                        </a>

                    @endcan

                    @endif

                    @can('settings.manage')

                        <a href="{{ route('settings.index') }}"

                            class="nav-item {{ request()->routeIs('settings.*') && !request()->routeIs('settings.sales-channels.*') ? 'active' : '' }}">

                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <circle cx="12" cy="12" r="3" />

                                <path d="M19.07 4.93l-1.41 1.41M5.34 5.34L3.93 6.75M19.07 19.07l-1.41-1.41M5.34 18.66L3.93 17.25M21 12h-2M5 12H3M12 21v-2M12 5V3" />

                            </svg>

                            <span>إعدادات النظام</span>

                        </a>

                        {{-- @if(auth()->user()->isAdmin() && \Illuminate\Support\Facades\Route::has('modules.index'))

                            <a href="{{ route('modules.index') }}" class="nav-item {{ request()->routeIs('modules.*') ? 'active' : '' }}">

                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                    <rect x="3" y="3" width="7" height="7"/>

                                    <rect x="14" y="3" width="7" height="7"/>

                                    <rect x="3" y="14" width="7" height="7"/>

                                    <rect x="14" y="14" width="7" height="7"/>

                                </svg>

                                <span>إدارة الوحدات</span>

                            </a>

                        @endif --}}


                        @include('layouts.partials.onboarding-navigation')

                        @include('layouts.partials.release-center-navigation')

                        {{-- @if(auth()->user()->isAdmin() && \Illuminate\Support\Facades\Route::has('business-profiles.index'))

                            <a href="{{ route('business-profiles.index') }}" class="nav-item {{ request()->routeIs('business-profiles.*') ? 'active' : '' }}">

                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                    <path d="M3 21h18" />

                                    <path d="M5 21V7l7-4 7 4v14" />

                                    <path d="M9 21v-6h6v6" />

                                </svg>

                                <span>نوع النشاط</span>

                            </a>

                        @endif --}}

                    @endcan

                    @if($moduleEnabled('sales_channels'))

                    @can('sales_channels.view')

                        <a href="{{ route('settings.sales-channels.index') }}"

                            class="nav-item {{ request()->routeIs('settings.sales-channels.*') ? 'active' : '' }}">

                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <path d="M3 9l2-5h14l2 5" />

                                <path d="M5 13v7h14v-7" />

                                <path d="M9 20v-5h6v5" />

                                <path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0" />

                            </svg>

                            <span>قنوات البيع</span>

                        </a>

                    @endcan

                    @endif

                </div>

            @endcanany

        </nav>

        <div class="sidebar-footer">

            <div class="user-info">

                <div class="user-avatar-sm">

                    @if ($headerProfileImage)

                        <img src="{{ asset('storage/' . $headerProfileImage) }}" alt="{{ $headerDisplayName }}">

                    @else

                        {{ $headerInitial }}

                    @endif

                </div>

                <div class="user-details">

                    <div class="user-name">{{ $headerDisplayName }}</div>

                    <div class="user-role">{{ $headerRoleName }}</div>

                </div>

            </div>

            <form method="POST" action="{{ route('logout') }}" class="logout-form">

                @csrf

                <button type="submit" class="logout-btn" title="تسجيل الخروج">

                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />

                        <polyline points="16 17 21 12 16 7" />

                        <line x1="21" y1="12" x2="9" y2="12" />

                    </svg>

                </button>

            </form>

        </div>

    </aside>

    <div class="dahab-main" id="main-content">

        <header class="dahab-topbar">

            <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="فتح القائمة الجانبية">

                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                    <line x1="3" y1="6" x2="21" y2="6" />

                    <line x1="3" y1="12" x2="21" y2="12" />

                    <line x1="3" y1="18" x2="21" y2="18" />

                </svg>

            </button>

            <div class="topbar-title">@yield('page-title', 'لوحة التحكم')</div>

            <div class="topbar-actions">

                <button
                    type="button"
                    class="install-app-btn"
                    id="installAppButton"
                    title="تحميل التطبيق"
                    aria-label="تحميل التطبيق"
                    hidden
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 3v12" />
                        <path d="m7 10 5 5 5-5" />
                        <path d="M5 21h14" />
                    </svg>

                    <span>تحميل التطبيق</span>
                </button>

                <dialog class="pwa-install-dialog" id="pwaInstallHelp">
                    <div class="pwa-install-dialog__body">
                        <div class="pwa-install-dialog__head">
                            <h3 id="pwaInstallHelpTitle">تثبيت التطبيق</h3>
                            <button type="button" class="pwa-install-dialog__close" id="pwaInstallHelpClose" aria-label="إغلاق">×</button>
                        </div>

                        <p class="pwa-install-dialog__message" id="pwaInstallHelpMessage"></p>

                        <div class="pwa-install-dialog__hint">
                            التثبيت يحتاج اتصال HTTPS آمن، أو فتح النظام من localhost على نفس الجهاز.
                        </div>
                    </div>
                </dialog>

                @if($chatEnabled && \Illuminate\Support\Facades\Route::has('chat.index'))

                    @include('layouts.partials.chat-topbar')

                @endif

                <div class="notif-wrapper" id="notifWrapper">

                    <button type="button" class="notif-btn" id="notifBtn" aria-label="الإشعارات">

                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />

                            <path d="M13.73 21a2 2 0 0 1-3.46 0" />

                        </svg>

                        <span class="notif-badge" id="notifCount" style="display:none">0</span>

                    </button>

                    <div class="notif-dropdown" id="notifDropdown">

                        <div class="notif-header">

                            <span>الإشعارات</span>

                            <a href="#" id="markAllRead" class="notif-mark-all">تحديد الكل كمقروء</a>

                        </div>

                        <div class="notif-list" id="notifList">

                            <div class="notif-empty">لا توجد إشعارات جديدة</div>

                        </div>

                        <div class="notif-footer">

                            <a href="{{ route('notifications.index') }}">عرض كل الإشعارات</a>

                        </div>

                    </div>

                </div>

                <div class="user-menu-wrapper" id="userMenuWrapper">

                    <button type="button" class="user-menu-btn" id="userMenuBtn" aria-label="قائمة المستخدم">

                        <div class="user-avatar-sm">

                            @if ($headerProfileImage)

                                <img src="{{ asset('storage/' . $headerProfileImage) }}" alt="{{ $headerDisplayName }}">

                            @else

                                {{ $headerInitial }}

                            @endif

                        </div>

                        <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                            <polyline points="6 9 12 15 18 9" />

                        </svg>

                    </button>

                    <div class="user-dropdown" id="userDropdown">

                        <a href="{{ route('profile.show') }}" class="dropdown-item">

                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />

                                <circle cx="12" cy="7" r="4" />

                            </svg>

                            الملف الشخصي

                        </a>

                        <a href="{{ route('auth.change-password') }}" class="dropdown-item">

                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />

                                <path d="M7 11V7a5 5 0 0 1 10 0v4" />

                            </svg>

                            تغيير كلمة المرور

                        </a>

                        <div class="dropdown-divider"></div>

                        <form method="POST" action="{{ route('logout') }}">

                            @csrf

                            <button type="submit" class="dropdown-item dropdown-item-danger">

                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />

                                    <polyline points="16 17 21 12 16 7" />

                                    <line x1="21" y1="12" x2="9" y2="12" />

                                </svg>

                                تسجيل الخروج

                            </button>

                        </form>

                    </div>

                </div>

            </div>

        </header>

        <div class="flash-container" id="flashContainer">

            @if (session('success'))

                <div class="toast toast-success" data-auto-dismiss>

                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                        <polyline points="20 6 9 17 4 12" />

                    </svg>

                    <span>{{ session('success') }}</span>

                    <button type="button" class="toast-close" aria-label="إغلاق" onclick="this.parentElement.remove()">×</button>

                </div>

            @endif

            @if (session('error'))

                <div class="toast toast-error" data-auto-dismiss>

                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                        <circle cx="12" cy="12" r="10" />

                        <line x1="12" y1="8" x2="12" y2="12" />

                        <line x1="12" y1="16" x2="12.01" y2="16" />

                    </svg>

                    <span>{{ session('error') }}</span>

                    <button type="button" class="toast-close" aria-label="إغلاق" onclick="this.parentElement.remove()">×</button>

                </div>

            @endif

            @if (session('warning'))

                <div class="toast toast-warning" data-auto-dismiss>

                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />

                        <line x1="12" y1="9" x2="12" y2="13" />

                        <line x1="12" y1="17" x2="12.01" y2="17" />

                    </svg>

                    <span>{{ session('warning') }}</span>

                    <button type="button" class="toast-close" aria-label="إغلاق" onclick="this.parentElement.remove()">×</button>

                </div>

            @endif

        </div>

        <main class="dahab-content">

            @yield('content')

        </main>

    </div>

    <div class="modal-overlay" id="confirmModal" style="display:none">

        <div class="modal-box">

            <div class="modal-icon modal-icon-danger">

                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />

                    <line x1="12" y1="9" x2="12" y2="13" />

                    <line x1="12" y1="17" x2="12.01" y2="17" />

                </svg>

            </div>

            <h3 class="modal-title" id="confirmTitle">تأكيد العملية</h3>

            <p class="modal-body" id="confirmBody">هل أنت متأكد؟</p>

            <div class="modal-actions">

                <button type="button" class="btn btn-outline" onclick="closeConfirmModal()">إلغاء</button>

                <button type="button" class="btn btn-danger" id="confirmBtn">تأكيد</button>

            </div>

        </div>

    </div>

    <script src="{{ asset('assets/js/app.js') }}"></script>

    <script>

        document.addEventListener('DOMContentLoaded', () => {

            const checkUrl = @json(route('notifications.check-new'));

            const recentUrl = @json(route('notifications.recent'));

            const readAllUrl = @json(route('notifications.read-all'));

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            const notifBtn = document.getElementById('notifBtn');

            const badge = document.getElementById('notifCount');

            const list = document.getElementById('notifList');

            const markAllRead = document.getElementById('markAllRead');

            if (!notifBtn || !badge || !list) return;

            let latestNotificationId = null;

            let requestRunning = false;

            let audioContext = null;

            let audioUnlocked = false;

            let soundEnabled = localStorage.getItem('dahab_notification_sound') !== 'off';

            const escapeHtml = (value) => {

                const element = document.createElement('div');

                element.textContent = value ?? '';

                return element.innerHTML;

            };

            const updateBadge = (count) => {

                count = Number(count || 0);

                badge.textContent = count > 99 ? '99+' : String(count);

                badge.style.display = count > 0 ? 'inline-flex' : 'none';

            };

            const unlockAudio = async () => {

                if (!soundEnabled || audioUnlocked) return;

                const AudioContextClass = window.AudioContext || window.webkitAudioContext;

                if (!AudioContextClass) return;

                audioContext ??= new AudioContextClass();

                if (audioContext.state === 'suspended') {

                    await audioContext.resume();

                }

                audioUnlocked = audioContext.state === 'running';

            };

            const playNotificationSound = async () => {

                if (!soundEnabled) return;

                await unlockAudio();

                if (!audioUnlocked || !audioContext) return;

                const now = audioContext.currentTime;

                const gain = audioContext.createGain();

                gain.connect(audioContext.destination);

                gain.gain.setValueAtTime(0.0001, now);

                gain.gain.exponentialRampToValueAtTime(0.24, now + 0.015);

                gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.55);

                [880, 1174].forEach((frequency, index) => {

                    const oscillator = audioContext.createOscillator();

                    oscillator.type = 'sine';

                    oscillator.frequency.value = frequency;

                    oscillator.connect(gain);

                    oscillator.start(now + (index * 0.12));

                    oscillator.stop(now + 0.42 + (index * 0.12));

                });

            };

            const renderNotifications = (items) => {

                if (!Array.isArray(items) || items.length === 0) {

                    list.innerHTML = '<div class="notif-empty">لا توجد إشعارات جديدة</div>';

                    return;

                }

                list.innerHTML = items.map((item) => `

            <a class="notif-item" href="${escapeHtml(item.url || '#')}">

                <div class="notif-item-content">

                    <strong>${escapeHtml(item.title)}</strong>

                    <span>${escapeHtml(item.message)}</span>

                    <small>${escapeHtml(item.time)}</small>

                </div>

            </a>

        `).join('');

            };

            const loadRecent = async () => {

                try {

                    const response = await fetch(recentUrl, {

                        headers: {

                            'Accept': 'application/json',

                            'X-Requested-With': 'XMLHttpRequest'

                        },

                        credentials: 'same-origin',

                        cache: 'no-store',

                    });

                    if (!response.ok) throw new Error(`HTTP ${response.status}`);

                    const data = await response.json();

                    renderNotifications(data.items || []);

                } catch (error) {

                    console.debug('Loading recent notifications failed.', error);

                }

            };

            const checkNewNotifications = async () => {

                if (requestRunning || document.hidden) return;

                requestRunning = true;

                try {

                    const url = new URL(checkUrl, window.location.origin);

                    if (latestNotificationId) url.searchParams.set('after_id', latestNotificationId);

                    const response = await fetch(url, {

                        headers: {

                            'Accept': 'application/json',

                            'X-Requested-With': 'XMLHttpRequest'

                        },

                        credentials: 'same-origin',

                        cache: 'no-store',

                    });

                    if (!response.ok) throw new Error(`HTTP ${response.status}`);

                    const data = await response.json();

                    updateBadge(data.unread_count);

                    if (data.has_new && latestNotificationId !== null) {

                        await playNotificationSound();

                        await loadRecent();

                    }

                    latestNotificationId = data.latest_id || latestNotificationId;

                    if (latestNotificationId) notifBtn.dataset.latestId = latestNotificationId;

                } catch (error) {

                    console.debug('Notification check failed.', error);

                } finally {

                    requestRunning = false;

                }

            };

            window.addEventListener('pointerdown', unlockAudio, {

                once: true

            });

            window.addEventListener('keydown', unlockAudio, {

                once: true

            });

            notifBtn?.addEventListener('click', loadRecent);

            markAllRead?.addEventListener('click', async (event) => {

                event.preventDefault();

                try {

                    const response = await fetch(readAllUrl, {

                        method: 'POST',

                        headers: {

                            'Accept': 'application/json',

                            'X-Requested-With': 'XMLHttpRequest',

                            'X-CSRF-TOKEN': csrfToken || '',

                        },

                        credentials: 'same-origin',

                    });

                    if (!response.ok) throw new Error(`HTTP ${response.status}`);

                    updateBadge(0);

                    renderNotifications([]);

                } catch (error) {

                    console.debug('Marking notifications as read failed.', error);

                }

            });

            window.setDahabNotificationSound = (enabled) => {

                soundEnabled = Boolean(enabled);

                localStorage.setItem('dahab_notification_sound', soundEnabled ? 'on' : 'off');

                if (soundEnabled) unlockAudio();

            };

            checkNewNotifications();

            window.setInterval(checkNewNotifications, 5000);

            document.addEventListener('visibilitychange', () => {

                if (!document.hidden) checkNewNotifications();

            });

        });

    </script>


    {{-- =========================================================
         أقسام Sidebar قابلة للفتح والإغلاق
    ========================================================= --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebarNav = document.querySelector('.dahab-sidebar .sidebar-nav');

            if (!sidebarNav) {
                return;
            }

            const storageKey = 'dahab_sidebar_collapsed_sections_v1';
            let savedState = {};

            try {
                savedState = JSON.parse(localStorage.getItem(storageKey) || '{}');
            } catch (error) {
                savedState = {};
            }

            const persistState = function () {
                const state = {};

                sidebarNav.querySelectorAll('.nav-section.has-collapse').forEach(function (section) {
                    state[section.dataset.collapseKey] = section.classList.contains('is-open');
                });

                localStorage.setItem(storageKey, JSON.stringify(state));
            };

            sidebarNav.querySelectorAll('.nav-section').forEach(function (section, index) {
                const originalTitle = section.querySelector(':scope > .nav-section-title');

                if (!originalTitle) {
                    return;
                }

                const titleText = originalTitle.textContent.trim();
                const sectionKey = originalTitle.dataset.collapseKey
                    || titleText.replace(/\s+/g, '-').toLowerCase()
                    || 'section-' + index;
                const button = document.createElement('button');
                const body = document.createElement('div');
                const bodyInner = document.createElement('div');
                const panelId = 'sidebar-collapse-' + index;

                button.type = 'button';
                button.className = originalTitle.className;
                button.innerHTML = originalTitle.innerHTML;
                button.setAttribute('aria-controls', panelId);

                body.className = 'nav-section-body';
                body.id = panelId;
                bodyInner.className = 'nav-section-body-inner';

                while (originalTitle.nextSibling) {
                    bodyInner.appendChild(originalTitle.nextSibling);
                }

                body.appendChild(bodyInner);
                originalTitle.replaceWith(button);
                section.appendChild(body);
                section.classList.add('has-collapse');
                section.dataset.collapseKey = sectionKey;

                const containsActiveLink = Boolean(body.querySelector('.nav-item.active'));
                const shouldOpen = containsActiveLink
                    || (Object.prototype.hasOwnProperty.call(savedState, sectionKey)
                        ? Boolean(savedState[sectionKey])
                        : false);

                section.classList.toggle('is-open', shouldOpen);
                button.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
                body.hidden = false;

                button.addEventListener('click', function () {
                    const willOpen = !section.classList.contains('is-open');

                    section.classList.toggle('is-open', willOpen);
                    button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                    persistState();
                });
            });

            persistState();
        });
    </script>

    {{-- =========================================================
         حفظ موضع تمرير القائمة الجانبية أثناء التنقل
         يمنع Sidebar من القفز للأعلى/الأسفل بين صفحات المالية وغيرها
    ========================================================= --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebarNav = document.querySelector('.dahab-sidebar .sidebar-nav');

            if (!sidebarNav) {
                return;
            }

            const storageKey = 'dahab_sidebar_scroll_position';

            const readSavedPosition = function () {
                const saved = Number(sessionStorage.getItem(storageKey));

                return Number.isFinite(saved) && saved >= 0
                    ? saved
                    : 0;
            };

            const savePosition = function () {
                sessionStorage.setItem(
                    storageKey,
                    String(Math.max(0, sidebarNav.scrollTop))
                );
            };

            const restorePosition = function () {
                const savedPosition = readSavedPosition();

                const maxScroll = Math.max(
                    0,
                    sidebarNav.scrollHeight - sidebarNav.clientHeight
                );

                sidebarNav.scrollTop = Math.min(
                    savedPosition,
                    maxScroll
                );
            };

            /*
             * نعيد الموضع أكثر من مرة لأن عناصر القائمة تتغير حسب
             * الصلاحيات والموديولات وقد يتغير ارتفاعها أثناء رسم الصفحة.
             */
            restorePosition();

            requestAnimationFrame(function () {
                restorePosition();

                requestAnimationFrame(function () {
                    restorePosition();
                });
            });

            window.addEventListener('load', restorePosition);

            let saveFrame = null;

            sidebarNav.addEventListener(
                'scroll',
                function () {
                    if (saveFrame !== null) {
                        cancelAnimationFrame(saveFrame);
                    }

                    saveFrame = requestAnimationFrame(function () {
                        savePosition();
                        saveFrame = null;
                    });
                },
                { passive: true }
            );

            /*
             * نحفظ المكان قبل فتح أي رابط في السايدبار.
             */
            sidebarNav.addEventListener('click', function (event) {
                const link = event.target.closest('a.nav-item');

                if (!link) {
                    return;
                }

                savePosition();
            });

            /*
             * دعم Refresh / Back / Forward.
             */
            window.addEventListener('pagehide', savePosition);
            window.addEventListener('beforeunload', savePosition);

            window.addEventListener('pageshow', function () {
                requestAnimationFrame(restorePosition);
            });
        });
    </script>

    <script>
        (() => {
            const installButton = document.getElementById('installAppButton');
            const helpDialog = document.getElementById('pwaInstallHelp');
            const helpTitle = document.getElementById('pwaInstallHelpTitle');
            const helpMessage = document.getElementById('pwaInstallHelpMessage');
            const helpClose = document.getElementById('pwaInstallHelpClose');

            let deferredInstallPrompt = null;

            const isStandalone =
                window.matchMedia('(display-mode: standalone)').matches
                || window.navigator.standalone === true;

            const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
            const isAndroid = /android/i.test(navigator.userAgent);
            const isLocalHost = ['localhost', '127.0.0.1', '::1'].includes(location.hostname);
            const isSecureInstallContext = window.isSecureContext || isLocalHost;

            const openHelp = () => {
                if (! helpDialog || ! helpMessage || ! helpTitle) {
                    return;
                }

                if (! isSecureInstallContext) {
                    helpTitle.textContent = 'يلزم رابط آمن لتثبيت التطبيق';
                    helpMessage.textContent =
                        'أنت تفتح النظام عبر عنوان شبكة HTTP. المتصفح يمنع تثبيت التطبيق وتسجيل Service Worker في هذه الحالة. افتح النظام عبر HTTPS، أو من localhost على جهاز السيرفر.';
                } else if (isIos) {
                    helpTitle.textContent = 'تثبيت التطبيق على iPhone أو iPad';
                    helpMessage.textContent =
                        'افتح الصفحة في Safari، اضغط زر المشاركة، ثم اختر «إضافة إلى الشاشة الرئيسية».';
                } else if (isAndroid) {
                    helpTitle.textContent = 'تثبيت التطبيق على Android';
                    helpMessage.textContent =
                        'إذا لم تظهر نافذة التثبيت، افتح قائمة Chrome واختر «تثبيت التطبيق» أو «إضافة إلى الشاشة الرئيسية».';
                } else {
                    helpTitle.textContent = 'تثبيت التطبيق على الكمبيوتر';
                    helpMessage.textContent =
                        'استخدم Chrome أو Edge، ثم اضغط أيقونة التثبيت في شريط العنوان. إذا لم تظهر، تأكد من فتح النظام عبر HTTPS.';
                }

                if (typeof helpDialog.showModal === 'function') {
                    helpDialog.showModal();
                } else {
                    helpDialog.setAttribute('open', 'open');
                }
            };

            if (installButton) {
                installButton.hidden = isStandalone;
            }

            window.addEventListener('beforeinstallprompt', event => {
                event.preventDefault();
                deferredInstallPrompt = event;

                if (installButton && ! isStandalone) {
                    installButton.hidden = false;
                }
            });

            installButton?.addEventListener('click', async () => {
                if (deferredInstallPrompt) {
                    await deferredInstallPrompt.prompt();
                    const choice = await deferredInstallPrompt.userChoice;

                    if (choice.outcome === 'accepted') {
                        installButton.hidden = true;
                    }

                    deferredInstallPrompt = null;
                    return;
                }

                openHelp();
            });

            helpClose?.addEventListener('click', () => {
                if (typeof helpDialog?.close === 'function') {
                    helpDialog.close();
                } else {
                    helpDialog?.removeAttribute('open');
                }
            });

            helpDialog?.addEventListener('click', event => {
                if (event.target === helpDialog && typeof helpDialog.close === 'function') {
                    helpDialog.close();
                }
            });

            window.addEventListener('appinstalled', () => {
                deferredInstallPrompt = null;

                if (installButton) {
                    installButton.hidden = true;
                }

                if (helpDialog?.open && typeof helpDialog.close === 'function') {
                    helpDialog.close();
                }
            });

            if ('serviceWorker' in navigator && isSecureInstallContext) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register(
                        @json(asset('sw.js'))
                    ).catch(error => {
                        console.error(
                            'Service Worker registration failed:',
                            error
                        );
                    });
                });
            }
        })();
    </script>

    @stack('scripts')

</body>

</html>
