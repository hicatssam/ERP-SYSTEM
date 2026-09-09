@can('settings.manage')
    @if(\Illuminate\Support\Facades\Route::has('release-center.index'))
        <a
            href="{{ route('release-center.index') }}"
            class="nav-item {{ request()->routeIs('release-center.*') ? 'active' : '' }}"
        >
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 7h-9"/>
                <path d="M14 17H5"/>
                <circle cx="17" cy="17" r="3"/>
                <circle cx="7" cy="7" r="3"/>
            </svg>
            <span>مركز الجاهزية والتسليم</span>
        </a>
    @endif
@endcan
