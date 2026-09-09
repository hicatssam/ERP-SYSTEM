@extends('layouts.app')

@section('title', 'طلبات الكيك')
@section('page-title', 'طلبات الكيك الخاصة')

@section('content')

@php
    /*
    |--------------------------------------------------------------------------
    | جميع حالات الكيك الحالية بالعربي
    |--------------------------------------------------------------------------
    */

    $statusLabels = collect(\App\Enums\CakeOrderStatus::cases())
        ->mapWithKeys(
            fn ($status) => [
                $status->value => $status->label()
            ]
        )
        ->all();


    /*
    |--------------------------------------------------------------------------
    | دعم الحالات القديمة الموجودة بقاعدة البيانات
    |--------------------------------------------------------------------------
    */

    $legacyStatusLabels = [
        'pending_deposit' => 'بانتظار دفع العربون',
        'deposit_paid' => 'تم دفع العربون',

        'in_decoration' => 'قيد التزيين',

        'dispatched_to_branch' => 'تم الإرسال إلى الفرع',

        'received_at_branch' => 'تم الاستلام في الفرع',

        'ready_for_pickup' => 'جاهز لاستلام العميل',

        'delivered' => 'مكتمل',

        'canceled' => 'ملغى',
    ];


    /*
    |--------------------------------------------------------------------------
    | دمج جميع المسميات
    |--------------------------------------------------------------------------
    */

    $statusLabels = array_merge(
        $legacyStatusLabels,
        $statusLabels
    );


    /*
    |--------------------------------------------------------------------------
    | ألوان الحالات
    |--------------------------------------------------------------------------
    */

    $statusClasses = [

        'draft' => 'status-neutral',

        'pending_deposit' => 'status-warning',
        'deposit_paid' => 'status-info',

        'pending_factory_review' => 'status-warning',

        'accepted' => 'status-info',

        'scheduled' => 'status-purple',

        'in_preparation' => 'status-progress',

        'decorating' => 'status-pink',
        'in_decoration' => 'status-pink',

        'quality_check' => 'status-quality',

        'ready' => 'status-ready',

        'sent_to_branch' => 'status-delivery',
        'dispatched_to_branch' => 'status-delivery',

        'received_by_branch' => 'status-received',
        'received_at_branch' => 'status-received',

        'ready_for_customer' => 'status-ready',

        'completed' => 'status-success',
        'delivered' => 'status-success',

        'rejected' => 'status-danger',

        'cancelled' => 'status-danger',
        'canceled' => 'status-danger',
    ];


    /*
    |--------------------------------------------------------------------------
    | ترتيب الحالات في الفلتر
    |--------------------------------------------------------------------------
    */

    $filterStatuses = [

        'draft',

        'pending_factory_review',

        'accepted',

        'scheduled',

        'in_preparation',

        'decorating',

        'quality_check',

        'ready',

        'sent_to_branch',

        'received_by_branch',

        'ready_for_customer',

        'completed',

        'rejected',

        'cancelled',
    ];
@endphp


{{-- =========================================================
     رأس الصفحة
========================================================= --}}

<div class="page-actions cake-list-header">

    <div>

        <div class="page-actions-title">
            طلبات الكيك الخاصة
        </div>

        <p class="cake-list-subtitle">
            متابعة طلبات الكيك الخاصة من الإنشاء وحتى التجهيز والتزيين والجودة والتوصيل والاستلام.
        </p>

    </div>


    <div class="action-btns">

        @can('cake_orders.create')

            <a
                href="{{ route('cake-orders.create') }}"
                class="btn btn-gold"
            >

                <span class="add-icon">
                    +
                </span>

                طلب كيك جديد

            </a>

        @endcan

    </div>

</div>


{{-- =========================================================
     الرسائل
========================================================= --}}

@if(session('success'))

    <div class="cake-alert cake-alert-success">
        {{ session('success') }}
    </div>

@endif


@if(session('error'))

    <div class="cake-alert cake-alert-danger">
        {{ session('error') }}
    </div>

@endif


{{-- =========================================================
     ملخص الطلبات
========================================================= --}}

<div class="cake-summary">

    <div class="summary-card">

        <span>
            إجمالي الطلبات
        </span>

        <strong>
            {{ $orders->total() }}
        </strong>

    </div>


    <div class="summary-card">

        <span>
            المعروض في الصفحة
        </span>

        <strong>
            {{ $orders->count() }}
        </strong>

    </div>


    <div class="summary-card summary-filter">

        <span>
            حالة التصفية
        </span>

        <strong>
            {{
                request()->hasAny([
                    'status',
                    'date_from'
                ])
                    ? 'مفعّلة'
                    : 'كل الطلبات'
            }}
        </strong>

    </div>

