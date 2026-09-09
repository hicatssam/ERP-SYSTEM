<div class="page-header-actions" style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem">
    <a href="{{ route('catalog.index') }}" class="btn btn-ghost btn-sm">إعدادات الكتالوج</a>
    <a href="{{ route('catalog.units.index') }}" class="btn btn-ghost btn-sm">الوحدات</a>
    @if(app(\App\Services\ModuleService::class)->isEnabled('brands'))
        <a href="{{ route('catalog.brands.index') }}" class="btn btn-ghost btn-sm">العلامات التجارية</a>
    @endif
    @if(app(\App\Services\ModuleService::class)->isEnabled('sizes'))
        <a href="{{ route('catalog.sizes.index') }}" class="btn btn-ghost btn-sm">المقاسات</a>
    @endif
    @if(app(\App\Services\ModuleService::class)->isEnabled('colors'))
        <a href="{{ route('catalog.colors.index') }}" class="btn btn-ghost btn-sm">الألوان</a>
    @endif
    @if(app(\App\Services\ModuleService::class)->isEnabled('product_variants'))
        <a href="{{ route('catalog.attributes.index') }}" class="btn btn-ghost btn-sm">خصائص المتغيرات</a>
    @endif
</div>
