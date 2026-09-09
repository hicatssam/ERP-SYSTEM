@can('inventory.expiry.view')
    <a
        href="{{ route('inventory.expiry.index') }}"
        class="nav-item {{ request()->routeIs('inventory.expiry.*') ? 'active' : '' }}"
    >
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2v10"/>
            <path d="M12 18v.01"/>
            <path d="M10.3 3.6 2.6 17a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/>
        </svg>
        <span>صلاحية المخزون</span>
    </a>
@endcan