</div>


{{-- =========================================================
     التصفية
========================================================= --}}

<div class="card cake-filter-card">

    <div class="card-body">

        <form
            method="GET"
            action="{{ route('cake-orders.index') }}"
            class="filter-grid"
        >

            <div class="filter-group">

                <label class="filter-label">
                    حالة الطلب
                </label>


                <select
                    name="status"
                    class="form-select"
                >

                    <option value="">
                        جميع الحالات
                    </option>


                    @foreach($filterStatuses as $value)

                        @if(isset($statusLabels[$value]))

                            <option
                                value="{{ $value }}"
                                @selected(
                                    request('status') === $value
                                )
                            >
                                {{ $statusLabels[$value] }}
                            </option>

                        @endif

                    @endforeach

                </select>

            </div>


            <div class="filter-group">

                <label class="filter-label">
                    تاريخ التسليم من
                </label>

                <input
                    type="date"
                    name="date_from"
                    class="form-input"
                    value="{{ request('date_from') }}"
                >

            </div>


            <div class="filter-actions">

                <button
                    class="btn btn-gold btn-sm"
                    type="submit"
                >
                    تطبيق التصفية
                </button>


                @if(
                    request()->hasAny([
                        'status',
                        'date_from'
                    ])
                )

                    <a
                        href="{{ route('cake-orders.index') }}"
                        class="btn btn-ghost btn-sm"
                    >
                        مسح التصفية
                    </a>

                @endif

            </div>

        </form>

    </div>

</div>


{{-- =========================================================
     عنوان القائمة
========================================================= --}}

<div class="cake-cards-header">

    <span class="card-title">
        قائمة الطلبات
    </span>

    <span class="results-count">
        {{ $orders->total() }}
        طلب
    </span>

</div>


{{-- =========================================================
     بطاقات الطلبات
========================================================= --}}

<div class="cake-cards-grid">

