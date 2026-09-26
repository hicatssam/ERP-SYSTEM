@extends('layouts.app')

@section('title', 'لوحة التحكم')
@section('page-title', 'لوحة التحكم')

@push('styles')
<style>
    /* =========================================================
       مركز المهام
    ========================================================= */

    .dashboard-tasks-card {
        overflow: hidden;
        margin-bottom: 1.25rem;
    }

    .dashboard-tasks-card .card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .dashboard-tasks-total {
        display: inline-flex;
        align-items: center;
        justify-content: center;

        min-width: 34px;
        height: 34px;
        padding: 0 .55rem;

        border-radius: 999px;

        background: rgba(212, 160, 23, .11);
        color: var(--gold);

        font-size: .8rem;
        font-weight: 900;
    }

    .dashboard-task-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .8rem;
    }

    .dashboard-task-card {
        --task-accent: #d4a017;
        --task-soft: rgba(212, 160, 23, .07);
        --task-border: rgba(212, 160, 23, .22);

        position: relative;

        display: flex;
        flex-direction: column;

        min-height: 165px;

        padding: 1rem;

        text-decoration: none;

        background:
            linear-gradient(
                145deg,
                var(--task-soft),
                #fff 67%
            );

        border: 1px solid var(--task-border);
        border-radius: 14px;

        overflow: hidden;

        transition:
            transform .18s ease,
            box-shadow .18s ease,
            border-color .18s ease;
    }

    .dashboard-task-card::before {
        content: '';

        position: absolute;

        top: 0;
        right: 0;

        width: 4px;
        height: 100%;

        background: var(--task-accent);
    }

    .dashboard-task-card:hover {
        transform: translateY(-3px);

        box-shadow:
            0 10px 24px
            rgba(15, 23, 42, .07);

        border-color: var(--task-accent);
    }

    .task-tone-gold {
        --task-accent: #d4a017;
        --task-soft: rgba(212, 160, 23, .07);
        --task-border: rgba(212, 160, 23, .22);
    }

    .task-tone-blue {
        --task-accent: #2563eb;
        --task-soft: rgba(37, 99, 235, .06);
        --task-border: rgba(37, 99, 235, .18);
    }

    .task-tone-green {
        --task-accent: #16a34a;
        --task-soft: rgba(22, 163, 74, .06);
        --task-border: rgba(22, 163, 74, .18);
    }

    .task-tone-red {
        --task-accent: #dc2626;
        --task-soft: rgba(220, 38, 38, .055);
        --task-border: rgba(220, 38, 38, .18);
    }

    .dashboard-task-head {
        display: flex;
        align-items: center;
        justify-content: space-between;

        gap: .6rem;

        margin-bottom: .8rem;
    }

    .dashboard-task-module {
        color: var(--task-accent);

        font-size: .66rem;
        font-weight: 800;
    }

    .dashboard-task-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;

        min-width: 34px;
        height: 34px;
        padding: 0 .45rem;

        border-radius: 10px;

        background: var(--task-soft);
        border: 1px solid var(--task-border);

        color: var(--task-accent);

        font-size: .85rem;
        font-weight: 900;
    }

    .dashboard-task-title {
        color: var(--text);

        font-size: .88rem;
        font-weight: 900;
        line-height: 1.6;
    }

    .dashboard-task-description {
        margin-top: .3rem;

        color: var(--text-muted);

        font-size: .68rem;
        line-height: 1.7;
    }

    .dashboard-task-action {
        display: flex;
        align-items: center;
        gap: .35rem;

        margin-top: auto;
        padding-top: .8rem;

        color: var(--task-accent);

        font-size: .72rem;
        font-weight: 900;
    }

    @media (max-width: 1200px) {
        .dashboard-task-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 700px) {
        .dashboard-task-grid {
            grid-template-columns: 1fr;
        }
    }


    /* =========================================================
       وحدات التشغيل — Restaurant / Kitchen / Production
    ========================================================= */

    .dashboard-module-section {
        margin-top: 1.1rem;
    }

    .dashboard-module-title-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: .65rem;
    }

    .dashboard-module-title {
        font-size: .92rem;
        font-weight: 900;
        color: var(--text);
    }

    .dashboard-module-subtitle {
        margin-top: .15rem;
        color: var(--text-muted);
        font-size: .68rem;
    }

    .dashboard-module-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .75rem;
    }

    .dashboard-module-card {
        display: block;
        min-width: 0;
        padding: .95rem 1rem;
        text-decoration: none;
        color: var(--text);
        background: var(--surface);
        border: 1px solid var(--border-light);
        border-radius: 13px;
        transition: .18s ease;
    }

    .dashboard-module-card:hover {
        transform: translateY(-2px);
        border-color: rgba(212, 160, 23, .42);
        box-shadow: 0 8px 22px rgba(15, 23, 42, .055);
    }

    .dashboard-module-card-label {
        color: var(--text-muted);
        font-size: .68rem;
        font-weight: 700;
    }

    .dashboard-module-card-value {
        margin-top: .25rem;
        font-size: 1.28rem;
        line-height: 1.2;
        font-weight: 900;
    }

    .dashboard-module-card-action {
        margin-top: .55rem;
        color: var(--gold-deep);
        font-size: .64rem;
        font-weight: 800;
    }

    .dashboard-module-card.is-danger .dashboard-module-card-value {
        color: #dc2626;
    }

    .dashboard-module-card.is-success .dashboard-module-card-value {
        color: #16a34a;
    }

    .dashboard-module-card.is-info .dashboard-module-card-value {
        color: #2563eb;
    }

    @media (max-width: 1100px) {
        .dashboard-module-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 620px) {
        .dashboard-module-grid {
            grid-template-columns: 1fr;
        }
    }

