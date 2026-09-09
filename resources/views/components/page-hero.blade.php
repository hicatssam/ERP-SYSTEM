<section class="page-hero">
    <div class="container page-hero-inner">
        <div>
            <p class="eyebrow">{{ $eyebrow ?? 'Crisp & Co' }}</p>
            <h1>{{ $heading }}</h1>
            @if(!empty($intro))
                <p class="lead">{{ $intro }}</p>
            @endif
        </div>
        <div class="hero-sticker" aria-hidden="true">
            @include('partials.icons', ['name' => 'spark'])
            <span>made<br>fresh</span>
        </div>
    </div>
</section>