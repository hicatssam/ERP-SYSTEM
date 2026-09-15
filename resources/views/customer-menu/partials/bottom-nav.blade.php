@php
    $activeNav = $activeNav ?? 'home';
@endphp
<nav class="bottom-nav" aria-label="التنقل">
 <a class="nav-btn {{ $activeNav === 'home' ? 'active' : '' }}" href="{{ route('customer-menu.show', $location->code) }}"><i class="fa-solid fa-house"></i><span>الرئيسية</span></a>
 <a class="nav-btn {{ $activeNav === 'menu' ? 'active' : '' }}" href="{{ route('customer-menu.products', $location->code) }}"><i class="fa-solid fa-utensils"></i><span>المنيو</span></a>
 <a class="nav-btn {{ $activeNav === 'cart' ? 'active' : '' }}" href="{{ route('customer-menu.cart', $location->code) }}"><i class="fa-solid fa-bag-shopping"></i><span>السلة</span><b class="nav-badge" id="cartBadgeNav">0</b></a>
 <a class="nav-btn {{ $activeNav === 'favorites' ? 'active' : '' }}" href="{{ route('customer-menu.favorites', $location->code) }}"><i class="fa-regular fa-heart"></i><span>المفضلة</span><b class="nav-badge" id="favBadgeNav">0</b></a>
 <a class="nav-btn {{ $activeNav === 'orders' ? 'active' : '' }}" href="{{ route('customer-menu.my-orders', $location->code) }}"><i class="fa-solid fa-receipt"></i><span>طلباتي</span></a>
</nav>
@if($activeNav === 'home')
 @include('customer-menu.partials.home-enhancements')
@endif
