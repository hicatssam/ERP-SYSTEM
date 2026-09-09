@extends('layouts.app', ['title' => 'Fresh comfort food', 'metaDescription' => 'Crisp & Co brings fresh, crunchy comfort food to your door.'])

@section('content')
    <section class="home-shell">
        <div class="container home-topline">
            <div class="welcome-copy">
                <p class="eyebrow">Wednesday is for treating yourself <span aria-hidden="true">✦</span></p>
                <h1>Good food.<br><em>Good mood.</em></h1>
                <p>Freshly made favourites, packed with flavour and ready when you are.</p>
            </div>
            <div class="delivery-pill">
                <span class="status-dot"></span>
                <span><strong>Delivering now</strong><small>25–35 min · free over $25</small></span>
                @include('partials.icons', ['name' => 'chevron'])
            </div>
        </div>

        <div class="container home-controls">
            <label class="search-field">
                @include('partials.icons', ['name' => 'search'])
                <span class="sr-only">Search the menu</span>
                <input type="search" placeholder="Search your favourite..." data-menu-search>
            </label>
            <button class="filter-button" type="button" aria-label="Filter menu items" aria-expanded="false" data-filter-toggle>
                @include('partials.icons', ['name' => 'filter'])
            </button>
        </div>

        <div class="container promo-wrap">
            <section class="promo-banner reveal">
                <div class="promo-content">
                    <p class="eyebrow">Limited-time drop</p>
                    <h2>The crunch<br>you crave.</h2>
                    <p>Golden on the outside. Juicy in the middle. Made for sharing.</p>
                    <a class="button button--light" href="{{ route('services') }}">Order now @include('partials.icons', ['name' => 'arrow'])</a>
                </div>
                <div class="promo-art promo-art--hero" aria-hidden="true">
                    <span class="art-piece art-piece--one"></span>
                    <span class="art-piece art-piece--two"></span>
                    <span class="art-piece art-piece--three"></span>
                    <span class="art-cup">C<span>&amp;</span>C</span>
                    <span class="art-fries"><i></i><i></i><i></i><i></i><b></b></span>
                </div>
            </section>
        </div>

        <div class="container category-row" aria-label="Menu categories">
            @foreach([
                ['label' => 'All', 'class' => 'category-art--all'],
                ['label' => 'Buckets', 'class' => 'category-art--bucket'],
                ['label' => 'Burgers', 'class' => 'category-art--burger'],
                ['label' => 'Snacks', 'class' => 'category-art--snack'],
                ['label' => 'Sides', 'class' => 'category-art--sides'],
                ['label' => 'Drinks', 'class' => 'category-art--drink'],
                ['label' => 'Sweet', 'class' => 'category-art--sweet'],
            ] as $category)
                <button class="category-item {{ $loop->first ? 'is-selected' : '' }}" type="button" data-category="{{ $category['label'] }}">
                    <span class="category-art {{ $category['class'] }}" aria-hidden="true"></span>
                    <span>{{ $category['label'] }}</span>
                </button>
            @endforeach
        </div>

        <section class="container section-block menu-section" data-menu>
            <div class="section-heading">
                <div><p class="eyebrow">The crowd favourites</p><h2>Popular picks</h2></div>
                <a class="text-link" href="{{ route('services') }}">View full menu @include('partials.icons', ['name' => 'arrow'])</a>
            </div>
            <div class="menu-grid">
                @include('components.content-card', ['name' => 'Crispy share box', 'description' => '8 pieces · 2 large sides · 2 dips', 'price' => '24.99', 'badge' => 'Bestseller', 'image' => 'images/ui/bucket.svg'])
                @include('components.content-card', ['name' => 'Stacked crunch burger', 'description' => 'Golden fillet · house slaw · fries', 'price' => '12.49', 'badge' => 'Popular', 'image' => 'images/ui/burger.svg'])
                @include('components.content-card', ['name' => 'Hot & crispy box', 'description' => '5 pieces · signature fries · dip', 'price' => '16.99', 'badge' => 'Save 15%', 'image' => 'images/ui/bucket.svg'])
            </div>
        </section>

        <section class="container offer-banner reveal">
            <div>
                <p class="eyebrow">A little extra</p>
                <h2>Make it a meal.</h2>
                <p>Add a side and a drink to any favourite for less.</p>
                <a class="text-link" href="{{ route('services') }}">See the combos @include('partials.icons', ['name' => 'arrow'])</a>
            </div>
            <div class="offer-art" aria-hidden="true"><span class="offer-burger"></span><span class="offer-fries"></span><b>20%<small>OFF</small></b></div>
        </section>
    </section>
@endsection