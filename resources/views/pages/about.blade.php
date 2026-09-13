@extends('layouts.app', ['title' => 'Our story', 'metaDescription' => 'Meet the people and principles behind Crisp & Co.'])

@section('content')
    @include('components.page-hero', ['eyebrow' => 'A little about us', 'heading' => "Food with a\nfeel-good side.", 'intro' => 'We started Crisp & Co with a simple idea: quick food should still feel thoughtful.'])
    <section class="container story-layout">
        <div class="story-art">
            <div class="story-circle">C<span>&amp;</span>C</div>
            <span class="story-sticker">since<br>2018</span>
        </div>
        <div class="story-copy">
            <p class="eyebrow">The short version</p>
            <h2>Big flavour, no big fuss.</h2>
            <p>We believe the best meals are the ones that fit right into your day. That is why every Crisp & Co favourite is made to order, thoughtfully sourced, and finished with the kind of crunch that makes you pause mid-conversation.</p>
            <p>From our first tiny counter to the neighbourhood spots we call home today, the recipe has stayed the same: good ingredients, generous portions, and a team that cares about your next bite.</p>
            <a class="button button--dark" href="{{ route('services') }}">Meet the menu @include('partials.icons', ['name' => 'arrow'])</a>
        </div>
    </section>
    <section class="values-section">
        <div class="container">
            <div class="section-heading"><div><p class="eyebrow">What we stand for</p><h2>The good stuff.</h2></div></div>
            <div class="values-grid">
                <article><span class="value-number">01</span><h3>Made fresh</h3><p>No sitting around. Every order gets the attention it deserves.</p></article>
                <article><span class="value-number">02</span><h3>Shared freely</h3><p>Food tastes better when there is enough to pass around.</p></article>
                <article><span class="value-number">03</span><h3>Always human</h3><p>Warm service, honest ingredients, and zero unnecessary fuss.</p></article>
            </div>
        </div>
    </section>
@endsection