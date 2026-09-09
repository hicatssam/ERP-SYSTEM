@php
    $customerDisplayKitchenEnabled =
        app(\App\Services\ModuleService::class)
            ->isEnabled('kitchen');
@endphp

@if($customerDisplayKitchenEnabled)
    @can('customer_display.view')
        @if(\Illuminate\Support\Facades\Route::has('restaurant.customer-display.index'))
            <a
                href="{{ route('restaurant.customer-display.index') }}"
                class="nav-item {{
                    request()->routeIs('restaurant.customer-display.*')
                        ? 'active'
                        : ''
                }}"
                target="_blank"
                rel="noopener"
            >
                <svg
                    class="nav-icon"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <rect
                        x="3"
                        y="4"
                        width="18"
                        height="13"
                        rx="2"
                    />
                    <path d="M8 21h8M12 17v4"/>
                    <path d="M7 9h3M14 9h3M7 13h10"/>
                </svg>

                <span>شاشة طلبات العملاء</span>
            </a>
        @endif
    @endcan
@endif
