@extends('layouts.app', ['title' => 'Your order', 'metaDescription' => 'Review your Crisp & Co order.'])

@section('content')
    @include('components.page-hero', ['eyebrow' => 'Almost yours', 'heading' => 'Your order.', 'intro' => 'A few delicious decisions away from the good part.'])
    <section class="container order-layout">
        <div class="order-list">
            <div class="order-list-head"><h2>2 items</h2><button class="text-link" type="button">Clear all</button></div>
            <article class="order-item">
                <img src="{{ asset('images/ui/bucket.svg') }}" alt="" width="120" height="100">
                <div class="order-item-copy"><h3>Crispy share box</h3><p>8 pieces · 2 sides · herb dip</p><strong>$24.99</strong></div>
                <div class="quantity-control" aria-label="Quantity for Crispy share box"><button type="button" aria-label="Decrease quantity">−</button><span>1</span><button type="button" aria-label="Increase quantity">+</button></div>
            </article>
            <article class="order-item">
                <img src="{{ asset('images/ui/drink.svg') }}" alt="" width="120" height="100">
                <div class="order-item-copy"><h3>Cloudy vanilla shake</h3><p>Vanilla cream · caramel crumb</p><strong>$5.99</strong></div>
                <div class="quantity-control" aria-label="Quantity for Cloudy vanilla shake"><button type="button" aria-label="Decrease quantity">−</button><span>1</span><button type="button" aria-label="Increase quantity">+</button></div>
            </article>
            <a class="continue-link" href="{{ route('services') }}">← Keep browsing</a>
        </div>
        <aside class="checkout-card">
            <p class="eyebrow">Order summary</p>
            <div class="summary-row"><span>Subtotal</span><strong>$30.98</strong></div>
            <div class="summary-row"><span>Delivery</span><strong class="summary-free">Free</strong></div>
            <div class="summary-row"><span>Service fee</span><strong>$1.50</strong></div>
            <div class="summary-total"><span>Total</span><strong>$32.48</strong></div>
            <button class="button button--dark button--full" type="button">Continue to checkout @include('partials.icons', ['name' => 'arrow'])</button>
            <p class="checkout-note">You can add a note or choose a delivery time next.</p>
        </aside>
    </section>
@endsection