</style>
@endpush


@section('content')

@php
    $dashboardUser = auth()->user();

    $dashboardName =
        $dashboardUser->employee?->full_name
        ?? $dashboardUser->display_name;

    $lowStock =
        $stats['low_stock_count'] ?? 0;

    $products =
        $stats['top_products'] ?? collect();

    $operationalTasks =
        collect($stats['task_center'] ?? []);

    $moduleService = app(\App\Services\ModuleService::class);

    $moduleEnabled =
        static fn (string $code): bool =>
            $moduleService->isEnabled($code);

    $routeExists =
        static fn (string $name): bool =>
            \Illuminate\Support\Facades\Route::has($name);

    $restaurantStats =
        $stats['restaurant']
        ?? [];

    $kitchenStats =
        $stats['kitchen']
        ?? [];

    $productionStats =
        $stats['production']
        ?? [];

    /*
     * خط إنتاج الكيك لا يظهر للـ Dispatcher
     * لمجرد أنه يمتلك cake_orders.view.
     */
    $canSeeCakeProductionPipeline =
        $moduleEnabled('cake_orders')
        && (
        $dashboardUser->isAdmin()
        || $dashboardUser->can('cake_orders.review')
        || $dashboardUser->can('cake_orders.accept')
        || $dashboardUser->can('cake_orders.schedule')
        || $dashboardUser->can('cake_orders.prepare')
        || $dashboardUser->can('cake_orders.decorate')
        || $dashboardUser->can('cake_orders.quality_check')
        );

    /*
     * كرت "كيك في الإنتاج"
     * أيضًا لا علاقة للـ Dispatcher به.
     */
    $canSeeFactoryProductionStat =
        $canSeeCakeProductionPipeline;
@endphp


{{-- =========================================================
     رأس الصفحة
========================================================= --}}

<div class="page-header">

    <div>

        <h1 class="page-heading">
            مرحباً، {{ $dashboardName }} 👋
        </h1>

        <p class="page-subheading">
            {{ now()->translatedFormat('l، d F Y') }}
            —
            نظام إدارة {{ \App\Models\SystemSetting::get('system_name', 'حلويات دهب') }}
        </p>

    </div>

    <span style="font-size:.75rem;color:var(--text-muted)">
        آخر تحديث:
        {{ now()->format('H:i') }}
    </span>

</div>


{{-- =========================================================
     الإجراءات السريعة
========================================================= --}}