@forelse($orders as $order)

    @php

        /*
        |--------------------------------------------------------------------------
        | حالة الطلب
        |--------------------------------------------------------------------------
        */

        $statusValue =
            $order->status instanceof \BackedEnum
                ? $order->status->value
                : (string) $order->status;


        /*
         * لا نُظهر الاسم التقني الإنجليزي إطلاقًا.
         */
        $statusLabel =
            $statusLabels[$statusValue]
            ?? 'حالة غير معرفة';


        $statusClass =
            $statusClasses[$statusValue]
            ?? 'status-neutral';


        /*
        |--------------------------------------------------------------------------
        | صور الكيك
        |--------------------------------------------------------------------------
        |
        | الأولوية:
        |
        | 1. صورة الكيك النهائي
        | 2. تصميم العميل
        | 3. الصورة المرجعية
        | 4. أي صورة أخرى
        |
        */

        $imageExtensions = [
            'jpg',
            'jpeg',
            'png',
            'webp',
        ];


        $imageAttachments =
            $order->attachments
                ->filter(
                    function ($attachment) use ($imageExtensions) {

                        $extension =
                            strtolower(
                                pathinfo(
                                    (string) $attachment->file_path,
                                    PATHINFO_EXTENSION
                                )
                            );

                        return in_array(
                            $extension,
                            $imageExtensions,
                            true
                        );
                    }
                );


        $cakeAttachment =

            $imageAttachments->firstWhere(
                'attachment_type',
                'final_cake_image'
            )

            ??

            $imageAttachments->firstWhere(
                'attachment_type',
                'customer_design'
            )

            ??

            $imageAttachments->firstWhere(
                'attachment_type',
                'reference_image'
            )

            ??

            $imageAttachments->first();


        $cakeImage = null;


        if($cakeAttachment?->file_path) {

            $imagePath =
                ltrim(
                    $cakeAttachment->file_path,
                    '/'
                );


            if(
                str_starts_with(
                    $imagePath,
                    'http://'
                )
                ||
                str_starts_with(
                    $imagePath,
                    'https://'
                )
            ) {

                $cakeImage =
                    $imagePath;

            }

            elseif(
                str_starts_with(
                    $imagePath,
                    'storage/'
                )
            ) {

                $cakeImage =
                    asset(
                        $imagePath
                    );

            }

            else {

                $cakeImage =
                    asset(
                        'storage/'
                        .
                        $imagePath
                    );

            }
        }


        /*
        |--------------------------------------------------------------------------
        | سعر الطلب
        |--------------------------------------------------------------------------
        */

        $displayPrice =
            (float) (
                $order->net_price
                ??
                $order->total_price
                ??
                0
            );
    @endphp


    <a
        href="{{ route('cake-orders.show', $order) }}"
        class="cake-card"
    >

        {{-- =====================================================
             الصورة
        ====================================================== --}}

        <div class="cake-card-img">

            @if($cakeImage)

                <img
                    src="{{ $cakeImage }}"
                    alt="صورة طلب الكيك {{ $order->order_number }}"
                    loading="lazy"
                >

            @else

                <div class="cake-card-img-placeholder">

                    <svg
                        width="40"
                        height="40"
                        viewBox="0 0 24 24"
                        fill="none"
                    >

                        <path
                            d="M4 20h16M5 20v-6a2 2 0 012-2h10a2 2 0 012 2v6M9 12V8a3 3 0 016 0v4M12 4v2"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />

                    </svg>

                    <span>
                        لا توجد صورة
                    </span>

                </div>

            @endif


            {{-- الحالة --}}
            <span
                class="
                    order-status
                    cake-card-status
                    {{ $statusClass }}
                "
            >
                {{ $statusLabel }}
            </span>

        </div>


        {{-- =====================================================
             جسم البطاقة
        ====================================================== --}}

        <div class="cake-card-body">

            <div class="cake-card-top">

                <strong class="order-number">

                    {{
                        $order->order_number
                        ??
                        'طلب رقم ' . $order->id
                    }}

                </strong>


                <strong class="price-value">
                    ₪{{ number_format($displayPrice, 2) }}
                </strong>

            </div>


            {{-- العميل --}}
            <div class="cake-card-customer">

                <span class="customer-avatar">

                    {{
                        mb_substr(
                            $order->customer?->name
                            ?? 'ع',
                            0,
                            1
                        )
                    }}

                </span>


                <span>

                    <strong>
                        {{ $order->customer?->name ?? 'بدون اسم' }}
                    </strong>

                    @if($order->customer?->phone)

                        <small>
                            {{ $order->customer->phone }}
                        </small>

                    @endif

                </span>

            </div>


            {{-- معلومات الطلب --}}
            <div class="cake-card-meta">

                <div class="meta-item">

                    <small>
                        الفرع
                    </small>

                    <span>
                        {{ $order->originBranch?->name ?? 'غير محدد' }}
                    </span>

                </div>


                <div class="meta-item">

                    <small>
                        موعد التسليم
                    </small>

                    <span>

                        {{
                            $order->required_date
                                ? $order->required_date->format('Y-m-d')
                                : 'غير محدد'
                        }}


                        @if($order->required_time)

                            ·

                            {{
                                \Carbon\Carbon::parse(
                                    $order->required_time
                                )->format('H:i')
                            }}

                        @endif

                    </span>

                </div>

            </div>


            {{-- =================================================
                 معلومات المرحلة الحالية
            ================================================== --}}

            <div class="cake-current-stage">

                <span class="stage-label">
                    المرحلة الحالية
                </span>

                <strong>
                    {{ $statusLabel }}
                </strong>

            </div>


            {{-- =================================================
                 أسفل البطاقة
            ================================================== --}}

            <div class="cake-card-footer">

                <small>

                    تم إنشاء الطلب:

                    {{
                        $order->created_at
                            ? $order->created_at->format('Y-m-d H:i')
                            : 'غير محدد'
                    }}

                </small>


                @if($statusValue === 'draft')

                    @can('cake_orders.edit')

                        <span
                            class="edit-hint"
                            onclick="
                                event.preventDefault();
                                window.location='{{ route('cake-orders.edit', $order) }}';
                            "
                        >
                            تعديل الطلب
                        </span>

                    @endcan

                @endif

            </div>

        </div>

    </a>


@empty

    <div class="empty-state cake-empty-full">

        <strong>
            لا توجد طلبات كيك
        </strong>

        <span>
            جرّب تغيير عوامل التصفية أو أنشئ طلب كيك جديدًا.
        </span>

    </div>

@endforelse

</div>


{{-- =========================================================
     ترقيم الصفحات
========================================================= --}}

<div style="margin-top:1rem">

    {{ $orders->withQueryString()->links() }}

</div>


{{-- =========================================================
     التنسيق
========================================================= --}}

