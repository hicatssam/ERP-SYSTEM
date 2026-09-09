<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <a class="brand" href="{{ route('home') }}">
                <span class="brand-mark">C</span>
                <span class="brand-copy"><strong>CRISP<span>&amp;</span>CO</strong><small>good food, good mood</small></span>
            </a>
            <p>Freshly made comfort food for the moments that matter.</p>
        </div>
        <div>
            <p class="footer-heading">Explore</p>
            <a href="{{ route('services') }}">Browse menu</a>
            <a href="{{ route('about') }}">Our story</a>
            <a href="{{ route('contact') }}">Contact</a>
        </div>
        <div>
            <p class="footer-heading">Say hello</p>
            <a href="mailto:hello@crispandco.test">hello@crispandco.test</a>
            <span>Open daily · 11am–11pm</span>
        </div>
    </div>
    <div class="container footer-bottom">
        <span>© {{ date('Y') }} Crisp &amp; Co. Made for good days.</span>
        <span>Built with care, served with crunch.</span>
    </div>
</footer>