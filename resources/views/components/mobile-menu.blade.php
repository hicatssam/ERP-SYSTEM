<div class="drawer-backdrop" data-menu-close></div>
<aside class="mobile-drawer" id="mobile-drawer" aria-label="Mobile navigation" aria-hidden="true">
    <div class="drawer-top">
        <span class="eyebrow">Explore</span>
        <button class="icon-button" type="button" data-menu-close aria-label="Close navigation menu">
            @include('partials.icons', ['name' => 'close'])
        </button>
    </div>
    <div class="drawer-brand">
        <span class="brand-mark">C</span>
        <div><strong>CRISP<span>&amp;</span>CO</strong><small>good food, good mood</small></div>
    </div>
    <nav class="drawer-nav" aria-label="Mobile primary navigation">
        <a href="{{ route('home') }}"><span>Home</span>@include('partials.icons', ['name' => 'arrow'])</a>
        <a href="{{ route('services') }}"><span>Browse the menu</span>@include('partials.icons', ['name' => 'arrow'])</a>
        <a href="{{ route('about') }}"><span>Our story</span>@include('partials.icons', ['name' => 'arrow'])</a>
        <a href="{{ route('contact') }}"><span>Get in touch</span>@include('partials.icons', ['name' => 'arrow'])</a>
    </nav>
    <div class="drawer-note">
        @include('partials.icons', ['name' => 'spark'])
        <p>Made fresh daily, with a little extra crunch.</p>
    </div>
</aside>