<style>

.cake-list-header {
    align-items:flex-end;
    margin-bottom:1.25rem;
}

.cake-list-subtitle {
    margin:.3rem 0 0;

    color:var(--text-muted);

    font-size:.86rem;

    line-height:1.7;
}

.add-icon {
    font-size:1.2rem;
    line-height:1;
}


/* =========================================================
   الرسائل
========================================================= */

.cake-alert {
    margin-bottom:1rem;

    padding:1rem 1.2rem;

    border-radius:12px;
}

.cake-alert-success {
    color:#198754;

    background:
        rgba(25,135,84,.1);

    border:
        1px solid
        rgba(25,135,84,.35);
}

.cake-alert-danger {
    color:#dc3545;

    background:
        rgba(220,53,69,.1);

    border:
        1px solid
        rgba(220,53,69,.35);
}


/* =========================================================
   الملخص
========================================================= */

.cake-summary {

    display:grid;

    grid-template-columns:
        repeat(
            3,
            minmax(0,1fr)
        );

    gap:1rem;

    margin-bottom:1rem;
}


.summary-card {

    display:flex;
    align-items:center;
    justify-content:space-between;

    padding:1rem 1.1rem;

    background:var(--surface);

    border:
        1px solid
        var(--border);

    border-radius:12px;
}


.summary-card span {

    color:var(--text-muted);

    font-size:.8rem;
}


.summary-card strong {

    color:var(--gold);

    font-size:1.2rem;
}


.summary-filter strong {
    font-size:.9rem;
}


/* =========================================================
   التصفية
========================================================= */

.cake-filter-card {
    margin-bottom:1rem;
}


.filter-grid {

    display:grid;

    grid-template-columns:
        1fr
        1fr
        auto;

    gap:1rem;

    align-items:end;
}


.filter-group {
    display:grid;
    gap:.4rem;
}


.filter-actions {

    display:flex;

    gap:.5rem;

    align-items:center;
}


/* =========================================================
   رأس قائمة الطلبات
========================================================= */

.cake-cards-header {

    display:flex;
    align-items:center;
    justify-content:space-between;

    margin-bottom:.75rem;
}


.cake-cards-header .card-title {
    font-weight:800;
}


.results-count {
    color:var(--text-muted);
    font-size:.78rem;
}


/* =========================================================
   شبكة الطلبات
========================================================= */

.cake-cards-grid {

    display:grid;

    grid-template-columns:
        repeat(
            auto-fill,
            minmax(260px,1fr)
        );

    gap:1rem;
}


.cake-card {

    display:flex;
    flex-direction:column;

    background:var(--surface);

    border:
        1px solid
        var(--border);

    border-radius:14px;

    overflow:hidden;

    text-decoration:none;

    color:inherit;

    transition:
        transform .15s ease,
        box-shadow .15s ease,
        border-color .15s ease;
}


.cake-card:hover {

    transform:
        translateY(-3px);

    border-color:
        rgba(212,160,23,.35);

    box-shadow:
        0 10px 24px
        rgba(0,0,0,.09);
}


/* =========================================================
   الصورة
========================================================= */

.cake-card-img {

    position:relative;

    aspect-ratio:4/3;

    background:
        rgba(212,175,55,.08);

    overflow:hidden;
}


.cake-card-img img {

    width:100%;
    height:100%;

    object-fit:cover;

    display:block;

    transition:
        transform .25s ease;
}


.cake-card:hover
.cake-card-img img {

    transform:
        scale(1.04);
}


.cake-card-img-placeholder {

    width:100%;
    height:100%;

    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;

    gap:.4rem;

    color:var(--text-muted);
}


.cake-card-img-placeholder span {
    font-size:.75rem;
}


/* =========================================================
   حالة الطلب
========================================================= */

.cake-card-status {

    position:absolute;

    top:.6rem;
    right:.6rem;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,.15);
}


.order-status {

    display:inline-flex;
    align-items:center;

    padding:.3rem .6rem;

    border-radius:999px;

    font-size:.68rem;
    font-weight:800;

    white-space:nowrap;
}


.status-neutral {
    color:#6c757d;
    background:rgba(108,117,125,.14);
}


.status-warning {
    color:#9a6700;
    background:rgba(255,193,7,.18);
}


.status-info {
    color:#0d6efd;
    background:rgba(13,110,253,.13);
}


.status-purple {
    color:#7c3aed;
    background:rgba(124,58,237,.13);
}


