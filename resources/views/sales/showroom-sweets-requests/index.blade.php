@extends('layouts.app')

@section('title', 'طلبات حلويات الفروع')

@push('styles')
<style>
    /* =========================================================
       رأس الصفحة
    ========================================================= */

    .sweets-page-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.25rem;
    }

    .sweets-page-title {
        font-size: 1.35rem;
        font-weight: 800;
        color: var(--text);
    }

    .sweets-page-subtitle {
        margin-top: .3rem;
        color: var(--text-muted);
        font-size: .84rem;
        line-height: 1.8;
    }


    /* =========================================================
       الفلاتر
    ========================================================= */

    .sweets-filter-box {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 1rem;
        margin-bottom: 1.25rem;
    }

    .sweets-filter-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(180px, 1fr));
        gap: .85rem;
        align-items: end;
    }

    .sweets-filter-actions {
        display: flex;
        align-items: center;
        gap: .5rem;
        flex-wrap: wrap;
    }


    /* =========================================================
       قائمة الطلبات
    ========================================================= */

    .sweets-requests-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }


    /* =========================================================
       بطاقة الطلب
    ========================================================= */

    .sweets-request-card {
        position: relative;

        --request-accent: #d6a900;
        --request-soft: rgba(214, 169, 0, .075);
        --request-border: rgba(214, 169, 0, .22);

        background: #fff;

        border: 1px solid var(--border);
        border-radius: 18px;

        overflow: hidden;

        box-shadow:
            0 3px 12px
            rgba(15, 23, 42, .045);

        transition:
            transform .18s ease,
            border-color .18s ease,
            box-shadow .18s ease;
    }


    /* الشريط الجانبي الذي يوضح حالة الطلب */
    .sweets-request-card::before {
        content: '';

        position: absolute;

        top: 0;
        right: 0;

        width: 5px;
        height: 100%;

        background: var(--request-accent);

        z-index: 3;
    }


    .sweets-request-card:hover {
        transform: translateY(-3px);

        border-color: var(--request-border);

        box-shadow:
            0 10px 28px
            rgba(15, 23, 42, .08);
    }


    /* =========================================================
       ألوان الحالات
    ========================================================= */

    /* مرسل للمصنع */
    .sweets-request-card.tone-gold {
        --request-accent: #d6a900;
        --request-soft: rgba(214, 169, 0, .075);
        --request-border: rgba(214, 169, 0, .23);
    }


    /* قيد التجهيز */
    .sweets-request-card.tone-blue {
        --request-accent: #2563eb;
        --request-soft: rgba(37, 99, 235, .065);
        --request-border: rgba(37, 99, 235, .19);
    }


    /* تم التنفيذ */
    .sweets-request-card.tone-green {
        --request-accent: #16a34a;
        --request-soft: rgba(22, 163, 74, .065);
        --request-border: rgba(22, 163, 74, .19);
    }


    /* مرفوض */
    .sweets-request-card.tone-red {
        --request-accent: #dc2626;
        --request-soft: rgba(220, 38, 38, .06);
        --request-border: rgba(220, 38, 38, .18);
    }


    /* ملغي */
    .sweets-request-card.tone-gray {
        --request-accent: #64748b;
        --request-soft: rgba(100, 116, 139, .065);
        --request-border: rgba(100, 116, 139, .18);
    }


    /* =========================================================
       أعلى البطاقة
    ========================================================= */

    .request-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;

        gap: 1rem;

        padding: 1rem 1.15rem;

        background:
            linear-gradient(
                270deg,
                var(--request-soft) 0%,
                rgba(255, 255, 255, .97) 72%
            );

        border-bottom:
            1px solid
            var(--request-border);
    }


    .request-number-wrap {
        display: flex;
        align-items: center;

        gap: .75rem;

        min-width: 0;
    }


    .request-icon {
        width: 44px;
        height: 44px;

        display: flex;
        align-items: center;
        justify-content: center;

        flex: 0 0 44px;

        border-radius: 12px;

        color: var(--request-accent);

        background: var(--request-soft);

        border:
            1px solid
            var(--request-border);
    }


    .request-icon svg {
        width: 22px;
        height: 22px;
    }


    .request-number {
        color: var(--text);

        font-size: 1rem;
        font-weight: 800;
    }


    .request-created {
        margin-top: .18rem;

        color: var(--text-muted);

        font-size: .71rem;
    }


    .request-head-side {
        display: flex;
        align-items: center;

        gap: .65rem;

        flex-wrap: wrap;
    }


    /* Badge الحالة */
    .request-status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;

        min-height: 30px;

        padding: .35rem .75rem;

        border-radius: 999px;

        color: var(--request-accent);

        background: var(--request-soft);

        border:
            1px solid
            var(--request-border);

        font-size: .7rem;
        font-weight: 800;
    }


    /* =========================================================
       جسم البطاقة
    ========================================================= */

    .request-card-body {
        padding: 1.1rem;
    }


    /* =========================================================
       معلومات الطلب
    ========================================================= */

    .request-meta-grid {
        display: grid;

        grid-template-columns:
            minmax(180px, 1fr)
            minmax(180px, 1fr)
            minmax(140px, .7fr)
            minmax(120px, .6fr);

        gap: .75rem;

        margin-bottom: 1rem;
    }


    .request-meta {
        min-height: 72px;

        display: flex;
        align-items: center;

        gap: .65rem;

        padding: .78rem .9rem;

        background: #fafbfc;

        border: 1px solid #e8ebef;

        border-radius: 13px;

        transition:
            background .18s ease,
            border-color .18s ease,
            transform .18s ease;
    }


    .request-meta:hover {
        background: var(--request-soft);

        border-color:
            var(--request-border);

        transform: translateY(-1px);
    }


    .request-meta-icon {
        width: 36px;
        height: 36px;

        display: flex;
        align-items: center;
        justify-content: center;

        flex: 0 0 36px;

        border-radius: 10px;

        color: var(--request-accent);

        background: var(--request-soft);

        border:
            1px solid
            var(--request-border);
    }


    .request-meta-icon svg {
        width: 18px;
        height: 18px;
    }


    .request-meta-label {
        color: var(--text-muted);

        font-size: .67rem;

        margin-bottom: .15rem;
    }


    .request-meta-value {
        color: var(--text);

        font-size: .82rem;
        font-weight: 700;

        line-height: 1.5;
    }


    /* =========================================================
       عنوان الأصناف
    ========================================================= */

    .request-items-title {
        display: flex;
        align-items: center;
        justify-content: space-between;

        gap: .75rem;

        margin-bottom: .65rem;
    }


    .request-items-title strong {
        color: var(--text);

        font-size: .85rem;
        font-weight: 800;
    }


    .request-items-count {
        color: var(--text-muted);

        font-size: .7rem;
    }


    /* =========================================================
       الأصناف
    ========================================================= */

    .request-items {
        display: grid;

        grid-template-columns:
            repeat(4, minmax(0, 1fr));

        gap: .7rem;
    }


    .request-product {
        display: flex;
        align-items: center;

        gap: .7rem;

        min-width: 0;

        padding: .7rem;

        background: #fff;

        border:
            1px solid
            #e8ebef;

        border-radius: 13px;

        box-shadow:
            0 2px 7px
            rgba(15, 23, 42, .035);

        transition:
            transform .16s ease,
            border-color .16s ease,
            box-shadow .16s ease,
            background .16s ease;
    }


    .request-product:hover {
        transform: translateY(-2px);

        background:
            var(--request-soft);

        border-color:
            var(--request-border);

        box-shadow:
            0 6px 15px
            rgba(15, 23, 42, .065);
    }


    /* =========================================================
       صورة المنتج
    ========================================================= */

    .request-product-image {
        width: 68px;
        height: 68px;

        flex: 0 0 68px;

        border-radius: 11px;

        overflow: hidden;

        background: #f7f8fa;

        border:
            1px solid
            #e6e9ed;
    }


    .request-product-image img {
        display: block;

        width: 100%;
        height: 100%;

        object-fit: cover;

        transition:
            transform .25s ease;
    }


    .request-product:hover
    .request-product-image img {
        transform: scale(1.06);
    }


    .request-product-placeholder {
        width: 100%;
        height: 100%;

        display: flex;
        align-items: center;
        justify-content: center;

        color: #9ca3af;

        background:
            linear-gradient(
                135deg,
                #fafafa,
                #f1f3f5
            );
    }


    .request-product-placeholder svg {
        width: 25px;
        height: 25px;
    }


    /* =========================================================
       معلومات المنتج
    ========================================================= */

    .request-product-info {
        min-width: 0;
        flex: 1;
    }


    .request-product-name {
        color: var(--text);

        font-size: .79rem;
        font-weight: 800;

        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }


    .request-product-qty {
        display: inline-flex;
        align-items: center;

        gap: .3rem;

        margin-top: .35rem;

        color: var(--request-accent);

        font-size: .72rem;
        font-weight: 800;
    }


    .request-product-note {
        margin-top: .22rem;

        color: var(--text-muted);

        font-size: .64rem;

        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }


    /* =========================================================
       أصناف إضافية
    ========================================================= */

    .more-products {
        min-height: 82px;

        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;

        border:
            1px dashed
            var(--request-border);

        border-radius: 13px;

        background:
            var(--request-soft);

        color:
            var(--request-accent);
    }


    .more-products strong {
        font-size: 1.1rem;
        font-weight: 900;
    }


    .more-products span {
        margin-top: .15rem;

        font-size: .67rem;
    }


    /* =========================================================
       ملاحظات الطلب
    ========================================================= */

    .request-notes {
        display: flex;
        align-items: flex-start;

        gap: .55rem;

        margin-top: .85rem;

        padding: .75rem .9rem;

        border-radius: 11px;

        background:
            var(--request-soft);

        border:
            1px solid
            var(--request-border);

        color:
            var(--text-muted);

        font-size: .72rem;
        line-height: 1.8;
    }


    .request-notes svg {
        width: 16px;
        height: 16px;

        flex: 0 0 16px;

        margin-top: 3px;

        color:
            var(--request-accent);
    }


    /* =========================================================
       أسفل البطاقة
    ========================================================= */

    .request-card-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;

        gap: .75rem;

        flex-wrap: wrap;

        padding: .8rem 1.1rem;

        background:
            linear-gradient(
                270deg,
                var(--request-soft),
                #fafbfc 45%
            );

        border-top:
            1px solid
            var(--request-border);
    }


    .request-footer-info {
        color: var(--text-muted);

        font-size: .7rem;
    }


    .request-footer-info strong {
        color: var(--text);
        font-weight: 700;
    }


    .request-actions {
        display: flex;
        align-items: center;

        gap: .45rem;

        flex-wrap: wrap;
    }


    .request-view-btn {
        display: inline-flex;
        align-items: center;

        gap: .35rem;
    }


    .request-view-btn svg {
        width: 15px;
        height: 15px;
    }


    /* =========================================================
       الحالة الفارغة
    ========================================================= */

    .sweets-empty {
        min-height: 270px;

        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;

        text-align: center;

        background: var(--surface);

        border:
            1px dashed
            var(--border);

        border-radius: 18px;
    }


    .sweets-empty-icon {
        width: 64px;
        height: 64px;

        display: flex;
        align-items: center;
        justify-content: center;

        margin-bottom: .75rem;

        border-radius: 18px;

        color: var(--gold);

        background:
            rgba(212, 175, 55, .1);

        border:
            1px solid
            rgba(212, 175, 55, .16);
    }


    .sweets-empty-icon svg {
        width: 30px;
        height: 30px;
    }


    .sweets-empty-title {
        color: var(--text);

        font-size: .95rem;
        font-weight: 800;
    }


    .sweets-empty-text {
        margin-top: .3rem;

        color: var(--text-muted);

        font-size: .75rem;
    }


    /* =========================================================
       Responsive
    ========================================================= */

    @media (max-width: 1200px) {

        .request-items {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .request-meta-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }
    }


    @media (max-width: 768px) {

        .sweets-filter-grid {
            grid-template-columns: 1fr;
        }


        .sweets-page-head {
            align-items: stretch;
            flex-direction: column;
        }


        .sweets-page-head > a {
            align-self: flex-start;
        }


        .request-card-head {
            align-items: flex-start;
            flex-direction: column;
        }


        .request-meta-grid {
            grid-template-columns: 1fr;
        }


        .request-items {
            grid-template-columns: 1fr;
        }


        .request-card-footer {
            align-items: stretch;
            flex-direction: column;
        }


        .request-actions {
            width: 100%;
        }


        .request-actions .btn {
            flex: 1;
            justify-content: center;
        }
    }