<div style="display:flex;flex-wrap:wrap;gap:.625rem;margin-bottom:1.5rem">

    @if($moduleEnabled('sales') && $dashboardUser->can('orders.create'))

        <a
            href="{{ route('orders.create') }}"
            class="btn btn-gold btn-sm"
        >
            طلب جديد
        </a>

    @endif


    @if($moduleEnabled('cake_orders') && $dashboardUser->can('cake_orders.create'))

        <a
            href="{{ route('cake-orders.create') }}"
            class="btn btn-outline btn-sm"
        >
            طلب كيك جديد
        </a>

    @endif


    @if($moduleEnabled('bakery') && $dashboardUser->can('showroom_sweets_requests.create'))

        <a
            href="{{ route('showroom-sweets-requests.create') }}"
            class="btn btn-outline btn-sm"
        >
            طلب حلويات جديد
        </a>

    @endif


    @if($moduleEnabled('finance') && $dashboardUser->can('cash_sessions.manage'))

        <a
            href="{{ route('cash-sessions.index') }}"
            class="btn btn-outline btn-sm"
        >
            جلسة الكاش
        </a>

    @endif


    @if($moduleEnabled('inventory') && $dashboardUser->can('stock_requests.create'))

        <a
            href="{{ route('stock-requests.create') }}"
            class="btn btn-outline btn-sm"
        >
            طلب مخزون
        </a>

    @endif


    @if(
        $moduleEnabled('restaurant')
        && $dashboardUser->can('restaurant.view')
        && $routeExists('restaurant.dashboard')
    )

        <a
            href="{{ route('restaurant.dashboard') }}"
            class="btn btn-outline btn-sm"
        >
            تشغيل المطعم
        </a>

    @endif


    @if(
        $moduleEnabled('restaurant_pos')
        && $dashboardUser->can('restaurant_pos.use')
        && $routeExists('restaurant.pos.index')
    )

        <a
            href="{{ route('restaurant.pos.index') }}"
            class="btn btn-gold btn-sm"
        >
            POS المطعم
        </a>

    @endif


    @if(
        $moduleEnabled('kds')
        && $dashboardUser->can('kds.view')
        && $routeExists('kds.index')
    )

        <a
            href="{{ route('kds.index') }}"
            class="btn btn-outline btn-sm"
        >
            شاشة KDS
        </a>

    @endif


    @if(
        $moduleEnabled('production')
        && $dashboardUser->can('production.view')
        && $routeExists('production.orders.index')
    )

        <a
            href="{{ route('production.orders.index') }}"
            class="btn btn-outline btn-sm"
        >
            أوامر الإنتاج
        </a>

    @endif


    @if(
        $moduleEnabled('quality_control')
        && $dashboardUser->can('quality_control.view')
        && $routeExists('quality-control.index')
    )

        <a
            href="{{ route('quality-control.index') }}"
            class="btn btn-outline btn-sm"
        >
            مراقبة الجودة
        </a>

    @endif


    @if(
        $moduleEnabled('purchasing')
        && $dashboardUser->can('dashboard.procurement')
        && $routeExists('procurement.dashboard')
    )

        <a
            href="{{ route('procurement.dashboard') }}"
            class="btn btn-outline btn-sm"
        >
            لوحة المشتريات
        </a>

    @endif

</div>


{{-- =========================================================
     مهامي الحالية
========================================================= --}}

@if($operationalTasks->isNotEmpty())

    <div class="card dashboard-tasks-card">

        <div class="card-header">

            <div>

                <span class="card-title">
                    مهامي الحالية
                </span>

                <div style="font-size:.7rem;color:var(--text-muted);margin-top:.2rem">
                    العمليات التي تحتاج إجراء منك الآن حسب صلاحياتك وموقعك
                </div>

            </div>


            <span class="dashboard-tasks-total">
                {{ $operationalTasks->sum('count') }}
            </span>

        </div>


        <div class="card-body">

            <div class="dashboard-task-grid">

                @foreach($operationalTasks as $task)

                    <a
                        href="{{ $task['url'] }}"
                        class="dashboard-task-card task-tone-{{ $task['tone'] ?? 'gold' }}"
                    >

                        <div class="dashboard-task-head">

                            <span class="dashboard-task-module">

                                @if(($task['type'] ?? '') === 'cake')

                                    طلبات الكيك

                                @elseif(($task['type'] ?? '') === 'sweets')

                                    طلبات حلويات الفروع

                                @else

                                    مهمة تشغيلية

                                @endif

                            </span>


                            <span class="dashboard-task-count">
                                {{ $task['count'] ?? 0 }}
                            </span>

                        </div>


                        <div class="dashboard-task-title">
                            {{ $task['title'] }}
                        </div>


                        <div class="dashboard-task-description">
                            {{ $task['description'] }}
                        </div>


                        <div class="dashboard-task-action">

                            {{ $task['action'] }}

                            <span>
                                ←
                            </span>

                        </div>

                    </a>

                @endforeach

            </div>

        </div>

    </div>

@endif


