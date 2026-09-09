@php
    $growthModules = app(\App\Services\ModuleService::class);
@endphp

@if(
    ($growthModules->isEnabled('crm') && auth()->user()->can('crm.view')) ||
    ($growthModules->isEnabled('loyalty') && auth()->user()->can('loyalty.view')) ||
    ($growthModules->isEnabled('delivery') && auth()->user()->can('delivery.view'))
)
<div class="nav-section">
    <div class="nav-section-title">العملاء والنمو</div>

    @if($growthModules->isEnabled('crm') && auth()->user()->can('crm.view') && Route::has('crm.index'))
        <a href="{{ route('crm.index') }}" class="nav-item {{ request()->routeIs('crm.*') ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a6 6 0 0 1 6-6h2"/><path d="M16 11h6M19 8v6"/></svg>
            <span>CRM والعملاء</span>
        </a>
    @endif

    @if($growthModules->isEnabled('loyalty') && auth()->user()->can('loyalty.view') && Route::has('loyalty.index'))
        <a href="{{ route('loyalty.index') }}" class="nav-item {{ request()->routeIs('loyalty.*') ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3 6 6 .9-4.5 4.4 1.1 6.2L12 16.6 6.4 19.5l1.1-6.2L3 8.9 9 8z"/></svg>
            <span>برنامج الولاء</span>
        </a>
    @endif

    @if($growthModules->isEnabled('delivery') && auth()->user()->can('delivery.view') && Route::has('delivery.tasks.index'))
        <a href="{{ route('delivery.tasks.index') }}" class="nav-item {{ request()->routeIs('delivery.*') ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h11v10H3z"/><path d="M14 9h4l3 3v4h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg>
            <span>التوصيل</span>
        </a>
    @endif
</div>
@endif