</style>
@endpush


@section('content')

{{-- =========================================================
     رأس الصفحة
========================================================= --}}

<div class="sweets-page-head">

    <div>

        <div class="sweets-page-title">
            طلبات حلويات الفروع
        </div>

        <div class="sweets-page-subtitle">
            متابعة طلبات النمورة والبقلاوة والكنافة وباقي أصناف الحلويات
            المرسلة من الفروع إلى المصنع.
        </div>

    </div>


    @can('showroom_sweets_requests.create')

        <a
            href="{{ route('showroom-sweets-requests.create') }}"
            class="btn btn-gold"
        >
            + طلب حلويات جديد
        </a>

    @endcan

</div>


{{-- =========================================================
     الفلاتر
========================================================= --}}

<div class="sweets-filter-box">

    <form
        method="GET"
        action="{{ route('showroom-sweets-requests.index') }}"
        class="sweets-filter-grid"
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
                    كل الحالات
                </option>

                @foreach($statusEnum as $status)

                    <option
                        value="{{ $status->value }}"
                        {{ request('status') === $status->value ? 'selected' : '' }}
                    >
                        {{ $status->label() }}
                    </option>

                @endforeach

            </select>

        </div>


        @if($canViewAll)

            <div class="filter-group">

                <label class="filter-label">
                    الفرع الطالب
                </label>

                <select
                    name="location_id"
                    class="form-select"
                >

                    <option value="">
                        كل الفروع
                    </option>

                    @foreach($locations as $location)

                        <option
                            value="{{ $location->id }}"
                            {{ (string) request('location_id') === (string) $location->id ? 'selected' : '' }}
                        >
                            {{ $location->name }}
                        </option>

                    @endforeach

                </select>

            </div>

        @endif


        <div class="sweets-filter-actions">

            <button
                type="submit"
                class="btn btn-outline"
            >
                تصفية
            </button>

            <a
                href="{{ route('showroom-sweets-requests.index') }}"
                class="btn btn-ghost"
            >
                مسح الفلاتر
            </a>

        </div>

    </form>