{{-- =========================================================
     إحصائيات الطلبات والمالية
========================================================= --}}

@canany([
    'orders.view',
    'financial.dashboard.view',
    'payments.record'
])

    <div class="stats-grid">

        @can('orders.view')

            <div class="stat-card stat-gold">

                <div class="stat-info">

                    <div class="stat-value">
                        {{ $stats['orders_today'] ?? 0 }}
                    </div>

                    <div class="stat-label">
                        طلبات اليوم
                    </div>

                </div>

            </div>

        @endcan


        @can('financial.dashboard.view')

            <div class="stat-card stat-green">

                <div class="stat-info">

                    <div class="stat-value">
                        ₪ {{ number_format($stats['sales_today'] ?? 0, 0) }}
                    </div>

                    <div class="stat-label">
                        إيراد اليوم
                    </div>

                </div>

            </div>


            <div class="stat-card stat-green">

                <div class="stat-info">

                    <div class="stat-value">
                        ₪ {{ number_format($stats['net_sales_month'] ?? 0, 0) }}
                    </div>

                    <div class="stat-label">
                        مبيعات الشهر
                    </div>

                </div>

            </div>

        @endcan


        @can('payments.record')

            <div class="stat-card stat-blue">

                <div class="stat-info">

                    <div class="stat-value">
                        ₪ {{ number_format($stats['collections_month'] ?? 0, 0) }}
                    </div>

                    <div class="stat-label">
                        تحصيلات الشهر
                    </div>

                </div>

            </div>

        @endcan

    </div>

@endcanany


{{-- =========================================================
     الإحصائيات الإضافية
========================================================= --}}

@if(
    $dashboardUser->can('financial.dashboard.view')
    || $dashboardUser->can('cash_sessions.manage')
    || $canSeeFactoryProductionStat
    || $dashboardUser->can('inventory.view')
)

    <div
        class="stats-grid"
        style="margin-top:.75rem"
    >

        @can('financial.dashboard.view')

            <div class="stat-card stat-green">

                <div class="stat-info">

                    <div class="stat-value">
                        ₪ {{ number_format($stats['net_sales_after_refunds'] ?? 0, 0) }}
                    </div>

                    <div class="stat-label">
                        صافي المبيعات بعد المرتجعات
                    </div>

                </div>

            </div>

        @endcan


        @can('cash_sessions.manage')

            <div class="stat-card stat-gold">

                <div class="stat-info">

                    <div class="stat-value">
                        ₪ {{ number_format($stats['cash_balance'] ?? 0, 0) }}
                    </div>

                    <div class="stat-label">
                        الرصيد النقدي المفتوح
                    </div>

                </div>

            </div>

        @endcan


        @if($canSeeFactoryProductionStat)

            <div class="stat-card stat-blue">

                <div class="stat-info">

                    <div class="stat-value">
                        {{ $stats['factory_production'] ?? 0 }}
                    </div>

                    <div class="stat-label">
                        كيك في الإنتاج
                    </div>

                </div>

            </div>

        @endif


        @can('inventory.view')

            <div class="stat-card {{ $lowStock > 0 ? 'stat-orange' : 'stat-green' }}">

                <div class="stat-info">

                    <div class="stat-value">
                        {{ $lowStock }}
                    </div>

                    <div class="stat-label">
                        منتجات نقص مخزون
                    </div>

                </div>

            </div>

        @endcan

    </div>

@endif



{{-- =========================================================
     تشغيل المطعم والمطبخ
========================================================= --}}

