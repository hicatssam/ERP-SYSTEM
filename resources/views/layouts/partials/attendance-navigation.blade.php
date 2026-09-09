@php
    $attendanceFeatures = app(
        \App\Services\AttendanceFeatureService::class
    );
@endphp

@if($attendanceFeatures->attendanceEnabled())
    @can('attendance.view')
        @if(\Illuminate\Support\Facades\Route::has('attendance.index'))
            <a
                href="{{ route('attendance.index') }}"
                class="nav-item {{ request()->routeIs('attendance.*') ? 'active' : '' }}"
            >
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M12 7v5l3 2"/>
                </svg>
                <span>الحضور والدوام</span>
            </a>
        @endif
    @endcan
@endif