</div>


{{-- =========================================================
     الطلبات
========================================================= --}}

@if($requests->count())

    <div class="sweets-requests-list">

        @foreach($requests as $req)

            @php
                /*
                 * نستخدم القيمة الفعلية للحالة إذا كانت معروفة،
                 * ونرجع للعنوان العربي كخيار احتياطي.
                 */
                $statusValue = $req->status instanceof \BackedEnum
                    ? $req->status->value
                    : (string) $req->status;

                $statusLabel = $req->status instanceof \App\Enums\ShowroomSweetsRequestStatus
                    ? $req->status->label()
                    : ($req->status?->label() ?? (string) $req->status);

                $cardTone = match ($statusValue) {
                    'pending',
                    'submitted' => 'gold',

                    'in_progress' => 'blue',

                    'ready',
                    'ready_for_dispatch',
                    'out_for_delivery' => 'gold',

                    'completed',
                    'received_at_branch',
                    'fulfilled' => 'green',

                    'rejected' => 'red',

                    'cancelled',
                    'canceled' => 'gray',

                    default => 'gold',
                };
            @endphp


            <div class="sweets-request-card tone-{{ $cardTone }}">

                {{-- =====================================================
                     رأس الطلب
                ====================================================== --}}

                <div class="request-card-head">

                    <div class="request-number-wrap">

                        <div class="request-icon">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path d="M21 8a2 2 0 0 0-2-2h-3.5l-1-2h-5l-1 2H5a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2z"/>
                                <path d="M8 13h8"/>
                                <path d="M12 9v8"/>
                            </svg>

                        </div>


                        <div>

                            <div class="request-number">
                                {{ $req->request_number }}
                            </div>

                            <div class="request-created">

                                تم إنشاء الطلب

                                {{ $req->created_at->format('Y-m-d') }}

                                الساعة

                                {{ $req->created_at->format('H:i') }}

                            </div>

                        </div>

                    </div>


                    <div class="request-head-side">

                        <span class="request-status-badge">
                            {{ $statusLabel }}
                        </span>

                    </div>

                </div>


                {{-- =====================================================
                     جسم الطلب
                ====================================================== --}}

                <div class="request-card-body">

                    {{-- معلومات الطلب --}}
                    <div class="request-meta-grid">

                        {{-- الفرع --}}
                        <div class="request-meta">

                            <div class="request-meta-icon">

                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path d="M3 9l9-7 9 7"/>
                                    <path d="M5 10v10h14V10"/>
                                    <path d="M9 20v-6h6v6"/>
                                </svg>

                            </div>


                            <div>

                                <div class="request-meta-label">
                                    الفرع الطالب
                                </div>

                                <div class="request-meta-value">
                                    {{ $req->requestingLocation?->name ?? '—' }}
                                </div>

                            </div>

                        </div>


                        {{-- المصنع --}}
                        <div class="request-meta">

                            <div class="request-meta-icon">

                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path d="M3 21V9l6 3V9l6 3V4h6v17z"/>
                                    <path d="M7 17h2"/>
                                    <path d="M13 17h2"/>
                                    <path d="M18 17h2"/>
                                </svg>

                            </div>


                            <div>

                                <div class="request-meta-label">
                                    المصنع
                                </div>

                                <div class="request-meta-value">
                                    {{ $req->factoryLocation?->name ?? '—' }}
                                </div>

                            </div>

                        </div>


                        {{-- تاريخ الحاجة --}}
                        <div class="request-meta">

                            <div class="request-meta-icon">

                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <rect x="3" y="5" width="18" height="16" rx="2"/>
                                    <path d="M16 3v4"/>
                                    <path d="M8 3v4"/>
                                    <path d="M3 10h18"/>
                                </svg>

                            </div>


                            <div>

                                <div class="request-meta-label">
                                    تاريخ الحاجة
                                </div>

                                <div class="request-meta-value">
                                    {{ $req->needed_by?->format('Y-m-d') ?? 'غير محدد' }}
                                </div>

                            </div>

                        </div>


                        {{-- عدد الأصناف --}}
                        <div class="request-meta">

                            <div class="request-meta-icon">

                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                                    <rect x="14" y="14" width="7" height="7" rx="1"/>
                                </svg>

                            </div>


                            <div>

                                <div class="request-meta-label">
                                    عدد الأصناف
                                </div>

                                <div class="request-meta-value">
                                    {{ $req->items->count() }}
                                    صنف
                                </div>

                            </div>

                        </div>

                    </div>


                    {{-- =====================================================
                         الأصناف
                    ====================================================== --}}

                    @if($req->items->isNotEmpty())

                        <div class="request-items-title">

                            <strong>
                                الأصناف المطلوبة
                            </strong>

                            <span class="request-items-count">
                                {{ $req->items->count() }}
                                صنف في الطلب
                            </span>

                        </div>


                        <div class="request-items">

                            @foreach($req->items->take(4) as $item)

                                @php
                                    $productImage = $item->product?->image;

                                    $productImageUrl = null;

                                    if ($productImage) {
                                        if (\Illuminate\Support\Str::startsWith($productImage, ['http://', 'https://'])) {
                                            $productImageUrl = $productImage;
                                        } else {
                                            $productImageUrl = \Illuminate\Support\Facades\Storage::url($productImage);
                                        }
                                    }
                                @endphp


                                <div class="request-product">

                                    {{-- الصورة --}}
                                    <div class="request-product-image">

                                        @if($productImageUrl)

                                            <img
                                                src="{{ $productImageUrl }}"
                                                alt="{{ $item->displayProductName() }}"
                                                loading="lazy"
                                            >

                                        @else

                                            <div class="request-product-placeholder">

                                                <svg
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="1.5"
                                                >
                                                    <rect
                                                        x="3"
                                                        y="3"
                                                        width="18"
                                                        height="18"
                                                        rx="3"
                                                    />

                                                    <circle
                                                        cx="8.5"
                                                        cy="8.5"
                                                        r="1.5"
                                                    />

                                                    <path d="M21 15l-5-5L5 21"/>
                                                </svg>

                                            </div>

                                        @endif

                                    </div>


                                    {{-- بيانات الصنف --}}
                                    <div class="request-product-info">

                                        <div
                                            class="request-product-name"
                                            title="{{ $item->displayProductName() }}"
                                        >
                                            {{ $item->displayProductName() }}
                                        </div>


                                        <div class="request-product-qty">

                                            {{ number_format((float) $item->quantity, 3) + 0 }}

                                            <span>
                                                {{ $item->requested_unit }}
                                            </span>

                                        </div>


                                        @if($item->notes)

                                            <div
                                                class="request-product-note"
                                                title="{{ $item->notes }}"
                                            >
                                                {{ $item->notes }}
                                            </div>

                                        @endif

                                    </div>

                                </div>

                            @endforeach


                            @if($req->items->count() > 4)

                                <div class="more-products">

                                    <strong>
                                        +{{ $req->items->count() - 4 }}
                                    </strong>

                                    <span>
                                        أصناف إضافية
                                    </span>

                                </div>

                            @endif

                        </div>

                    @endif


                    {{-- =====================================================
                         الملاحظات
                    ====================================================== --}}

                    @if($req->notes)

                        <div class="request-notes">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/>
                            </svg>


                            <div>

                                <strong style="color:var(--text)">
                                    ملاحظات الطلب:
                                </strong>

                                {{ $req->notes }}

                            </div>

                        </div>

                    @endif

                </div>


                {{-- =====================================================
                     أسفل الطلب
                ====================================================== --}}

                <div class="request-card-footer">

                    <div class="request-footer-info">

                        من

                        <strong>
                            {{ $req->requestingLocation?->name ?? 'الفرع' }}
                        </strong>

                        إلى

                        <strong>
                            {{ $req->factoryLocation?->name ?? 'المصنع' }}
                        </strong>

                    </div>


                    <div class="request-actions">

                        <a
                            href="{{ route('showroom-sweets-requests.show', $req) }}"
                            class="btn btn-gold btn-sm request-view-btn"
                        >

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>

                            عرض تفاصيل الطلب

                        </a>


                        @can('showroom_sweets_requests.delete')

                            @if(
                                in_array(
                                    $statusValue,
                                    ['cancelled', 'canceled'],
                                    true
                                )
                            )

                                <form
                                    action="{{ route('showroom-sweets-requests.destroy', $req) }}"
                                    method="POST"
                                    onsubmit="return confirm('هل تريد حذف هذا الطلب الملغى؟')"
                                >

                                    @csrf
                                    @method('DELETE')


                                    <button
                                        type="submit"
                                        class="btn btn-danger btn-sm"
                                    >
                                        حذف
                                    </button>

                                </form>

                            @endif

                        @endcan

                    </div>

                </div>

            </div>

        @endforeach

    </div>


    {{-- =========================================================
         Pagination
    ========================================================== --}}

    <div>
        {{ $requests->withQueryString()->links() }}
    </div>


@else

    {{-- =========================================================
         لا توجد طلبات
    ========================================================== --}}

    <div class="sweets-empty">

        <div class="sweets-empty-icon">

            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.7"
            >
                <path d="M3 21V9l6 3V9l6 3V4h6v17z"/>
            </svg>

        </div>


        <div class="sweets-empty-title">
            لا توجد طلبات حلويات
        </div>


        <div class="sweets-empty-text">
            لم يتم إنشاء أي طلب حلويات للفروع حتى الآن.
        </div>


        @can('showroom_sweets_requests.create')

            <a
                href="{{ route('showroom-sweets-requests.create') }}"
                class="btn btn-gold"
                style="margin-top:1rem"
            >
                + إنشاء أول طلب
            </a>

        @endcan

    </div>

@endif

@endsection