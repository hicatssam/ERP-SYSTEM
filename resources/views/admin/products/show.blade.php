@extends('layouts.app')
@section('title', $product->name_ar ?? $product->name)

@push('styles')
<style>
    .product-barcode-section {
        margin-top: .25rem;
        padding-top: 1.25rem;
        border-top: 1px solid var(--border);
    }

    .product-barcode-label {
        width: min(100%, 360px);
        margin: 0 auto;
        padding: 1rem 1.25rem;
        direction: ltr;
        text-align: center;
        background: #fff;
        color: #111;
        border: 1px solid var(--border);
        border-radius: 12px;
    }

    .product-barcode-name {
        margin-bottom: .5rem;
        overflow: hidden;
        font-size: .9rem;
        font-weight: 700;
        line-height: 1.35;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .product-barcode-svg {
        display: block;
        width: 100%;
        height: auto;
        margin: 0 auto;
        background: #fff;
    }

    .product-barcode-number {
        margin-top: .3rem;
        font-family: monospace;
        font-size: 1rem;
        font-weight: 700;
        letter-spacing: .16em;
        direction: ltr;
    }

    .product-barcode-sku {
        margin-top: .25rem;
        font-family: monospace;
        font-size: .75rem;
        color: #555;
        direction: ltr;
    }

    .product-barcode-error {
        display: none;
        padding: 1rem;
        color: #b42318;
        font-size: .85rem;
        direction: rtl;
    }

    .product-barcode-actions {
        display: flex;
        justify-content: center;
        margin-top: .75rem;
    }

    @media (max-width: 640px) {
        .product-barcode-label {
            padding: .85rem;
        }
    }
</style>
@endpush

@section('content')
<div class="page-actions">
    <div class="page-actions-title">{{ $product->name_ar ?? $product->name }}</div>
    <div class="action-btns">
        @can('products.update')
        @if($variantsEnabled && Route::has('products.variants.index'))
        <a href="{{ route('products.variants.index', $product) }}" class="btn btn-gold btn-sm">المتغيرات ({{ $product->variants->count() }})</a>
        @endif
        <a href="{{ route('products.edit', $product) }}" class="btn btn-outline btn-sm">تعديل</a>
        <form action="{{ route('products.toggle-status', $product) }}" method="POST" style="display:inline">
            @csrf
            @method('POST')
            <button class="btn btn-ghost btn-sm" type="submit">{{ $product->is_active ? 'تعطيل' : 'تفعيل' }}</button>
        </form>
        @endcan
        <a href="{{ route('products.index') }}" class="btn btn-ghost btn-sm">رجوع</a>
    </div>
</div>

<div class="dashboard-row">
    {{-- Product details + image --}}
    <div class="card">
        <div class="card-header"><span class="card-title">بيانات المنتج</span></div>
        <div class="card-body" style="display:grid;gap:1rem">
            @if($product->image)
            <div style="text-align:center;padding:.5rem 0">
                <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}"
                     style="max-width:220px;max-height:220px;width:100%;object-fit:contain;border-radius:12px;border:1px solid var(--border);background:var(--surface);padding:.5rem">
            </div>
            @endif

            <table class="data-table">
                <tr><td style="color:var(--text-muted)">الاسم العربي</td><td>{{ $product->name_ar ?? '—' }}</td></tr>
                <tr><td style="color:var(--text-muted)">الاسم الإنجليزي</td><td>{{ $product->name }}</td></tr>
                <tr><td style="color:var(--text-muted)">الفئة</td><td>{{ $product->category?->name_ar ?? $product->category?->name }}</td></tr>
                <tr><td style="color:var(--text-muted)">العلامة التجارية</td><td>{{ $product->brand?->displayName() ?? '—' }}</td></tr>
                <tr><td style="color:var(--text-muted)">نوع المنتج</td><td>{{ $product->product_type?->label() ?? 'منتج عادي' }}</td></tr>
                <tr><td style="color:var(--text-muted)">SKU</td><td><code>{{ $product->sku }}</code></td></tr>
                <tr><td style="color:var(--text-muted)">الباركود</td><td dir="ltr">{{ $product->barcode ?? '—' }}</td></tr>
                <tr><td style="color:var(--text-muted)">الوحدة</td><td>{{ $product->unitDefinition?->displayName() ?? $product->unit }}</td></tr>
                <tr><td style="color:var(--text-muted)">تتبع الدفعات</td><td>{{ $product->tracks_batch ? 'نعم' : 'لا' }}</td></tr>
                <tr><td style="color:var(--text-muted)">تتبع الصلاحية</td><td>{{ $product->tracks_expiry ? 'نعم' : 'لا' }}</td></tr>
                <tr><td style="color:var(--text-muted)">السعر الأساسي</td><td>₪{{ number_format($product->base_selling_price, 2) }}</td></tr>
                @if($product->description)
                <tr><td style="color:var(--text-muted)">الوصف</td><td>{{ $product->description }}</td></tr>
                @endif
                <tr>
                    <td style="color:var(--text-muted)">الحالة</td>
                    <td><span class="badge {{ $product->is_active ? 'badge-active' : 'badge-inactive' }}">{{ $product->is_active ? 'نشط' : 'معطل' }}</span></td>
                </tr>
            </table>

            {{-- Scannable EAN-13 barcode --}}
            <div class="product-barcode-section">
                <div class="card-title" style="margin-bottom:.75rem;text-align:center">باركود المنتج</div>

                @if($product->barcode)
                    <div class="product-barcode-label" id="productBarcodeLabel">
                        <div class="product-barcode-name">{{ $product->name_ar ?? $product->name }}</div>
                        <svg
                            class="product-barcode-svg js-ean13-barcode"
                            data-ean13="{{ $product->barcode }}"
                            viewBox="0 0 117 68"
                            role="img"
                            aria-label="باركود المنتج {{ $product->barcode }}"
                        ></svg>
                        <div class="product-barcode-error js-barcode-error">رقم الباركود غير صالح بصيغة EAN-13.</div>
                        <div class="product-barcode-number">{{ $product->barcode }}</div>
                        <div class="product-barcode-sku">SKU: {{ $product->sku }}</div>
                    </div>

                    <div class="product-barcode-actions">
                        <button type="button" class="btn btn-gold btn-sm" onclick="printProductBarcode()">
                            طباعة الباركود
                        </button>
                    </div>
                @else
                    <div class="empty-state-sm">لا يوجد رقم باركود لهذا المنتج.</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Branch availability --}}
    <div class="card">
        <div class="card-header"><span class="card-title">توفر المنتج في الفروع</span></div>
        <div class="card-body">
            @forelse($product->locationProducts as $lp)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--border)">
                    <span>{{ $lp->location?->name }}</span>
                    <span class="badge {{ $lp->is_available ? 'badge-active' : 'badge-inactive' }}">{{ $lp->is_available ? 'متوفر' : 'غير متوفر' }}</span>
                </div>
            @empty
                <div class="empty-state-sm">غير مخصص لأي موقع.</div>
            @endforelse
        </div>
    </div>
