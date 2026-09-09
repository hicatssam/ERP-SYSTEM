@extends('layouts.app')
@section('title', 'المنتجات')

@push('styles')
<style>
    .table-barcode-preview {
        width: 150px;
        direction: ltr;
        text-align: center;
    }

    .table-barcode-svg {
        display: block;
        width: 150px;
        height: 48px;
        background: #fff;
        border-radius: 4px;
    }

    .table-barcode-number {
        margin-top: .15rem;
        font-family: monospace;
        font-size: .65rem;
        font-weight: 600;
        letter-spacing: .08em;
        color: var(--text-muted);
    }

    .table-barcode-invalid {
        display: none;
        color: #b42318;
        font-size: .7rem;
        direction: rtl;
    }
</style>
@endpush

@section('content')
<div class="page-actions">
    <div class="page-actions-title">المنتجات</div>
    <div class="action-btns">
        @can('products.update')
        @if(Route::has('catalog.index'))
        <a href="{{ route('catalog.index') }}" class="btn btn-outline">
            إعدادات الكتالوج
        </a>
        @endif
        @endcan
        @can('products.create')
        <a href="{{ route('products.create') }}" class="btn btn-gold">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            إضافة منتج
        </a>
        @endcan
    </div>
</div>

<div class="filter-row">
    <form method="GET" action="{{ route('products.index') }}" class="filter-grid" style="width:100%">
        <input type="search" name="q" class="form-input" placeholder="الاسم / SKU / الباركود" value="{{ request('q') }}">
        <select name="category_id" class="form-input"><option value="">كل الفئات</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)request('category_id')===(string)$category->id)>{{ $category->name_ar ?? $category->name }}</option>@endforeach</select>
        @if($brands->isNotEmpty())<select name="brand_id" class="form-input"><option value="">كل العلامات</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected((string)request('brand_id')===(string)$brand->id)>{{ $brand->displayName() }}</option>@endforeach</select>@endif
        @if($variantsEnabled)<select name="product_type" class="form-input"><option value="">كل الأنواع</option><option value="standard" @selected(request('product_type')==='standard')>عادي</option><option value="variant" @selected(request('product_type')==='variant')>بمتغيرات</option></select>@endif
        <button class="btn btn-gold" type="submit">تطبيق</button>
    </form>
</div>

<div class="table-wrap">
    <table class="data-table" id="productsTable">
        <thead>
            <tr>
                <th style="width:64px">الصورة</th>
                <th>الاسم</th>
                <th>الفئة</th>
                <th>العلامة</th>
                @if($variantsEnabled)<th>النوع / المتغيرات</th>@endif
                <th>SKU</th>
                <th style="width:170px">الباركود</th>
                <th>الوحدة</th>
                <th>السعر الأساسي</th>
                <th>الحالة</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
        @forelse($products as $product)
            <tr>
                <td>
                    @if($product->image)
                        <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}" style="width:44px;height:44px;object-fit:cover;border-radius:8px;border:1px solid var(--border)">
                    @else
                        <div style="width:44px;height:44px;border-radius:8px;background:var(--surface);border:1px solid var(--border);display:flex;align-items:center;justify-content:center">
                            <svg viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="1.5" style="width:20px;height:20px"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                        </div>
                    @endif
                </td>
                <td>
                    <strong>{{ $product->name_ar ?? $product->name }}</strong>
                    @if($product->name_ar)<div style="font-size:.75rem;color:var(--text-muted)">{{ $product->name }}</div>@endif
                </td>
                <td>{{ $product->category?->name_ar ?? $product->category?->name }}</td>
                <td>{{ $product->brand?->displayName() ?? '—' }}</td>
                @if($variantsEnabled)<td><span class="badge {{ ($product->product_type?->value ?? 'standard') === 'variant' ? 'badge-active' : 'badge-pending' }}">{{ ($product->product_type?->value ?? 'standard') === 'variant' ? 'متغير' : 'عادي' }}</span>@if($product->variants_count)<div style="font-size:.7rem;color:var(--text-muted);margin-top:.25rem">{{ $product->variants_count }} متغير</div>@endif</td>@endif
                <td><code>{{ $product->sku }}</code></td>
                <td>
                    @if($product->barcode)
                        <div class="table-barcode-preview">
                            <svg
                                class="table-barcode-svg js-ean13-barcode"
                                data-ean13="{{ $product->barcode }}"
                                viewBox="0 0 117 58"
                                role="img"
                                aria-label="باركود {{ $product->barcode }}"
                            ></svg>
                            <div class="table-barcode-invalid js-barcode-error">باركود غير صالح</div>
                            <div class="table-barcode-number">{{ $product->barcode }}</div>
                        </div>
                    @else
                        <span style="color:var(--text-muted)">—</span>
                    @endif
                </td>
                <td>{{ $product->unitDefinition?->displayName() ?? $product->unit }}</td>
                <td>₪{{ number_format($product->base_selling_price, 2) }}</td>
                <td><span class="badge {{ $product->is_active ? 'badge-active' : 'badge-inactive' }}">{{ $product->is_active ? 'نشط' : 'معطل' }}</span></td>
                <td>
                    <div class="actions">
                        <a href="{{ route('products.show', $product) }}" class="btn btn-ghost btn-sm">عرض</a>
                        @can('products.update')
                        <a href="{{ route('products.edit', $product) }}" class="btn btn-outline btn-sm">تعديل</a>
                        @endcan
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ $variantsEnabled ? 11 : 10 }}"><div class="empty-state-sm">لا توجد منتجات.</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>


 <div>
    {{ $products->withQueryString()->links() }}
    </div>


