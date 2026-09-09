@can('payroll.view')
    @if(\Illuminate\Support\Facades\Route::has('payroll.index'))
        <a
            href="{{ route('payroll.index') }}"
            class="nav-item {{ request()->routeIs('payroll.*') ? 'active' : '' }}"
        >
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="5" width="18" height="14" rx="2"/>
                <path d="M7 9h10M7 13h4M15 13h2"/>
            </svg>
            <span>الرواتب وكشوف الموظفين</span>
        </a>
    @endif
@endcan