@if(
    (
        $moduleEnabled('restaurant')
        && $dashboardUser->can('restaurant.view')
    )
    || (
        $moduleEnabled('kitchen')
        && $dashboardUser->can('kitchen.view')
    )
)

    <div class="dashboard-module-section">

        <div class="dashboard-module-title-row">

            <div>
                <div class="dashboard-module-title">
                    تشغيل المطعم والمطبخ
                </div>

                <div class="dashboard-module-subtitle">
                    حالة الطلبات والطاولات وتذاكر التحضير حسب صلاحياتك ونطاق موقعك
                </div>
            </div>

            @if(
                $moduleEnabled('restaurant')
                && $dashboardUser->can('restaurant.view')
                && $routeExists('restaurant.dashboard')
            )
                <a
                    href="{{ route('restaurant.dashboard') }}"
                    class="card-action"
                >
                    لوحة المطعم
                </a>
            @endif

        </div>


        <div class="dashboard-module-grid">

            @if(
                $moduleEnabled('restaurant')
                && $dashboardUser->can('restaurant.view')
            )

                <a
                    href="{{ $routeExists('restaurant.dashboard') ? route('restaurant.dashboard') : '#' }}"
                    class="dashboard-module-card"
                >
                    <div class="dashboard-module-card-label">
                        طلبات المطعم اليوم
                    </div>

                    <div class="dashboard-module-card-value">
                        {{ $restaurantStats['orders_today'] ?? 0 }}
                    </div>

                    <div class="dashboard-module-card-action">
                        عرض تشغيل المطعم
                    </div>
                </a>


                <a
                    href="{{ $routeExists('restaurant.dashboard') ? route('restaurant.dashboard') : '#' }}"
                    class="dashboard-module-card is-info"
                >
                    <div class="dashboard-module-card-label">
                        الطلبات المفتوحة
                    </div>

                    <div class="dashboard-module-card-value">
                        {{ $restaurantStats['open_orders'] ?? 0 }}
                    </div>

                    <div class="dashboard-module-card-action">
                        متابعة الطلبات
                    </div>
                </a>

            @endif


            @if(
                $moduleEnabled('restaurant_tables')
                && $dashboardUser->can('restaurant_tables.view')
            )

                <a
                    href="{{ $routeExists('restaurant.tables.index') ? route('restaurant.tables.index') : '#' }}"
                    class="dashboard-module-card"
                >
                    <div class="dashboard-module-card-label">
                        الطاولات المشغولة
                    </div>

                    <div class="dashboard-module-card-value">
                        {{ $restaurantStats['tables_occupied'] ?? 0 }}
                        /
                        {{ $restaurantStats['tables_total'] ?? 0 }}
                    </div>

                    <div class="dashboard-module-card-action">
                        إدارة الطاولات
                    </div>
                </a>

            @endif


            @if(
                $moduleEnabled('kitchen')
                && $dashboardUser->can('kitchen.view')
            )

                <a
                    href="{{ $routeExists('kitchen.tickets.index') ? route('kitchen.tickets.index', ['status' => 'queued']) : '#' }}"
                    class="dashboard-module-card"
                >
                    <div class="dashboard-module-card-label">
                        بانتظار التحضير
                    </div>

                    <div class="dashboard-module-card-value">
                        {{ $kitchenStats['queued'] ?? 0 }}
                    </div>

                    <div class="dashboard-module-card-action">
                        فتح تذاكر المطبخ
                    </div>
                </a>


                <a
                    href="{{ $routeExists('kitchen.tickets.index') ? route('kitchen.tickets.index', ['status' => 'preparing']) : '#' }}"
                    class="dashboard-module-card is-info"
                >
                    <div class="dashboard-module-card-label">
                        قيد التحضير
                    </div>

                    <div class="dashboard-module-card-value">
                        {{ $kitchenStats['preparing'] ?? 0 }}
                    </div>

                    <div class="dashboard-module-card-action">
                        متابعة المطبخ
                    </div>
                </a>


                <a
                    href="{{ $routeExists('kitchen.tickets.index') ? route('kitchen.tickets.index', ['status' => 'ready']) : '#' }}"
                    class="dashboard-module-card is-success"
                >
                    <div class="dashboard-module-card-label">
                        جاهز للتسليم
                    </div>

                    <div class="dashboard-module-card-value">
                        {{ $kitchenStats['ready'] ?? 0 }}
                    </div>

                    <div class="dashboard-module-card-action">
                        متابعة التسليم
                    </div>
                </a>


                @if(($kitchenStats['urgent'] ?? 0) > 0)

                    <a
                        href="{{ $routeExists('kds.index') ? route('kds.index') : ($routeExists('kitchen.tickets.index') ? route('kitchen.tickets.index') : '#') }}"
                        class="dashboard-module-card is-danger"
                    >
                        <div class="dashboard-module-card-label">
                            تذاكر عاجلة
                        </div>

                        <div class="dashboard-module-card-value">
                            {{ $kitchenStats['urgent'] ?? 0 }}
                        </div>

                        <div class="dashboard-module-card-action">
                            فتح شاشة التشغيل
                        </div>
                    </a>

                @endif

            @endif

        </div>

    </div>

@endif


{{-- =========================================================
     الإنتاج والوصفات والجودة — Sprint 06
========================================================= --}}

