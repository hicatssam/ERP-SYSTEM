@extends('layouts.app')
@section('title', 'التقارير')
@section('page-title', 'التقارير')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h1 class="page-heading">التقارير</h1>
        <p class="page-subheading">اختر التقرير المطلوب لعرض وتصدير البيانات</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('report-schedules.index') }}" class="btn btn-outline btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            التقارير المجدولة
        </a>
    </div>
</div>

@php
$categories = [
    'المبيعات' => [
        'daily-sales'    => ['title' => 'المبيعات اليومية',      'color' => 'stat-green'],
        'monthly-sales'  => ['title' => 'المبيعات الشهرية',      'color' => 'stat-green'],
        'branch-sales'   => ['title' => 'أداء الفروع',           'color' => 'stat-blue'],
        'product-sales'  => ['title' => 'مبيعات المنتجات',       'color' => 'stat-gold'],
        'orders'         => ['title' => 'تقرير الطلبات',         'color' => 'stat-gold'],
        'cake-orders'      => ['title' => 'طلبات الكيك الخاصة',   'color' => 'stat-blue'],
        'cake-production'  => ['title' => 'إنتاج الكيك الموحد',    'color' => 'stat-gold'],
    ],
    'المالية' => [
        'invoices'          => ['title' => 'الفواتير',               'color' => 'stat-gold'],
        'payments'          => ['title' => 'الدفعات',                'color' => 'stat-green'],
        'collections'       => ['title' => 'التحصيلات',             'color' => 'stat-green'],
        'outstanding'       => ['title' => 'الأرصدة المعلقة',       'color' => 'stat-orange'],
        'cash-sessions'     => ['title' => 'جلسات الكاشير',         'color' => 'stat-blue'],
    ],
    'المخزون' => [
        'inventory'         => ['title' => 'المخزون الحالي',         'color' => 'stat-gold'],
        'stock-movements'   => ['title' => 'حركات المخزون',          'color' => 'stat-blue'],
        'low-stock'         => ['title' => 'المخزون المنخفض',        'color' => 'stat-orange'],
        'stock-transfers'   => ['title' => 'تحويلات المخزون',        'color' => 'stat-blue'],
    ],
    'النظام' => [
        'activity-logs'  => ['title' => 'سجل النشاطات',          'color' => 'stat-grey'],
    ],
];

$icons = [
    'daily-sales'    => '<path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/>',
    'monthly-sales'  => '<rect x="2" y="2" width="20" height="20" rx="2"/><path d="M16 6v4M12 6v6M8 6v8"/>',
    'branch-sales'   => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
    'product-sales'  => '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>',
    'orders'         => '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2"/>',
    'cake-orders'      => '<path d="M20 10c0-5.523-8-10-8-10S4 4.477 4 10a4 4 0 0 0 8 0 4 4 0 0 0 8 0z"/>',
    'cake-production'  => '<path d="M12 2l9 5-9 5-9-5 9-5z"/><path d="M3 12l9 5 9-5"/><path d="M3 17l9 5 9-5"/>',
    'invoices'       => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
    'payments'       => '<rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>',
    'collections'    => '<polyline points="20 6 9 17 4 12"/>',
    'outstanding'    => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
    'cash-sessions'  => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
    'inventory'      => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
    'stock-movements'=> '<polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/>',
    'low-stock'      => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/>',
    'stock-transfers'=> '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
    'activity-logs'  => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
];
@endphp

@foreach($categories as $catName => $reports)
<div style="margin-bottom:1.5rem">
    <div style="font-size:.72rem;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;color:var(--text-muted);margin-bottom:.75rem;padding-bottom:.5rem;border-bottom:1px solid var(--border)">{{ $catName }}</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.75rem">
        @foreach($reports as $type => $meta)
        <a href="{{ route('reports.show', $type) }}" class="stat-card {{ $meta['color'] }}"
           style="text-decoration:none;cursor:pointer;transition:all .18s">
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $icons[$type] ?? '<path d="M14 2H6a2 2 0 0 0-2 2v16h12V2z"/>' !!}</svg>
            </div>
            <div class="stat-info">
                <div style="font-size:.88rem;font-weight:700;color:var(--text);line-height:1.3">{{ $meta['title'] }}</div>
                <div style="font-size:.7rem;color:var(--text-muted);margin-top:.15rem;display:flex;align-items:center;gap:.25rem">
                    عرض التقرير
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </div>
            </div>
        </a>
        @endforeach
    </div>
</div>
@endforeach
@endsection
