{{-- ضع هذا الرابط داخل قسم "تشغيل المطعم" في Sidebar الرئيسي --}}
@can('restaurant_menu.view')
    <a
        href="{{ route('restaurant.menu.index', ['location_id' => request('location_id')]) }}"
        class="sidebar-link {{ request()->routeIs('restaurant.menu.*') ? 'active' : '' }}"
    >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M4 5h7v14H4zM13 5h7v14h-7z"></path>
            <path d="M7 9h1M16 9h1M7 13h1M16 13h1"></path>
        </svg>
        <span>منيو المطعم</span>
    </a>
@endcan
