@extends('layouts.app')

@section('title', $title)

@section('page-title', $title)

@section('content')

@php
    $enumValue = static function ($value) {
        return $value instanceof \BackedEnum
            ? $value->value
            : $value;
    };

    $statusLabel = static function ($status): string {
        $status = (string) $status;

        return match ($status) {
            // Orders
            'draft'                  => 'مسودة',
            'pending'                => 'قيد الانتظار',
            'confirmed'              => 'مؤكد',
            'completed'              => 'مكتمل',
            'cancelled',
            'canceled'               => 'ملغي',

            // Cake orders
            'pending_factory_review' => 'قيد مراجعة المصنع',
            'accepted'               => 'مقبول',
            'scheduled'              => 'مجدول',
            'in_preparation'         => 'قيد التجهيز',
            'decorating',
            'in_decoration'          => 'قيد التزيين',
            'quality_check'          => 'فحص الجودة',
            'ready'                  => 'جاهز',
            'sent_to_branch',
            'dispatched_to_branch'   => 'خرج للتوصيل',
            'received_by_branch',
            'received_at_branch'     => 'استلمه الفرع',
            'ready_for_customer',
            'ready_for_pickup'       => 'جاهز للتسليم',
            'delivered'              => 'تم التسليم',
            'rejected'               => 'مرفوض',

            // Invoices / payments
            'active'                 => 'نشطة',
            'void',
            'voided'                 => 'ملغاة',
            'pending_verification'   => 'بانتظار التحقق',
            'pending_payment_verification' => 'بانتظار التحقق من الدفع',
            'paid'                   => 'مدفوع',
            'partial',
            'partially_paid'         => 'مدفوع جزئياً',
            'unpaid'                 => 'غير مدفوع',
            'refunded'               => 'مستردة',
            'corrected'              => 'مصححة',
            'failed'                 => 'فشلت',

            // Cash session / generic
            'open'                   => 'مفتوح',
            'closed'                 => 'مغلق',
            'approved'               => 'معتمد',
            'submitted'              => 'مرسل',
            'in_progress'            => 'قيد التنفيذ',
            'ready_for_dispatch'     => 'جاهز للإرسال',
            'out_for_delivery'       => 'خرج للتوصيل',
            'received_at_branch'     => 'استلمه الفرع',
            'fulfilled'              => 'مكتمل',

            default                  => $status !== '' ? $status : '—',
        };
    };

    $statusBadgeClass = static function ($status): string {
        $status = (string) $status;

        return match ($status) {
            'completed',
            'delivered',
            'active',
            'paid',
            'approved',
            'fulfilled'
                => 'badge-active',

            'cancelled',
            'canceled',
            'rejected',
            'void',
            'voided',
            'failed'
                => 'badge-inactive',

            default
                => 'badge-pending',
        };
    };
@endphp


{{-- ── Page Header ── --}}

<div class="page-header">

    <div class="page-header-text">

        <h1 class="page-heading">{{ $title }}</h1>

        <p class="page-subheading">

            الفترة: {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}

            @if($type === 'cake-production')
                · حسب تاريخ التسليم / الاحتياج
            @endif

        </p>

    </div>
@include('reports.partials.report-actions')

{{-- ── Row-count warning banner (shown via JS) ── --}}

<div id="export-count-banner" style="display:none;margin-bottom:.75rem">

    <div id="export-count-inner" style="

        display:flex;align-items:center;gap:.6rem;

        padding:.6rem 1rem;border-radius:8px;font-size:.85rem;font-weight:500;

        background:rgba(234,179,8,.12);border:1px solid rgba(234,179,8,.4);color:#92400e;

    ">

        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:1rem;height:1rem;flex-shrink:0"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>

        <span id="export-count-text"></span>

    </div>

</div>

{{-- ── Filters ── --}}

