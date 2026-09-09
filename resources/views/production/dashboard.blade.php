@extends('layouts.app')

@section('title', 'لوحة الإنتاج')
@section('page-title', 'لوحة الإنتاج')

@section('content')
<style>
    .production-hero {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.35rem;
        margin-bottom: 1rem;
        border: 1px solid var(--border-light);
        border-radius: 18px;
        background: linear-gradient(135deg, rgba(147, 15, 59, .08), rgba(212, 165, 55, .08));
    }
    .production-hero h1 { margin: 0 0 .35rem; font-size: 1.45rem; }
    .production-hero p { margin: 0; color: var(--text-muted); }
    .production-actions { display: flex; flex-wrap: wrap; gap: .65rem; }
    .production-stats {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: .85rem;
        margin-bottom: 1rem;
    }
    .production-stat {
        position: relative;
        overflow: hidden;
        min-height: 112px;
        padding: 1rem 1.1rem;
        border: 1px solid var(--border-light);
        border-radius: 16px;
        background: var(--card-bg, #fff);
    }
    .production-stat::before {
        content: '';
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 5px;
        background: var(--stat-color);
    }
    .production-stat-value { margin-top: .55rem; font-size: 1.7rem; font-weight: 800; color: var(--stat-color); }
    .production-stat-label { font-size: .82rem; font-weight: 700; }
    .production-stat-note { margin-top: .15rem; color: var(--text-muted); font-size: .72rem; }
    .production-stat.draft { --stat-color: #7b8794; }
    .production-stat.released { --stat-color: #b7791f; }
    .production-stat.progress { --stat-color: #2563a8; }
    .production-stat.completed { --stat-color: #138a63; }
    .production-stat.recipes { --stat-color: #930f3b; }
    .production-order-link { color: inherit; text-decoration: none; }
    .production-order-link:hover { color: var(--primary, #930f3b); }
    .production-product { font-weight: 700; }
    .production-secondary { margin-top: .2rem; font-size: .72rem; color: var(--text-muted); }
    @media (max-width: 1100px) { .production-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 760px) {
        .production-hero { align-items: stretch; flex-direction: column; }
        .production-actions .btn { flex: 1; text-align: center; }
        .production-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 460px) { .production-stats { grid-template-columns: 1fr; } }
</style>

<div class="production-hero">
    <div>
        <h1>متابعة الإنتاج</h1>
        <p>ملخص أوامر الإنتاج والوصفات الفعالة ضمن المواقع المسموح لك بها.</p>
    </div>
    <div class="production-actions">
        @can('production.view')
            <a class="btn btn-ghost" href="{{ route('production.orders.index') }}">سجل أوامر الإنتاج</a>
        @endcan
        @can('recipes.view')
            <a class="btn btn-ghost" href="{{ route('production.recipes.index') }}">الوصفات</a>
        @endcan
        @can('production.create')
            <a class="btn btn-gold" href="{{ route('production.orders.create') }}">إنشاء أمر إنتاج</a>
        @endcan
    </div>
</div>

<div class="production-stats">
    <div class="production-stat draft">
        <div class="production-stat-label">أوامر مسودة</div>
        <div class="production-stat-value">{{ number_format($stats['draft'] ?? 0) }}</div>
        <div class="production-stat-note">بانتظار المراجعة والاعتماد</div>
    </div>
    <div class="production-stat released">
        <div class="production-stat-label">معتمدة للإنتاج</div>
        <div class="production-stat-value">{{ number_format($stats['released'] ?? 0) }}</div>
        <div class="production-stat-note">جاهزة لبدء التنفيذ</div>
    </div>
    <div class="production-stat progress">
        <div class="production-stat-label">قيد الإنتاج</div>
        <div class="production-stat-value">{{ number_format($stats['in_progress'] ?? 0) }}</div>
        <div class="production-stat-note">يجري تنفيذها الآن</div>
    </div>
    <div class="production-stat completed">
        <div class="production-stat-label">مكتملة اليوم</div>
        <div class="production-stat-value">{{ number_format($stats['completed_today'] ?? 0) }}</div>
        <div class="production-stat-note">تم إتمامها خلال اليوم</div>
    </div>
    <div class="production-stat recipes">
        <div class="production-stat-label">الوصفات الفعالة</div>
        <div class="production-stat-value">{{ number_format($stats['active_recipes'] ?? 0) }}</div>
        <div class="production-stat-note">متاحة للاستخدام في الإنتاج</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">أحدث أوامر الإنتاج</span>
        @can('production.view')
            <a class="btn btn-ghost btn-sm" href="{{ route('production.orders.index') }}">عرض جميع الأوامر</a>
        @endcan
    </div>

    @if($recent->isEmpty())
        <div class="card-body">
            <div class="empty-state">
                <h3>لا توجد أوامر إنتاج بعد</h3>
                <p>ابدأ بإنشاء أول أمر إنتاج من وصفة فعالة.</p>
                @can('production.create')
                    <a class="btn btn-gold" href="{{ route('production.orders.create') }}">إنشاء أمر إنتاج</a>
                @endcan
            </div>
        </div>
    @else
        <div class="table-wrap" style="border:0;border-radius:0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>رقم الأمر</th>
                        <th>المنتج</th>
                        <th>الوصفة</th>
                        <th>الموقع</th>
                        <th>الكمية المخططة</th>
                        <th>الحالة</th>
                        <th>تاريخ التخطيط</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recent as $order)
                        @php
                            $status = $order->statusValue();
                            $badge = match ($status) {
                                'completed' => 'badge-active',
                                'cancelled', 'rejected' => 'badge-inactive',
                                default => 'badge-pending',
                            };
                            $statusLabel = $order->status instanceof \App\Enums\ProductionOrderStatus
                                ? $order->status->label()
                                : \App\Support\ArabicDisplay::status($status);
                            $productName = data_get($order->product, 'name_ar')
                                ?: data_get($order->product, 'name')
                                ?: 'منتج غير متاح';
                        @endphp
                        <tr>
                            <td>
                                <a class="production-order-link" href="{{ route('production.orders.show', $order) }}">
                                    <strong dir="ltr">{{ $order->production_number }}</strong>
                                </a>
                            </td>
                            <td><span class="production-product">{{ $productName }}</span></td>
                            <td>
                                {{ data_get($order->recipe, 'name', 'وصفة غير متاحة') }}
                                <div class="production-secondary">الإصدار {{ $order->recipe_version }}</div>
                            </td>
                            <td>{{ data_get($order->location, 'name', 'موقع غير متاح') }}</td>
                            <td>{{ number_format((float) $order->planned_output_quantity, 3) }}</td>
                            <td><span class="badge {{ $badge }}">{{ $statusLabel }}</span></td>
                            <td>{{ $order->planned_at?->format('Y/m/d H:i') ?? 'لم يحدد' }}</td>
                            <td>
                                <a class="btn btn-ghost btn-sm" href="{{ route('production.orders.show', $order) }}">التفاصيل</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