</div>

@if($canViewSupplierData)
    <div style="margin-top:1rem">
        @include('admin.products.partials.suppliers-card', [
            'supplierRows' => $product->supplierProducts
                ->sortByDesc(fn ($row) => (bool) $row->is_preferred)
                ->values(),
        ])
    </div>
@endif

@if($variantsEnabled)
<div class="card" style="margin-top:1rem">
    <div class="card-header">
        <span class="card-title">متغيرات المنتج</span>
        @can('products.update')
            <a href="{{ route('products.variants.create',$product) }}" class="btn btn-gold btn-sm">إضافة متغير</a>
        @endcan
    </div>
    <div class="table-wrap" style="border:0">
        <table class="data-table">
            <thead><tr><th>المتغير</th><th>المقاس</th><th>اللون</th><th>SKU</th><th>الباركود</th><th>السعر</th><th>الحالة</th></tr></thead>
            <tbody>
            @forelse($product->variants as $variant)
                <tr>
                    <td><strong>{{ $variant->displayName() }}</strong> @if($variant->is_default)<span class="badge badge-active">افتراضي</span>@endif</td>
                    <td>{{ $variant->size?->displayName() ?? '—' }}</td>
                    <td>{{ $variant->color?->displayName() ?? '—' }}</td>
                    <td><code>{{ $variant->sku }}</code></td>
                    <td dir="ltr">{{ $variant->barcode ?: '—' }}</td>
                    <td>₪{{ number_format((float)$variant->effectivePrice(),2) }}</td>
                    <td><span class="badge {{ $variant->is_active?'badge-active':'badge-inactive' }}">{{ $variant->is_active?'فعال':'معطل' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-state-sm">لا توجد متغيرات بعد.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-ean13-barcode').forEach(renderEan13Barcode);
});

function renderEan13Barcode(svg) {
    const value = String(svg.dataset.ean13 || '').replace(/\D/g, '');
    const label = svg.closest('.product-barcode-label');
    const error = label ? label.querySelector('.js-barcode-error') : null;

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

    const firstDigit = Number(value[0]);
    const parity = parityPatterns[firstDigit];
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
    background.setAttribute('height', '68');
    background.setAttribute('fill', '#fff');
    svg.appendChild(background);

    const quietZone = 11;

    for (let index = 0; index < modules.length; index++) {
        if (modules[index] !== '1') continue;

        const isGuardBar = index < 3 || (index >= 45 && index < 50) || index >= 92;
        const bar = document.createElementNS(namespace, 'rect');
        bar.setAttribute('x', String(quietZone + index));
        bar.setAttribute('y', '3');
        bar.setAttribute('width', '1');
        bar.setAttribute('height', isGuardBar ? '62' : '56');
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

function printProductBarcode() {
    const label = document.getElementById('productBarcodeLabel');
    if (!label) return;

    const printWindow = window.open('', '_blank', 'width=520,height=620');
    if (!printWindow) {
        window.alert('يرجى السماح بالنوافذ المنبثقة لطباعة الباركود.');
        return;
    }

    printWindow.document.open();
    printWindow.document.write(`
        <!doctype html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="utf-8">
            <title>طباعة الباركود</title>
            <style>
                @page { size: 60mm 40mm; margin: 2mm; }
                * { box-sizing: border-box; }
                body { margin: 0; background: #fff; color: #000; font-family: Arial, sans-serif; }
                .product-barcode-label {
                    width: 56mm;
                    height: 36mm;
                    margin: 0 auto;
                    padding: 2mm 3mm;
                    overflow: hidden;
                    direction: ltr;
                    text-align: center;
                    background: #fff;
                    border: 0;
                }
                .product-barcode-name {
                    margin-bottom: 1mm;
                    overflow: hidden;
                    font-size: 9pt;
                    font-weight: 700;
                    line-height: 1.15;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }
                .product-barcode-svg { display: block; width: 100%; height: 20mm; }
                .product-barcode-number {
                    margin-top: .5mm;
                    font-family: monospace;
                    font-size: 9pt;
                    font-weight: 700;
                    letter-spacing: .12em;
                }
                .product-barcode-sku { margin-top: .4mm; font-family: monospace; font-size: 7pt; color: #222; }
                .product-barcode-error { display: none !important; }
            </style>
        </head>
        <body>${label.outerHTML}</body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();

    window.setTimeout(function () {
        printWindow.print();
        printWindow.close();
    }, 250);
}
</script>
@endpush
