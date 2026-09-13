@extends('layouts.app', ['title' => 'Contact us', 'metaDescription' => 'Reach the Crisp & Co team.'])

@section('content')
    @include('components.page-hero', ['eyebrow' => 'We are all ears', 'heading' => "Let’s talk\nabout food.", 'intro' => 'Questions, kind words, big ideas — send them our way.'])
    <section class="container contact-layout">
        <div class="contact-details">
            <p class="eyebrow">Find us here</p>
            <h2>Come say hello.</h2>
            <p>We are open every day, with fresh favourites coming out of the kitchen from 11am until late.</p>
            <div class="contact-list">
                <a href="mailto:hello@crispandco.test"><span>Email</span><strong>hello@crispandco.test</strong></a>
                <a href="tel:+15550148"><span>Phone</span><strong>+1 (555) 0148</strong></a>
                <span><span>Find a counter</span><strong>42 Market Lane, Downtown</strong></span>
            </div>
        </div>
        <form class="contact-form" action="#" method="post" data-contact-form>
            <label for="name">Your name</label>
            <input id="name" name="name" type="text" autocomplete="name" placeholder="How should we call you?" required>
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" autocomplete="email" placeholder="you@example.com" required>
            <label for="message">Your message</label>
            <textarea id="message" name="message" rows="5" placeholder="Tell us what is on your mind..." required></textarea>
            <button class="button button--dark" type="submit">Send a note @include('partials.icons', ['name' => 'arrow'])</button>
            <p class="form-status" data-form-status role="status"></p>
        </form>
    </section>
@endsection