<div class="filter-row">

    <form id="report-filter-form" method="GET" action="{{ route('reports.show', $type) }}">

        <div class="filter-grid">

            <div class="filter-group" style="min-width:150px">

                <label class="filter-label">من تاريخ</label>

                <input type="date" name="date_from" id="filter-date-from" class="form-input" value="{{ $dateFrom }}">

            </div>

            <div class="filter-group" style="min-width:150px">

                <label class="filter-label">إلى تاريخ</label>

                <input type="date" name="date_to" id="filter-date-to" class="form-input" value="{{ $dateTo }}">

            </div>

            @if($locations->count() > 0)

            <div class="filter-group">

                <label class="filter-label">الفرع / الموقع</label>

                <select name="location_id" id="filter-location-id" class="form-input">

                    <option value="">الكل</option>

                    @foreach($locations as $loc)

                    <option value="{{ $loc->id }}" {{ $locationId == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>

                    @endforeach

                </select>

            </div>

            @endif

            <div class="filter-group" style="justify-content:flex-end;min-width:auto">

                <label class="filter-label">&nbsp;</label>

                <button type="submit" class="btn btn-gold">

                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>

                    تطبيق

                </button>

            </div>

        </div>

    </form>

</div>

@push('scripts')

<script>

(function () {

    const reportType  = @json($type);

    const countBase   = @json(route('reports.count', ['type' => $type]));

    const xlsxBase    = @json(route('reports.export.xlsx', ['type' => $type]));

    const pdfBase     = @json(route('reports.export.pdf',  ['type' => $type]));
    const printBase   = @json(route('reports.print', ['type' => $type]));

    const banner      = document.getElementById('export-count-banner');

    const bannerText  = document.getElementById('export-count-text');

    const btnXlsx     = document.getElementById('btn-export-xlsx');

    const btnPdf      = document.getElementById('btn-export-pdf');
    const btnPrint    = document.getElementById('btn-print-report');

    const elFrom      = document.getElementById('filter-date-from');

    const elTo        = document.getElementById('filter-date-to');

    const elLocation  = document.getElementById('filter-location-id');

    let debounce;

    function buildParams() {

        const p = new URLSearchParams();

        if (elFrom?.value)     p.set('date_from',   elFrom.value);

        if (elTo?.value)       p.set('date_to',     elTo.value);

        if (elLocation?.value) p.set('location_id', elLocation.value);

        return p;

    }

    function updateExportLinks(params) {

        const qs = params.toString();

        if (btnXlsx) btnXlsx.href = xlsxBase + (qs ? '?' + qs : '');

        if (btnPdf)  btnPdf.href  = pdfBase  + (qs ? '?' + qs : '');
        if (btnPrint) btnPrint.href = printBase + (qs ? '?' + qs : '');

    }

    function fetchCount() {

        const params = buildParams();

        updateExportLinks(params);

        fetch(countBase + '?' + params.toString(), {

            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }

        })

        .then(r => r.ok ? r.json() : null)

        .then(data => {

            if (!data) { banner.style.display = 'none'; return; }

            const count    = data.count;

            const xlsxCap  = data.xlsx_cap;

            const pdfCap   = data.pdf_cap;

            const fmtCount = count.toLocaleString('ar');

            if (data.exceeds_xlsx_cap) {

                bannerText.textContent =

                    '⚠ عدد الصفوف تقريباً ' + fmtCount +

                    ' — سيتم اقتصاص ملف Excel على ' + xlsxCap.toLocaleString('ar') + ' صف' +

                    ' وملف PDF على ' + pdfCap.toLocaleString('ar') + ' صف.';

                banner.style.display = 'block';

            } else if (data.exceeds_pdf_cap) {

                bannerText.textContent =

                    '⚠ عدد الصفوف تقريباً ' + fmtCount +

                    ' — سيتم اقتصاص ملف PDF على ' + pdfCap.toLocaleString('ar') + ' صف. ملف Excel يدعم كامل البيانات.';

                banner.style.display = 'block';

            } else {

                banner.style.display = 'none';

            }

        })

        .catch(() => { banner.style.display = 'none'; });

    }

    function onFilterChange() {

        clearTimeout(debounce);

        debounce = setTimeout(fetchCount, 400);

    }

    [elFrom, elTo, elLocation].forEach(el => {

        if (el) el.addEventListener('change', onFilterChange);

    });

    // Run immediately on page load with current filter values.

    fetchCount();

})();

</script>

@endpush

{{-- ── Summary KPIs ── --}}

@if(!empty($summary))

<div class="stats-grid" style="grid-template-columns:repeat({{ min(count($summary), 4) }},1fr);margin-bottom:1rem">

    @foreach($summary as $label => $value)

    <div class="stat-card stat-gold">

        <div class="stat-info" style="width:100%">

            <div class="stat-value" style="font-size:1.2rem">{{ $value }}</div>

            <div class="stat-label">{{ $label }}</div>

        </div>

    </div>

    @endforeach

</div>

@endif

{{-- ── Data Table ── --}}

<div class="card">

    <div class="card-header">

        <span class="card-title">

            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>

            بيانات التقرير

        </span>

        @if(method_exists($data, 'total'))

        <span style="font-size:.78rem;color:var(--text-muted)">{{ number_format($data->total()) }} سجل</span>

        @endif

    </div>

    @if(($data instanceof \Illuminate\Pagination\LengthAwarePaginator && $data->isEmpty()) || ($data instanceof \Illuminate\Support\Collection && $data->isEmpty()))

    <div class="card-body">

        <div class="empty-state">

            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>

            <h3>لا توجد بيانات</h3>

            <p>لم يتم العثور على بيانات للفترة والمعايير المحددة</p>

        </div>

    </div>

    @else

    <div class="table-wrap" style="border:none;border-radius:0">

        <table class="data-table">

            @if(!empty($columns))

            <thead>

                <tr>

                    @foreach($columns as $col)

                    <th>{{ $col }}</th>

                    @endforeach

                </tr>

            </thead>

            @endif

            <tbody>

            @foreach($data as $row)

            <tr>

                {{-- ── Orders ── --}}

                @if($type === 'orders')

                    <td style="font-size:.78rem;color:var(--text-muted)">#{{ $row->id }}</td>

                    <td>{{ $row->customer?->name ?? '—' }}</td>

                    <td>{{ $row->location?->name ?? '—' }}</td>

                    <td>₪ {{ number_format($row->total_amount ?? 0, 2) }}</td>

                    @php
                        $orderStatus = (string) $enumValue($row->status ?? '');
                    @endphp
                    <td>
                        <span class="badge {{ $statusBadgeClass($orderStatus) }}">
                            {{ $statusLabel($orderStatus) }}
                        </span>
                    </td>

                    <td>{{ \Carbon\Carbon::parse($row->created_at)->format('Y/m/d') }}</td>

                {{-- ── Invoices ── --}}

                @elseif($type === 'invoices')

                    <td style="font-size:.78rem;color:var(--text-muted)">#{{ $row->id }}</td>

                    <td>{{ $row->customer?->name ?? '—' }}</td>

                    <td>{{ $row->location?->name ?? '—' }}</td>

                    <td>₪ {{ number_format($row->total_amount ?? 0, 2) }}</td>

                    <td>₪ {{ number_format($row->paid_amount ?? 0, 2) }}</td>

                    <td>₪ {{ number_format($row->remaining_amount ?? 0, 2) }}</td>

                    @php
                        $invoiceStatus = (string) $enumValue($row->status ?? '');
                    @endphp
                    <td>
                        <span class="badge {{ $statusBadgeClass($invoiceStatus) }}">
                            {{ $statusLabel($invoiceStatus) }}
                        </span>
                    </td>

                    <td>{{ \Carbon\Carbon::parse($row->issued_at)->format('Y/m/d') }}</td>

                {{-- ── Payments / Collections ── --}}

                @elseif(in_array($type, ['payments', 'collections']))

                    <td style="font-size:.78rem;color:var(--text-muted)">#{{ $row->id }}</td>

                    <td>{{ $row->invoice?->customer?->name ?? '—' }}</td>

                    <td>{{ $row->location?->name ?? '—' }}</td>

                    <td>₪ {{ number_format($row->amount ?? 0, 2) }}</td>

                    <td>{{ $row->payment_method ?? '—' }}</td>

                    @php
                        $paymentStatus = (string) $enumValue($row->status ?? '');
                    @endphp
                    <td>
                        <span class="badge {{ $statusBadgeClass($paymentStatus) }}">
                            {{ $paymentStatus === 'confirmed' ? 'مؤكدة' : $statusLabel($paymentStatus) }}
                        </span>
                    </td>

                    <td>{{ \Carbon\Carbon::parse($row->paid_at)->format('Y/m/d') }}</td>

                {{-- ── Daily / Monthly Sales ── --}}

                @elseif(in_array($type, ['daily-sales', 'monthly-sales']))

                    <td>{{ $row->sale_date ?? $row->sale_month ?? '—' }}</td>

                    <td>{{ number_format($row->invoice_count ?? 0) }}</td>

                    <td>₪ {{ number_format($row->total_sales ?? 0, 2) }}</td>

                    <td>₪ {{ number_format($row->total_paid ?? 0, 2) }}</td>

                {{-- ── Branch Sales ── --}}

                @elseif($type === 'branch-sales')

                    <td>{{ $row->branch_name ?? '—' }}</td>

                    <td>{{ number_format($row->invoice_count ?? 0) }}</td>

                    <td>₪ {{ number_format($row->total_sales ?? 0, 2) }}</td>

                    <td>₪ {{ number_format($row->total_paid ?? 0, 2) }}</td>

                {{-- ── Product Sales ── --}}

                @elseif($type === 'product-sales')

                    <td>{{ $row->product_name ?? '—' }}</td>

                    <td>{{ number_format($row->total_qty ?? 0, 2) }}</td>

                    <td>₪ {{ number_format($row->total_revenue ?? 0, 2) }}</td>

                {{-- ── Low Stock ── --}}

                @elseif($type === 'low-stock')

                    <td>{{ $row->product?->name ?? '—' }}</td>

                    <td>{{ $row->location?->name ?? '—' }}</td>

                    <td><span style="color:var(--error);font-weight:700">{{ $row->quantity }}</span></td>

                    <td>{{ $row->minimum_stock_level ?? '—' }}</td>

                {{-- ── Stock Movements ── --}}

                @elseif($type === 'stock-movements')

                    <td>{{ $row->product?->name ?? '—' }}</td>

                    <td>{{ $row->location?->name ?? '—' }}</td>

                    <td>{{ $row->movement_type?->label() ?? \App\Support\ArabicDisplay::status($row->movement_type?->value) }}</td>

                    <td>{{ $row->quantity ?? '—' }}</td>

                    <td>{{ \Carbon\Carbon::parse($row->created_at)->format('Y/m/d') }}</td>

                {{-- ── Stock Transfers ── --}}

                @elseif($type === 'stock-transfers')

                    <td>{{ $row->fromLocation?->name ?? '—' }}</td>

                    <td>{{ $row->toLocation?->name ?? '—' }}</td>

                    @php
                        $transferStatus = (string) $enumValue($row->status ?? '');
                    @endphp
                    <td>
                        <span class="badge {{ $statusBadgeClass($transferStatus) }}">
                            {{ $statusLabel($transferStatus) }}
                        </span>
                    </td>

                    <td>{{ $row->dispatchedBy?->employee?->full_name ?? '—' }}</td>

                    <td>{{ \Carbon\Carbon::parse($row->created_at)->format('Y/m/d') }}</td>

                {{-- ── Cash Sessions ── --}}

                @elseif($type === 'cash-sessions')

                    <td>{{ $row->location?->name ?? '—' }}</td>

                    <td>{{ $row->employee?->full_name ?? '—' }}</td>

                    <td>₪ {{ number_format($row->opening_balance ?? 0, 2) }}</td>

                    <td>₪ {{ number_format($row->cash_received ?? 0, 2) }}</td>

                    <td>₪ {{ number_format($row->actual_cash ?? 0, 2) }}</td>

                    <td style="color:{{ ($row->variance ?? 0) < 0 ? 'var(--error)' : 'var(--success)' }}">₪ {{ number_format($row->variance ?? 0, 2) }}</td>

                    @php
                        $cashStatus = (string) $enumValue($row->status ?? '');
                    @endphp
                    <td>
                        <span class="badge {{ $statusBadgeClass($cashStatus) }}">
                            {{ $statusLabel($cashStatus) }}
                        </span>
                    </td>

                    <td>{{ \Carbon\Carbon::parse($row->created_at)->format('Y/m/d') }}</td>

                {{-- ── Outstanding ── --}}

                @elseif($type === 'outstanding')

                    <td>{{ $row->customer?->name ?? '—' }}</td>

                    <td>#{{ $row->id }}</td>

                    <td>₪ {{ number_format($row->total_amount ?? 0, 2) }}</td>

                    <td>₪ {{ number_format($row->paid_amount ?? 0, 2) }}</td>

                    <td style="color:var(--error);font-weight:700">₪ {{ number_format($row->remaining_amount ?? 0, 2) }}</td>

                    <td>{{ \Carbon\Carbon::parse($row->issued_at)->format('Y/m/d') }}</td>

                {{-- ── Inventory ── --}}

                @elseif($type === 'inventory')

                    <td>{{ $row->product?->name ?? '—' }}</td>

                    <td>{{ $row->location?->name ?? '—' }}</td>

                    <td>{{ $row->quantity }}</td>

                    <td>{{ $row->minimum_stock_level ?? '—' }}</td>

                    <td>{{ $row->maximum_stock_level ?? '—' }}</td>

                {{-- ── Cake Orders ── --}}

                @elseif($type === 'cake-orders')

                    <td>#{{ $row->id }}</td>

                    <td>{{ $row->customer?->name ?? '—' }}</td>

                    <td>{{ $row->originBranch?->name ?? '—' }}</td>

                    @php
                        $transferStatus = (string) $enumValue($row->status ?? '');
                    @endphp
                    <td>
                        <span class="badge {{ $statusBadgeClass($transferStatus) }}">
                            {{ $statusLabel($transferStatus) }}
                        </span>
                    </td>

                    <td>{{ $row->required_date ? \Carbon\Carbon::parse($row->required_date)->format('Y/m/d') : '—' }}</td>

                    <td>{{ \Carbon\Carbon::parse($row->created_at)->format('Y/m/d') }}</td>

                {{-- ── Unified Cake Production ── --}}

                @elseif($type === 'cake-production')

                    <td>{{ $row->cake_type ?? 'غير محدد' }}</td>

                    <td>{{ $row->cake_size ?? 'غير محدد' }}</td>

                    <td>{{ $row->shape ?? 'غير محدد' }}</td>

                    <td>{{ number_format((int) ($row->special_quantity ?? 0)) }}</td>

                    <td>{{ number_format((int) ($row->showroom_quantity ?? 0)) }}</td>

                    <td style="font-weight:900;color:var(--theme-primary)">
                        {{ number_format((int) ($row->total_quantity ?? 0)) }}
                    </td>

                {{-- ── Activity Logs ── --}}

                @elseif($type === 'activity-logs')

                    <td>{{ $row->user?->display_name ?? '—' }}</td>

                    <td>{{ $row->action }}</td>

                    <td style="font-size:.78rem;color:var(--text-muted)">{{ $row->module }} / {{ $row->record_type }}</td>

                    <td>{{ \Carbon\Carbon::parse($row->created_at)->format('Y/m/d H:i') }}</td>

                {{-- ── Fallback ── --}}

                @else

                    @foreach((array) $row as $val)

                    <td>{{ is_array($val) || is_object($val) ? json_encode($val) : $val }}</td>

                    @endforeach

                @endif

            </tr>

            @endforeach

            </tbody>

        </table>

    </div>





    <div>

    {{ $data->withQueryString()->links() }}

    </div>

    @endif

</div>

@endsection