@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-ean13-barcode').forEach(renderEan13Barcode);
});

function renderEan13Barcode(svg) {
    const value = String(svg.dataset.ean13 || '').replace(/\D/g, '');
    const wrapper = svg.closest('.table-barcode-preview');
    const error = wrapper ? wrapper.querySelector('.js-barcode-error') : null;

    if (!isValidEan13(value)) {
        svg.style.display = 'none';
        if (error) error.style.display = 'block';
        return;
    }

    const leftPatterns = {
        L: ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'],
        G: ['0100111', '0110011', '0011011', '0100001', '0011101', '0111001', '0000101', '0010001', '0001001', '0010111']
    };
    const rightPatterns = ['1110010', '1100110', '1101100', '1000010', '1011100', '1001110', '1010000', '1000100', '1001000', '1110100'];
    const parityPatterns = ['LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG', 'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL'];

    const parity = parityPatterns[Number(value[0])];
    let modules = '101';

    for (let index = 1; index <= 6; index++) {
        modules += leftPatterns[parity[index - 1]][Number(value[index])];
    }

    modules += '01010';

    for (let index = 7; index <= 12; index++) {
        modules += rightPatterns[Number(value[index])];
    }

    modules += '101';
    svg.innerHTML = '';
    svg.setAttribute('shape-rendering', 'crispEdges');

    const namespace = 'http://www.w3.org/2000/svg';
    const background = document.createElementNS(namespace, 'rect');
    background.setAttribute('x', '0');
    background.setAttribute('y', '0');
    background.setAttribute('width', '117');
    background.setAttribute('height', '58');
    background.setAttribute('fill', '#fff');
    svg.appendChild(background);

    const quietZone = 11;

    for (let index = 0; index < modules.length; index++) {
        if (modules[index] !== '1') continue;

        const isGuardBar = index < 3 || (index >= 45 && index < 50) || index >= 92;
        const bar = document.createElementNS(namespace, 'rect');
        bar.setAttribute('x', String(quietZone + index));
        bar.setAttribute('y', '2');
        bar.setAttribute('width', '1');
        bar.setAttribute('height', isGuardBar ? '54' : '49');
        bar.setAttribute('fill', '#000');
        svg.appendChild(bar);
    }
}

function isValidEan13(value) {
    if (!/^\d{13}$/.test(value)) return false;

    const digits = value.split('').map(Number);
    const sum = digits.slice(0, 12).reduce(function (total, digit, index) {
        return total + digit * (index % 2 === 0 ? 1 : 3);
    }, 0);
    const expectedCheckDigit = (10 - (sum % 10)) % 10;

    return expectedCheckDigit === digits[12];
}
</script>
@endpush