@if(
    (
        $moduleEnabled('production')
        && $dashboardUser->can('production.view')
    )
    || (
        $moduleEnabled('recipes')
        && $dashboardUser->can('recipes.view')
    )
    || (
        $moduleEnabled('quality_control')
        && $dashboardUser->can('quality_control.view')
    )
)

    <div class="dashboard-module-section">

        <div class="dashboard-module-title-row">

            <div>
                <div class="dashboard-module-title">
                    الإنتاج والوصفات والجودة
                </div>

                <div class="dashboard-module-subtitle">
                    مؤشرات التشغيل الفعلية للوصفات وأوامر الإنتاج وبوابة الجودة
                </div>
            </div>

            @if(
                $moduleEnabled('production')
                && $dashboardUser->can('production.view')
                && $routeExists('production.orders.index')
            )
                <a
                    href="{{ route('production.orders.index') }}"
                    class="card-action"
                >
                    أوامر الإنتاج
                </a>
            @endif

        </div>


        <div class="dashboard-module-grid">

            @if(
                $moduleEnabled('recipes')
                && $dashboardUser->can('recipes.view')
            )

                <a
                    href="{{ $routeExists('production.recipes.index') ? route('production.recipes.index') : '#' }}"
                    class="dashboard-module-card"
                >
                    <div class="dashboard-module-card-label">
                        الوصفات الفعالة
                    </div>

                    <div class="dashboard-module-card-value">
                        {{ $productionStats['active_recipes'] ?? 0 }}
                    </div>

                    <div class="dashboard-module-card-action">
                        فتح الوصفات
                    </div>
                </a>

            @endif


            @if(
                $moduleEnabled('production')
                && $dashboardUser->can('production.view')
            )

                <a
                    href="{{ $routeExists('production.orders.index') ? route('production.orders.index', ['status' => 'released']) : '#' }}"
                    class="dashboard-module-card is-info"
                >
                    <div class="dashboard-module-card-label">
                        جاهز لبدء الإنتاج
                    </div>

                    <div class="dashboard-module-card-value">
                        {{ $productionStats['released'] ?? 0 }}
                    </div>

                    <div class="dashboard-module-card-action">
                        فتح أوامر الإنتاج
                    </div>
                </a>


                <a
                    href="{{ $routeExists('production.orders.index') ? route('production.orders.index', ['status' => 'in_progress']) : '#' }}"
                    class="dashboard-module-card"
                >
                    <div class="dashboard-module-card-label">
                        قيد الإنتاج
                    </div>

                    <div class="dashboard-module-card-value">
                        {{ $productionStats['in_progress'] ?? 0 }}
                    </div>

                    <div class="dashboard-module-card-action">
                        متابعة الإنتاج
                    </div>
                </a>


                <a
                    href="{{ $routeExists('production.orders.index') ? route('production.orders.index', ['status' => 'completed']) : '#' }}"
                    class="dashboard-module-card is-success"
                >
                    <div class="dashboard-module-card-label">
                        مكتمل اليوم
                    </div>

                    <div class="dashboard-module-card-value">
                        {{ $productionStats['completed_today'] ?? 0 }}
                    </div>

                    <div class="dashboard-module-card-action">
                        سجل الإنتاج
                    </div>
                </a>

            @endif


            @if(
                $moduleEnabled('quality_control')
                && $dashboardUser->can('quality_control.view')
            )

                <a
                    href="{{ $routeExists('quality-control.index') ? route('quality-control.index') : '#' }}"
                    class="dashboard-module-card {{ ($productionStats['awaiting_quality'] ?? 0) > 0 ? 'is-danger' : 'is-success' }}"
                >
                    <div class="dashboard-module-card-label">
                        بانتظار فحص الجودة
                    </div>

                    <div class="dashboard-module-card-value">
                        {{ $productionStats['awaiting_quality'] ?? 0 }}
                    </div>

                    <div class="dashboard-module-card-action">
                        فتح مراقبة الجودة
                    </div>
                </a>

            @endif

        </div>

    </div>

@endif


{{-- =========================================================
     المشتريات
========================================================= --}}

@if($moduleEnabled('purchasing'))
    @include('admin.dashboard.partials.procurement-overview')
@endif


{{-- =========================================================
     الإيرادات
========================================================= --}}

