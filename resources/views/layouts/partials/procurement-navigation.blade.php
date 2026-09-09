@php
    $moduleService = app(\App\Services\ModuleService::class);
    $purchasingModuleEnabled = $moduleService->isEnabled('purchasing');
    $suppliersModuleEnabled = $moduleService->isEnabled('suppliers');
    $procurementRoutesAvailable = \Illuminate\Support\Facades\Route::has('procurement.dashboard');
@endphp

@if (($purchasingModuleEnabled || $suppliersModuleEnabled) && $procurementRoutesAvailable)
    @canany([
        'dashboard.procurement',
        'suppliers.view',
        'purchase_orders.view',
        'goods_receipts.view',
        'purchase_returns.view',
        'supplier_invoices.view',
        'supplier_payments.view',
        'procurement.reports.view',
        'procurement.exchange_rates.view',
    ])
        <div class="nav-section">
            <div class="nav-section-title">المشتريات والموردون</div>

            @if($purchasingModuleEnabled)
            @can('dashboard.procurement')
                <a href="{{ route('procurement.dashboard') }}"
                    class="nav-item {{ request()->routeIs('procurement.dashboard') ? 'active' : '' }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7" rx="1" />
                        <rect x="14" y="3" width="7" height="7" rx="1" />
                        <rect x="3" y="14" width="7" height="7" rx="1" />
                        <rect x="14" y="14" width="7" height="7" rx="1" />
                    </svg>
                    <span>لوحة المشتريات</span>
                </a>
            @endcan
            @endif

            @if($suppliersModuleEnabled)
            @can('suppliers.view')
                <a href="{{ route('suppliers.index') }}"
                    class="nav-item {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M19 8v6M22 11h-6" />
                    </svg>
                    <span>الموردون</span>
                </a>
            @endcan
            @endif

            @if($purchasingModuleEnabled)
            @can('purchase_orders.view')
                <a href="{{ route('purchase-orders.index') }}"
                    class="nav-item {{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 2h9l4 4v16H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z" />
                        <path d="M14 2v5h5" />
                        <line x1="8" y1="12" x2="16" y2="12" />
                        <line x1="8" y1="16" x2="14" y2="16" />
                    </svg>
                    <span>أوامر الشراء</span>
                </a>
            @endcan
            @endif

            @if($purchasingModuleEnabled)
            @can('goods_receipts.view')
                <a href="{{ route('goods-receipts.index') }}"
                    class="nav-item {{ request()->routeIs('goods-receipts.*') ? 'active' : '' }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 7l9-4 9 4-9 4-9-4z" />
                        <path d="M3 7v10l9 4 9-4V7" />
                        <path d="M12 11v10" />
                    </svg>
                    <span>سندات استلام المشتريات</span>
                </a>
            @endcan
            @endif

            @if($purchasingModuleEnabled)
            @can('purchase_returns.view')
                <a href="{{ route('purchase-returns.index') }}"
                    class="nav-item {{ request()->routeIs('purchase-returns.*') ? 'active' : '' }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 14 4 9 9 4" />
                        <path d="M20 20v-3a8 8 0 0 0-8-8H4" />
                    </svg>
                    <span>مرتجعات الموردين</span>
                </a>
            @endcan
            @endif

            @if($purchasingModuleEnabled)
            @can('supplier_invoices.view')
                <a href="{{ route('supplier-invoices.index') }}"
                    class="nav-item {{ request()->routeIs('supplier-invoices.*') ? 'active' : '' }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M7 3h10a2 2 0 0 1 2 2v16l-3-2-4 2-4-2-3 2V5a2 2 0 0 1 2-2z" />
                        <line x1="9" y1="8" x2="15" y2="8" />
                        <line x1="9" y1="12" x2="15" y2="12" />
                    </svg>
                    <span>فواتير الموردين</span>
                </a>
            @endcan
            @endif

            @if($purchasingModuleEnabled)
            @can('supplier_payments.view')
                <a href="{{ route('supplier-payments.index') }}"
                    class="nav-item {{ request()->routeIs('supplier-payments.*') ? 'active' : '' }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="5" width="20" height="14" rx="2" />
                        <path d="M2 10h20" />
                        <path d="M7 15h3" />
                    </svg>
                    <span>دفعات الموردين</span>
                </a>
            @endcan
            @endif

            @if($purchasingModuleEnabled)
            @can('procurement.reports.view')
                <a href="{{ route('procurement.reports.index') }}"
                    class="nav-item {{ request()->routeIs('procurement.reports.*') ? 'active' : '' }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 19V5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14" />
                        <line x1="8" y1="17" x2="8" y2="11" />
                        <line x1="12" y1="17" x2="12" y2="7" />
                        <line x1="16" y1="17" x2="16" y2="13" />
                    </svg>
                    <span>تقارير المشتريات</span>
                </a>
            @endcan
            @endif

           
        </div>
    @endcanany
@endif