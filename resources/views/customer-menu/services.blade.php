@extends('layouts.app', ['title' => 'The menu', 'metaDescription' => 'Browse the full Crisp & Co menu of fresh comfort food.'])

@section('content')
    @include('components.page-hero', ['eyebrow' => 'Pick your pleasure', 'heading' => "The menu,\nmade to share.", 'intro' => 'From first bite to last dip, find something for every kind of craving.'])
    <section class="container menu-page">
        <div class="menu-toolbar">
            <div class="category-tabs" role="tablist" aria-label="Menu categories">
                <button class="is-active" type="button" role="tab" aria-selected="true">All</button>
                <button type="button" role="tab" aria-selected="false">Mains</button>
                <button type="button" role="tab" aria-selected="false">Sides</button>
                <button type="button" role="tab" aria-selected="false">Sweet</button>
            </div>
            <button class="sort-button" type="button">Most loved @include('partials.icons', ['name' => 'chevron'])</button>
        </div>
        <div class="menu-grid menu-grid--wide">
            @include('components.content-card', ['name' => 'Crispy share box', 'description' => '8 pieces · 2 large sides · 2 dips', 'price' => '24.99', 'badge' => 'Bestseller', 'image' => 'images/ui/bucket.svg'])
            @include('components.content-card', ['name' => 'Stacked crunch burger', 'description' => 'Golden fillet · house slaw · fries', 'price' => '12.49', 'badge' => 'Popular', 'image' => 'images/ui/burger.svg'])
            @include('components.content-card', ['name' => 'Hot & crispy box', 'description' => '5 pieces · signature fries · dip', 'price' => '16.99', 'badge' => 'Save 15%', 'image' => 'images/ui/bucket.svg'])
            @include('components.content-card', ['name' => 'Golden side stack', 'description' => 'Crispy bites · smoky dip', 'price' => '6.49', 'badge' => 'New', 'image' => 'images/ui/fries.svg'])
            @include('components.content-card', ['name' => 'Cloudy vanilla shake', 'description' => 'Vanilla cream · caramel crumb', 'price' => '5.99', 'badge' => null, 'image' => 'images/ui/drink.svg'])
            @include('components.content-card', ['name' => 'Little sweet thing', 'description' => 'Warm cinnamon bites · glaze', 'price' => '4.99', 'badge' => null, 'image' => 'images/ui/dessert.svg'])
        </div>
    </section>
@endsection