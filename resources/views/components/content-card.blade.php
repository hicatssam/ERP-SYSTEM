<article class="menu-card reveal">
    <div class="menu-card-image">
        <img src="{{ asset($image) }}" alt="{{ $imageAlt ?? $name }}" loading="lazy" width="520" height="390">
        @if(!empty($badge))
            <span class="card-badge">{{ $badge }}</span>
        @endif
        <button class="favorite-button" type="button" aria-label="Save {{ $name }} to favorites" aria-pressed="false">
            @include('partials.icons', ['name' => 'heart'])
        </button>
    </div>
    <div class="menu-card-body">
        <div>
            <h3>{{ $name }}</h3>
            <p>{{ $description }}</p>
        </div>
        <div class="menu-card-footer">
            <strong>${{ $price }}</strong>
            <button class="add-button" type="button" aria-label="Add {{ $name }} to order">+</button>
        </div>
    </div>
</article>