@if($moduleEnabled('finance') && $dashboardUser->can('financial.dashboard.view'))

    <div
        class="dashboard-row"
        style="margin-top:1.25rem"
    >

        <div
            class="card"
            style="grid-column:1/-1"
        >

            <div class="card-header">

                <span class="card-title">
                    الإيراد اليومي — آخر 30 يوماً
                </span>

            </div>


            <div class="card-body">

                <div
                    id="revenueChart"
                    style="min-height:240px"
                ></div>

            </div>

        </div>

    </div>

@endif


{{-- =========================================================
     أداء الفروع والمنتجات
========================================================= --}}

@canany([
    'financial.dashboard.view',
    'products.view'
])

    <div
        class="dashboard-row"
        style="margin-top:1rem"
    >

        @can('financial.dashboard.view')

            @if(
                auth()->user()->isAdmin()
                && !empty($stats['branch_sales'])
            )

                <div class="card">

                    <div class="card-header">

                        <span class="card-title">
                            أداء الفروع — هذا الشهر
                        </span>

                    </div>


                    <div class="card-body">

                        <div
                            id="branchChart"
                            style="min-height:240px"
                        ></div>

                    </div>

                </div>

            @endif

        @endcan


        @can('products.view')

            @if($products->count())

                <div class="card">

                    <div class="card-header">

                        <span class="card-title">
                            أكثر المنتجات مبيعاً
                        </span>

                    </div>


                    <div class="card-body">

                        <div
                            id="productChart"
                            style="min-height:240px"
                        ></div>

                    </div>

                </div>

            @endif

        @endcan

    </div>

@endcanany


{{-- =========================================================
     الإشعارات + خط إنتاج الكيك
========================================================= --}}

<div
    class="dashboard-row"
    style="margin-top:1rem"
>

    {{-- آخر الإشعارات --}}
    @if($moduleEnabled('notifications'))

    <div class="card">

        <div class="card-header">

            <span class="card-title">
                آخر الإشعارات
            </span>

            @if($routeExists('notifications.index'))
                <a
                    href="{{ route('notifications.index') }}"
                    class="card-action"
                >
                    عرض الكل
                </a>
            @endif

        </div>


        <div
            class="card-body"
            style="padding:0"
        >

            @forelse(($stats['recent_notifications'] ?? []) as $notification)

                @php
                    $unread =
                        is_null(
                            $notification->read_at
                        );
                @endphp


                <div
                    style="
                        padding:.875rem 1.25rem;
                        border-bottom:1px solid var(--border-light);
                        {{ $unread ? 'background:var(--gold-ultra);' : '' }}
                    "
                >

                    <div
                        style="
                            font-size:.82rem;
                            font-weight:{{ $unread ? 700 : 500 }};
                        "
                    >
                        {{
                            data_get(
                                $notification->data,
                                'title'
                            )
                            ??
                            data_get(
                                $notification->data,
                                'message'
                            )
                            ??
                            'إشعار جديد'
                        }}
                    </div>


                    <div
                        style="
                            font-size:.72rem;
                            color:var(--text-muted);
                            margin-top:.15rem;
                        "
                    >
                        {{ $notification->created_at->diffForHumans() }}
                    </div>

                </div>

            @empty

                <div class="empty-state-sm">
                    لا توجد إشعارات حديثة
                </div>

            @endforelse

        </div>

    </div>

    @endif


    {{-- =====================================================
         خط إنتاج الكيك
         لا يظهر للـ Dispatcher
    ====================================================== --}}

    @if($canSeeCakeProductionPipeline)

        <div class="card">

            <div class="card-header">

                <span class="card-title">
                    مراحل طلبات الكيك
                </span>

                @can('cake_orders.view')

                    <a
                        href="{{ route('cake-orders.index') }}"
                        class="card-action"
                    >
                        عرض الكل
                    </a>

                @endcan

            </div>


            <div class="card-body">

                <div class="pipeline-grid">

                    <div class="pipeline-stage">
                        <div
                            class="pipeline-count {{ ($stats['cake_pipeline']['pending'] ?? 0) > 0 ? 'has-items' : '' }}"
                        >
                            {{ $stats['cake_pipeline']['pending'] ?? 0 }}
                        </div>

                        <div class="pipeline-label">
                            قيد المراجعة
                        </div>
                    </div>

                    <div class="pipeline-stage">
                        <div
                            class="pipeline-count {{ ($stats['cake_pipeline']['in_progress'] ?? 0) > 0 ? 'has-items' : '' }}"
                        >
                            {{ $stats['cake_pipeline']['in_progress'] ?? 0 }}
                        </div>

                        <div class="pipeline-label">
                            قيد التنفيذ
                        </div>
                    </div>

                    <div class="pipeline-stage">
                        <div
                            class="pipeline-count {{ ($stats['cake_pipeline']['ready'] ?? 0) > 0 ? 'has-items' : '' }}"
                        >
                            {{ $stats['cake_pipeline']['ready'] ?? 0 }}
                        </div>

                        <div class="pipeline-label">
                            جاهز للاستلام
                        </div>
                    </div>

                    <div class="pipeline-stage">
                        <div
                            class="pipeline-count {{ ($stats['cake_pipeline']['completed'] ?? 0) > 0 ? 'has-items' : '' }}"
                        >
                            {{ $stats['cake_pipeline']['completed'] ?? 0 }}
                        </div>

                        <div class="pipeline-label">
                            مكتمل
                        </div>
                    </div>

                </div>

            </div>

        </div>

    @endif

