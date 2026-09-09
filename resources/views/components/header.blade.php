<header class="site-header" data-header>
    <div class="container header-inner">
        <button class="icon-button menu-trigger" type="button" data-menu-open aria-controls="mobile-drawer" aria-expanded="false" aria-label="Open navigation menu">
            @include('partials.icons', ['name' => 'menu'])
        </button>

        <a class="brand" href="{{ route('home') }}" aria-label="Crisp & Co home">
            <span class="brand-mark">C</span>
            <span class="brand-copy">
                <strong>CRISP<span>&amp;</span>CO</strong>
                <small>good food, good mood</small>
            </span>
        </a>

        <nav class="desktop-nav" aria-label="Primary navigation">
            <a class="{{ request()->routeIs('home') ? 'is-active' : '' }}" href="{{ route('home') }}">Home</a>
            <a class="{{ request()->routeIs('services') ? 'is-active' : '' }}" href="{{ route('services') }}">Menu</a>
            <a class="{{ request()->routeIs('about') ? 'is-active' : '' }}" href="{{ route('about') }}">Our story</a>
            <a class="{{ request()->routeIs('contact') ? 'is-active' : '' }}" href="{{ route('contact') }}">Contact</a>
        </nav>

        <div class="header-actions">
            <button class="icon-button notification-button" type="button" aria-label="Notifications">
                @include('partials.icons', ['name' => 'bell'])
                <span class="notification-dot">3</span>
            </button>
            <a class="icon-button bag-button" href="{{ route('details') }}" aria-label="View your order">
                @include('partials.icons', ['name' => 'bag'])
                <span class="notification-dot notification-dot--dark">2</span>
            </a>
        </div>
    </div>
</header>