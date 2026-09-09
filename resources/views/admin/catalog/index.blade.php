@extends('layouts.app')
@section('title', 'إعدادات كتالوج المنتجات')
@section('page-title', 'إعدادات كتالوج المنتجات')
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">إعدادات كتالوج المنتجات</h1>
        <p class="page-subheading">الأساس الموحد للمنتجات العادية، الملابس، الأحذية والتجزئة.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('products.index') }}" class="btn btn-ghost btn-sm">المنتجات</a>
        @if(Route::has('modules.index'))
            <a href="{{ route('modules.index') }}" class="btn btn-outline btn-sm">إدارة الوحدات</a>
        @endif
    </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(5,minmax(0,1fr));margin-bottom:1rem">
    @foreach([
        ['الوحدات',$stats['units'],'catalog.units.index',true],
        ['العلامات التجارية',$stats['brands'],'catalog.brands.index',$flags['brands']],
        ['المقاسات',$stats['sizes'],'catalog.sizes.index',$flags['sizes']],
        ['الألوان',$stats['colors'],'catalog.colors.index',$flags['colors']],
        ['الخصائص',$stats['attributes'],'catalog.attributes.index',$flags['variants']],
    ] as [$label,$count,$route,$enabled])
        <div class="stat-card stat-gold">
            <div class="stat-info" style="width:100%">
                <div class="stat-value">{{ $count }}</div>
                <div class="stat-label">{{ $label }}</div>
                <div style="margin-top:.6rem">
                    @if($enabled)
                        <a href="{{ route($route) }}" class="btn btn-ghost btn-sm">إدارة</a>
                    @else
                        <span class="badge badge-inactive">الوحدة غير مفعلة</span>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card">
    <div class="card-header"><span class="card-title">كيف تعمل البنية الجديدة؟</span></div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem">
            <div><strong>المنتج الأساسي</strong><p style="color:var(--text-muted);margin:.4rem 0 0">يبقى متوافقًا مع كل مبيعات ومخزون ومشتريات دهب الحالية.</p></div>
            <div><strong>المتغيرات</strong><p style="color:var(--text-muted);margin:.4rem 0 0">كل متغير له SKU وباركود وسعر اختياري مستقل، مع مقاس ولون وخصائص.</p></div>
            <div><strong>توافق آمن</strong><p style="color:var(--text-muted);margin:.4rem 0 0">عمود الوحدة القديم بقي موجودًا ويتم مزامنته مع سجل وحدات القياس، لذلك لا تنكسر التدفقات القديمة.</p></div>
        </div>
    </div>
</div>
@endsection