</div>


{{-- =========================================================
     تنبيه نقص المخزون
========================================================= --}}

@if($moduleEnabled('inventory') && $dashboardUser->can('inventory.view'))

    @if($lowStock > 0)

        <div
            class="alert-banner alert-banner-warning"
            style="margin-top:1rem"
        >

            <span>
                تنبيه:
                <strong>{{ $lowStock }}</strong>
                منتج وصل إلى الحد الأدنى للمخزون.
            </span>

            <a
                href="{{ route('inventory.index') }}"
                class="alert-link"
            >
                عرض المخزون
            </a>

        </div>

    @endif

@endif

@endsection


@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    if (typeof ApexCharts === 'undefined') {
        return;
    }

    const defaults = {
        chart: {
            fontFamily: 'Cairo, sans-serif',
            toolbar: {
                show: false
            }
        },

        dataLabels: {
            enabled: false
        },

        grid: {
            borderColor: '#F1F3F5',
            strokeDashArray: 3
        },

        colors: [
            '#D4A017'
        ]
    };


    /* =========================================================
       الإيرادات
    ========================================================= */

    const revenue =
        document.getElementById(
            'revenueChart'
        );

    if (revenue) {

        new ApexCharts(
            revenue,
            {
                ...defaults,

                chart: {
                    ...defaults.chart,
                    type: 'area',
                    height: 240
                },

                series: [
                    {
                        name: 'الإيراد ₪',
                        data: @json($stats['revenue_data'] ?? [])
                    }
                ],

                xaxis: {
                    categories:
                        @json($stats['revenue_labels'] ?? [])
                },

                stroke: {
                    curve: 'smooth',
                    width: 2.5
                },

                fill: {
                    type: 'gradient',

                    gradient: {
                        opacityFrom: .25,
                        opacityTo: .03
                    }
                }
            }
        ).render();
    }


    /* =========================================================
       أداء الفروع
    ========================================================= */

    const branch =
        document.getElementById(
            'branchChart'
        );

    if (branch) {

        const data =
            @json($stats['branch_sales'] ?? []);

        new ApexCharts(
            branch,
            {
                ...defaults,

                chart: {
                    ...defaults.chart,
                    type: 'bar',
                    height: 240
                },

                series: [
                    {
                        name: 'المبيعات ₪',

                        data: data.map(
                            item =>
                                item.amount
                        )
                    }
                ],

                xaxis: {
                    categories:
                        data.map(
                            item =>
                                item.name
                        )
                },

                plotOptions: {
                    bar: {
                        borderRadius: 5,
                        columnWidth: '55%'
                    }
                }
            }
        ).render();
    }


    /* =========================================================
       المنتجات
    ========================================================= */

    const product =
        document.getElementById(
            'productChart'
        );

    if (product) {

        const data =
            @json($stats['top_products'] ?? []);

        new ApexCharts(
            product,
            {
                ...defaults,

                chart: {
                    ...defaults.chart,
                    type: 'donut',
                    height: 240
                },

                series:
                    data.map(
                        item =>
                            Number(
                                item.total_qty
                            )
                    ),

                labels:
                    data.map(
                        item =>
                            item.product_name
                    ),

                colors: [
                    '#D4A017',
                    '#F4C542',
                    '#B8860B',
                    '#FFE08A',
                    '#8D6E12'
                ],

                legend: {
                    position: 'bottom'
                }
            }
        ).render();
    }

});
</script>
@endpush