.status-progress {
    color:#d97706;
    background:rgba(249,115,22,.14);
}


.status-pink {
    color:#be185d;
    background:rgba(236,72,153,.13);
}


.status-quality {
    color:#0891b2;
    background:rgba(6,182,212,.13);
}


.status-ready {
    color:#15803d;
    background:rgba(34,197,94,.14);
}


.status-delivery {
    color:#2563eb;
    background:rgba(37,99,235,.14);
}


.status-received {
    color:#0f766e;
    background:rgba(20,184,166,.14);
}


.status-success {
    color:#198754;
    background:rgba(25,135,84,.14);
}


.status-danger {
    color:#dc3545;
    background:rgba(220,53,69,.13);
}


/* =========================================================
   جسم البطاقة
========================================================= */

.cake-card-body {

    padding:.9rem 1rem;

    display:flex;
    flex-direction:column;

    gap:.65rem;
}


.cake-card-top {

    display:flex;
    align-items:center;
    justify-content:space-between;

    gap:.5rem;
}


.cake-card-top .order-number {

    color:var(--gold);

    font-weight:800;

    font-size:.95rem;
}


.cake-card-top .price-value {
    white-space:nowrap;
}


/* =========================================================
   العميل
========================================================= */

.cake-card-customer {

    display:flex;
    align-items:center;

    gap:.6rem;
}


.cake-card-customer small {

    display:block;

    margin-top:.15rem;

    color:var(--text-muted);

    font-size:.7rem;
}


.customer-avatar {

    width:34px;
    height:34px;

    flex:0 0 34px;

    display:flex;
    align-items:center;
    justify-content:center;

    color:var(--gold);

    background:
        rgba(212,175,55,.12);

    border-radius:50%;

    font-weight:800;
}


/* =========================================================
   بيانات البطاقة
========================================================= */

.cake-card-meta {

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:.6rem;

    padding-top:.5rem;

    border-top:
        1px dashed
        var(--border);
}


.meta-item {

    display:flex;
    flex-direction:column;

    gap:.2rem;

    min-width:0;
}


.meta-item small {

    color:var(--text-muted);

    font-size:.68rem;
}


.meta-item span {

    font-size:.82rem;
    font-weight:600;

    overflow:hidden;

    text-overflow:ellipsis;

    white-space:nowrap;
}


/* =========================================================
   المرحلة الحالية
========================================================= */

.cake-current-stage {

    display:flex;
    align-items:center;
    justify-content:space-between;

    gap:.5rem;

    padding:.55rem .65rem;

    border-radius:9px;

    background:
        rgba(212,160,23,.055);

    border:
        1px solid
        rgba(212,160,23,.14);
}


.cake-current-stage
.stage-label {

    color:var(--text-muted);

    font-size:.65rem;
}


.cake-current-stage strong {

    color:var(--gold-deep);

    font-size:.72rem;
}


/* =========================================================
   أسفل البطاقة
========================================================= */

.cake-card-footer {

    display:flex;
    align-items:center;
    justify-content:space-between;

    gap:.5rem;

    padding-top:.4rem;
}


.cake-card-footer small {

    color:var(--text-muted);

    font-size:.67rem;
}


.edit-hint {

    font-size:.75rem;

    color:var(--gold);

    font-weight:700;

    cursor:pointer;
}


/* =========================================================
   الحالة الفارغة
========================================================= */

.empty-state {

    display:flex;
    align-items:center;
    flex-direction:column;

    gap:.35rem;

    padding:3rem;

    color:var(--text-muted);
}


.empty-state strong {

    color:var(--text);

    font-size:1rem;
}


.cake-empty-full {

    grid-column:1/-1;

    background:var(--surface);

    border:
        1px solid
        var(--border);

    border-radius:14px;
}


/* =========================================================
   Responsive
========================================================= */

@media(max-width:800px) {

    .cake-list-header {

        align-items:flex-start;

        flex-direction:column;
    }


    .cake-summary {
        grid-template-columns:1fr;
    }


    .filter-grid {
        grid-template-columns:1fr;
    }


    .cake-cards-grid {

        grid-template-columns:
            repeat(
                auto-fill,
                minmax(150px,1fr)
            );

        gap:.75rem;
    }


    .cake-card-body {
        padding:.7rem;
    }


    .meta-item span {
        font-size:.75rem;
    }


    .cake-card-meta {
        grid-template-columns:1fr;
    }


    .cake-current-stage {

        align-items:flex-start;

        flex-direction:column;
    }
}

</style>

@endsection