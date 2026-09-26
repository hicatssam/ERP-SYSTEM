@extends('layouts.app')

@section('title', 'طلب حلويات: ' . $showroomSweetsRequest->request_number)

@push('styles')
<style>
    /* =========================================================
       متغيرات حالة الطلب
    ========================================================= */

    .sweets-request-page {
        --request-accent: #d6a900;
        --request-soft: rgba(214, 169, 0, .07);
        --request-border: rgba(214, 169, 0, .22);
    }

    .sweets-request-page.tone-gold {
        --request-accent: #d6a900;
        --request-soft: rgba(214, 169, 0, .07);
        --request-border: rgba(214, 169, 0, .22);
    }

    .sweets-request-page.tone-blue {
        --request-accent: #2563eb;
        --request-soft: rgba(37, 99, 235, .065);
        --request-border: rgba(37, 99, 235, .19);
    }

    .sweets-request-page.tone-green {
        --request-accent: #16a34a;
        --request-soft: rgba(22, 163, 74, .065);
        --request-border: rgba(22, 163, 74, .19);
    }

    .sweets-request-page.tone-red {
        --request-accent: #dc2626;
        --request-soft: rgba(220, 38, 38, .06);
        --request-border: rgba(220, 38, 38, .18);
    }

    .sweets-request-page.tone-gray {
        --request-accent: #64748b;
        --request-soft: rgba(100, 116, 139, .065);
        --request-border: rgba(100, 116, 139, .18);
    }


    /* =========================================================
       رأس الصفحة
    ========================================================= */

    .request-page-header {
        position: relative;

        display: flex;
        align-items: center;
        justify-content: space-between;

        gap: 1rem;

        padding: 1.1rem 1.2rem;

        margin-bottom: 1.2rem;

        background:
            linear-gradient(
                270deg,
                var(--request-soft),
                #fff 72%
            );

        border:
            1px solid
            var(--request-border);

        border-radius: 18px;

        overflow: hidden;
    }

    .request-page-header::before {
        content: '';

        position: absolute;

        top: 0;
        right: 0;

        width: 5px;
        height: 100%;

        background:
            var(--request-accent);
    }

    .request-page-title-wrap {
        display: flex;
        align-items: center;
        gap: .8rem;

        min-width: 0;
    }

    .request-page-icon {
        width: 48px;
        height: 48px;

        display: flex;
        align-items: center;
        justify-content: center;

        flex: 0 0 48px;

        border-radius: 13px;

        color:
            var(--request-accent);

        background:
            var(--request-soft);

        border:
            1px solid
            var(--request-border);
    }

    .request-page-icon svg {
        width: 23px;
        height: 23px;
    }

    .request-page-title {
        color: var(--text);

        font-size: 1.18rem;
        font-weight: 900;
    }

    .request-page-subtitle {
        margin-top: .2rem;

        color:
            var(--text-muted);

        font-size: .75rem;
    }

    .request-page-actions {
        display: flex;
        align-items: center;

        gap: .5rem;

        flex-wrap: wrap;
    }

    .request-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;

        min-height: 32px;

        padding: .4rem .85rem;

        border-radius: 999px;

        background:
            var(--request-soft);

        color:
            var(--request-accent);

        border:
            1px solid
            var(--request-border);

        font-size: .72rem;
        font-weight: 900;
    }


    /* =========================================================
       ملخص البيانات
    ========================================================= */

    .request-summary-grid {
        display: grid;

        grid-template-columns:
            repeat(3, minmax(0, 1fr));

        gap: .8rem;

        margin-bottom: 1rem;
    }

    .summary-box {
        display: flex;
        align-items: center;

        gap: .7rem;

        min-height: 82px;

        padding: .85rem;

        background: #fff;

        border:
            1px solid
            #e7e9ed;

        border-radius: 14px;

        transition:
            transform .18s ease,
            border-color .18s ease,
            background .18s ease;
    }

    .summary-box:hover {
        transform: translateY(-2px);

        background:
            var(--request-soft);

        border-color:
            var(--request-border);
    }

    .summary-icon {
        width: 40px;
        height: 40px;

        display: flex;
        align-items: center;
        justify-content: center;

        flex: 0 0 40px;

        border-radius: 11px;

        color:
            var(--request-accent);

        background:
            var(--request-soft);

        border:
            1px solid
            var(--request-border);
    }

    .summary-icon svg {
        width: 19px;
        height: 19px;
    }

    .summary-label {
        color:
            var(--text-muted);

        font-size: .66rem;

        margin-bottom: .18rem;
    }

    .summary-value {
        color: var(--text);

        font-size: .82rem;
        font-weight: 800;

        line-height: 1.55;
    }


    /* =========================================================
       تخطيط الصفحة
    ========================================================= */

    .request-main-grid {
        display: grid;

        grid-template-columns:
            minmax(0, 1.6fr)
            minmax(300px, .7fr);

        gap: 1rem;

        align-items: start;
    }

    .request-panel {
        background: #fff;

        border:
            1px solid
            #e5e7eb;

        border-radius: 16px;

        overflow: hidden;
    }

    .request-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;

        gap: .75rem;

        padding: .9rem 1rem;

        background:
            linear-gradient(
                270deg,
                var(--request-soft),
                #fff 80%
            );

        border-bottom:
            1px solid
            var(--request-border);
    }

    .request-panel-title {
        color: var(--text);

        font-size: .88rem;
        font-weight: 900;
    }

    .request-panel-count {
        color:
            var(--text-muted);

        font-size: .68rem;
    }

    .request-panel-body {
        padding: 1rem;
    }


    /* =========================================================
       الأصناف
    ========================================================= */

    .products-grid {
        display: grid;

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        gap: .8rem;
    }

    .product-card {
        position: relative;

        display: flex;
        align-items: center;

        gap: .8rem;

        padding: .75rem;

        background:
            #fff;

        border:
            1px solid
            #e7e9ed;

        border-radius: 14px;

        overflow: hidden;

        transition:
            transform .18s ease,
            border-color .18s ease,
            box-shadow .18s ease;
    }

    .product-card:hover {
        transform: translateY(-2px);

        border-color:
            var(--request-border);

        box-shadow:
            0 7px 18px
            rgba(15, 23, 42, .06);
    }

    .product-image {
        width: 92px;
        height: 92px;

        flex: 0 0 92px;

        overflow: hidden;

        border-radius: 12px;

        background:
            #f5f6f8;

        border:
            1px solid
            #e5e7eb;
    }

    .product-image img {
        display: block;

        width: 100%;
        height: 100%;

        object-fit: cover;

        transition:
            transform .25s ease;
    }

    .product-card:hover
    .product-image img {
        transform: scale(1.06);
    }

    .product-placeholder {
        width: 100%;
        height: 100%;

        display: flex;
        align-items: center;
        justify-content: center;

        color:
            #9ca3af;

        background:
            linear-gradient(
                135deg,
                #fafafa,
                #f0f2f4
            );
    }

    .product-placeholder svg {
        width: 30px;
        height: 30px;
    }

    .product-details {
        min-width: 0;
        flex: 1;
    }

    .product-name {
        color: var(--text);

        font-size: .88rem;
        font-weight: 900;

        line-height: 1.5;
    }

    .product-category {
        margin-top: .14rem;

        color:
            var(--text-muted);

        font-size: .65rem;
    }

    .product-quantity {
        display: inline-flex;
        align-items: center;

        gap: .3rem;

        margin-top: .5rem;

        padding: .28rem .55rem;

        border-radius: 8px;

        color:
            var(--request-accent);

        background:
            var(--request-soft);

        border:
            1px solid
            var(--request-border);

        font-size: .72rem;
        font-weight: 900;
    }

    .reservation-breakdown {
        display:flex;
        flex-wrap:wrap;
        gap:.35rem;
        margin-top:.45rem;
    }

    .reservation-breakdown span {
        padding:.24rem .45rem;
        border-radius:8px;
        font-size:.64rem;
        font-weight:700;
    }

    .reservation-breakdown .reserved {
        color:#c2410c;
        background:#fff7ed;
        border:1px solid #fed7aa;
    }

    .reservation-breakdown .available {
        color:#15803d;
        background:#f0fdf4;
        border:1px solid #bbf7d0;
    }

    .reservation-note {
        margin-top:.35rem;
        color:#9a3412;
        font-size:.63rem;
        line-height:1.55;
    }

    .product-sku {
        margin-top: .25rem;

        color:
            var(--text-muted);

        font-size: .62rem;
    }

    .product-note {
        margin-top: .4rem;

        color:
            var(--text-muted);

        font-size: .65rem;

        line-height: 1.6;
    }


    /* =========================================================
       بيانات إضافية
    ========================================================= */

    .details-list {
        display: flex;
        flex-direction: column;

        gap: .15rem;
    }

    .detail-row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;

        gap: 1rem;

        padding: .72rem .15rem;

        border-bottom:
            1px solid
            #eef0f2;
    }

    .detail-row:last-child {
        border-bottom: 0;
    }

    .detail-label {
        color:
            var(--text-muted);

        font-size: .7rem;
    }

    .detail-value {
        color: var(--text);

        font-size: .72rem;
        font-weight: 700;

        text-align: left;

        max-width: 65%;
    }


    /* =========================================================
       الملاحظات
    ========================================================= */

    .note-box {
        display: flex;
        align-items: flex-start;

        gap: .55rem;

        padding: .8rem;

        margin-top: .8rem;

        border-radius: 11px;

        background:
            var(--request-soft);

        border:
            1px solid
            var(--request-border);

        color:
            var(--text-muted);

        font-size: .7rem;

        line-height: 1.7;
    }

    .note-box svg {
        width: 17px;
        height: 17px;

        flex: 0 0 17px;

        margin-top: 2px;

        color:
            var(--request-accent);
    }

    .note-box strong {
        color: var(--text);
    }


    /* =========================================================
       تحديث الحالة
    ========================================================= */

    .status-update-panel {
        margin-top: 1rem;

        background:
            linear-gradient(
                180deg,
                var(--request-soft),
                #fff 35%
            );

        border-color:
            var(--request-border);
    }

    .status-update-form {
        display: grid;

        gap: .9rem;
    }

    .status-update-button {
        width: 100%;

        justify-content: center;
    }


    /* =========================================================
       التاريخ / المسار
    ========================================================= */

    .request-route {
        display: flex;
        align-items: center;
        justify-content: center;

        gap: .5rem;

        margin-bottom: 1rem;

        padding: .7rem 1rem;

        background:
            var(--request-soft);

        border:
            1px solid
            var(--request-border);

        border-radius: 12px;

        color: var(--text);

        font-size: .72rem;
        font-weight: 700;
    }

    .request-route-arrow {
        color:
            var(--request-accent);

        font-size: 1rem;
        font-weight: 900;
    }


    /* =========================================================
       فارغ
    ========================================================= */

    .products-empty {
        min-height: 180px;

        display: flex;
        align-items: center;
        justify-content: center;

        color:
            var(--text-muted);

        font-size: .75rem;
    }


    /* =========================================================
       موعد الطلب
    ========================================================= */

    .request-due-alert {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:1rem;
        margin:-.2rem 0 1rem;
        padding:.8rem 1rem;
        border:1px solid var(--border);
        border-radius:13px;
        background:#fff;
    }

    .request-due-alert > div:first-child {
        display:grid;
        gap:.12rem;
    }

    .request-due-kicker {
        color:var(--text-muted);
        font-size:.62rem;
        font-weight:700;
    }

    .request-due-alert strong {
        font-size:.8rem;
    }

    .request-due-date {
        font-size:.74rem;
        font-weight:900;
        white-space:nowrap;
    }

    .request-due-alert.late {
        color:#b91c1c;
        background:#fef2f2;
        border-color:#fecaca;
    }

    .request-due-alert.today {
        color:#9a6700;
        background:#fffbeb;
        border-color:#fde68a;
    }

    .request-due-alert.soon {
        color:#0369a1;
        background:#f0f9ff;
        border-color:#bae6fd;
    }

    .request-due-alert.done {
        color:#15803d;
        background:#f0fdf4;
        border-color:#bbf7d0;
    }

    .request-due-alert.neutral {
        color:#475569;
        background:#f8fafc;
        border-color:#e2e8f0;
    }

    .workflow-section {
        margin-bottom:1rem;
    }


    /* =========================================================
       Popup تحديث الحالة
    ========================================================= */

    .status-modal {
        position:fixed;
        inset:0;
        z-index:99999;
        display:flex;
        align-items:center;
        justify-content:center;
        padding:1.25rem;
        background:rgba(15,23,42,.56);
        backdrop-filter:blur(4px);
        opacity:0;
        visibility:hidden;
        pointer-events:none;
        transition:opacity .18s ease,visibility .18s ease;
    }

    .status-modal.is-open {
        opacity:1;
        visibility:visible;
        pointer-events:auto;
    }

    .status-modal-dialog {
        width:100%;
        max-width:560px;
        max-height:calc(100vh - 2rem);
        overflow:auto;
        background:#fff;
        border:1px solid var(--border);
        border-radius:18px;
        box-shadow:0 24px 70px rgba(0,0,0,.24);
        transform:translateY(16px) scale(.985);
        transition:transform .18s ease;
    }

    .status-modal.is-open .status-modal-dialog {
        transform:translateY(0) scale(1);
    }

    .status-modal-header {
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:1rem;
        padding:1.15rem 1.25rem;
        border-bottom:1px solid var(--border);
    }

    .status-modal-eyebrow {
        display:block;
        margin-bottom:.2rem;
        color:var(--request-accent);
        font-size:.66rem;
        font-weight:900;
    }

    .status-modal-header h3 {
        margin:0;
        font-size:1.02rem;
        font-weight:900;
    }

    .status-modal-header p {
        margin:.28rem 0 0;
        color:var(--text-muted);
        font-size:.7rem;
    }

    .status-modal-header p strong {
        color:var(--request-accent);
    }

    .status-modal-close {
        width:34px;
        height:34px;
        flex:0 0 34px;
        display:grid;
        place-items:center;
        border:1px solid var(--border);
        border-radius:50%;
        background:#fff;
        color:var(--text-muted);
        font-size:1.35rem;
        cursor:pointer;
    }

    .status-current-box {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:1rem;
        margin:1rem 1.25rem 0;
        padding:.75rem .9rem;
        border:1px solid var(--request-border);
        border-radius:11px;
        background:var(--request-soft);
    }

    .status-current-box span {
        color:var(--text-muted);
        font-size:.7rem;
    }

    .status-current-box strong {
        color:var(--request-accent);
        font-size:.78rem;
    }

    .status-modal-body {
        display:grid;
        gap:1rem;
        padding:1.15rem 1.25rem;
    }

    .status-choice-grid {
        display:grid;
        gap:.55rem;
    }

    .status-choice {
        display:flex;
        align-items:center;
        gap:.7rem;
        padding:.72rem .8rem;
        border:1px solid var(--border);
        border-radius:11px;
        cursor:pointer;
        transition:.15s ease;
    }

    .status-choice:has(input:checked) {
        border-color:var(--request-border);
        background:var(--request-soft);
    }

    .status-choice input {
        width:17px;
        height:17px;
        accent-color:var(--request-accent);
    }

    .status-choice span {
        display:grid;
        gap:.08rem;
    }

    .status-choice strong {
        font-size:.76rem;
    }

    .status-choice small {
        color:var(--text-muted);
        font-size:.62rem;
    }

    .status-modal-footer {
        display:flex;
        justify-content:flex-end;
        gap:.6rem;
        padding:.95rem 1.25rem;
        border-top:1px solid var(--border);
        background:var(--off-white);
    }


    /* =========================================================
       Responsive
    ========================================================= */

    @media (max-width: 1200px) {

        .request-summary-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .request-main-grid {
            grid-template-columns: 1fr;
        }

    }

    @media (max-width: 800px) {

        .request-page-header {
            align-items: flex-start;
            flex-direction: column;
        }

        .request-page-actions {
            width: 100%;
        }

        .request-summary-grid {
            grid-template-columns: 1fr;
        }

        .products-grid {
            grid-template-columns: 1fr;
        }

        .product-image {
            width: 78px;
            height: 78px;

            flex-basis: 78px;
        }

        .status-modal {
            padding:.75rem;
        }

        .status-modal-footer {
            flex-direction:column-reverse;
        }

        .status-modal-footer .btn {
            width:100%;
        }

        .request-due-alert {
            align-items:flex-start;
        }

    }
</style>
@endpush


@section('content')

@php
    $statusValue = $showroomSweetsRequest->status instanceof \BackedEnum
        ? $showroomSweetsRequest->status->value
        : (string) $showroomSweetsRequest->status;

    $statusLabel = $showroomSweetsRequest->status->label();

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

    $terminalStatuses = [
        'completed',
        'fulfilled',
        'cancelled',
        'canceled',
        'rejected',
    ];

    $isTerminal = in_array(
        $statusValue,
        $terminalStatuses,
        true
    );

    $neededBy = $showroomSweetsRequest->needed_by
        ? $showroomSweetsRequest->needed_by->copy()->startOfDay()
        : null;

    $daysUntilNeeded = $neededBy
        ? (int) today()->diffInDays(
            $neededBy,
            false
        )
        : null;

    [$dueTone, $dueLabel] = match (true) {
        $isTerminal => ['done', 'الطلب منتهٍ'],
        $daysUntilNeeded === null => ['neutral', 'موعد الحاجة غير محدد'],
        $daysUntilNeeded < 0 => [
            'late',
            'متأخر ' . abs($daysUntilNeeded) . ' يوم',
        ],
        $daysUntilNeeded === 0 => ['today', 'مطلوب اليوم'],
        $daysUntilNeeded === 1 => ['soon', 'مطلوب غدًا'],
        default => [
            'neutral',
            'متبقي ' . $daysUntilNeeded . ' أيام',
        ],
    };

    $totalQuantity = $showroomSweetsRequest
        ->items
        ->sum('quantity');
@endphp


<div class="sweets-request-page tone-{{ $cardTone }}">

    {{-- =========================================================
         رأس الطلب
    ========================================================== --}}

    <div class="request-page-header">

        <div class="request-page-title-wrap">

            <div class="request-page-icon">

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

                <div class="request-page-title">
                    {{ $showroomSweetsRequest->request_number }}
                </div>

                <div class="request-page-subtitle">

                    طلب حلويات من

                    <strong>
                        {{ $showroomSweetsRequest->requestingLocation?->name ?? 'الفرع' }}
                    </strong>

                    إلى

                    <strong>
                        {{ $showroomSweetsRequest->factoryLocation?->name ?? 'المصنع' }}
                    </strong>

                </div>

            </div>

        </div>


        <div class="request-page-actions">

            <span class="request-status">
                {{ $statusLabel }}
            </span>

            @if(count($allowedTransitions) > 0)
                <button
                    type="button"
                    class="btn btn-gold btn-sm"
                    onclick="openShowroomSweetsStatusModal()"
                >
                    تحديث الحالة
                </button>
            @endif


            @if($canCancel)

                <form
                    action="{{ route('showroom-sweets-requests.cancel', $showroomSweetsRequest) }}"
                    method="POST"
                    onsubmit="return confirm('هل تريد إلغاء هذا الطلب؟')"
                >

                    @csrf
                    @method('PATCH')

                    <button
                        class="btn btn-danger btn-sm"
                        type="submit"
                    >
                        إلغاء الطلب
                    </button>

                </form>

            @endif


            <a
                href="{{ route('showroom-sweets-requests.index') }}"
                class="btn btn-ghost btn-sm"
            >
                رجوع
            </a>

        </div>

    </div>

    <div class="request-due-alert {{ $dueTone }}">
        <div>
            <span class="request-due-kicker">موعد الطلب</span>
            <strong>{{ $dueLabel }}</strong>
        </div>

        <div class="request-due-date">
            {{ \App\Support\ArabicDate::date($showroomSweetsRequest->needed_by) }}
        </div>
    </div>


    {{-- =========================================================
         المسار
    ========================================================== --}}

    <div class="request-route">

        <span>
            {{ $showroomSweetsRequest->requestingLocation?->name ?? 'الفرع' }}
        </span>

        <span class="request-route-arrow">
            ←
        </span>

        <span>
            {{ $showroomSweetsRequest->factoryLocation?->name ?? 'المصنع' }}
        </span>

    </div>


    {{-- =========================================================
         ملخص
    ========================================================== --}}

    <div class="request-summary-grid compact">

        <div class="summary-box">

            <div class="summary-icon">
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <path d="M4 7h16"/>
                    <path d="M4 12h16"/>
                    <path d="M4 17h10"/>
                </svg>
            </div>

            <div>
                <div class="summary-label">
                    إجمالي الكمية
                </div>

                <div class="summary-value">
                    {{
                        rtrim(
                            rtrim(
                                number_format(
                                    (float) $totalQuantity,
                                    3,
                                    '.',
                                    ''
                                ),
                                '0'
                            ),
                            '.'
                        )
                    }}
                </div>
            </div>

        </div>


        <div class="summary-box">

            <div class="summary-icon">
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
                <div class="summary-label">
                    عدد الأصناف
                </div>

                <div class="summary-value">
                    {{ $showroomSweetsRequest->items->count() }}
                    صنف
                </div>
            </div>

        </div>


        <div class="summary-box">

            <div class="summary-icon">
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M12 7v5l3 2"/>
                </svg>
            </div>

            <div>
                <div class="summary-label">
                    وقت الإرسال
                </div>

                <div class="summary-value">
                    {{ \App\Support\ArabicDate::compactDateTime($showroomSweetsRequest->submitted_at) }}
                </div>
            </div>

        </div>

    </div>


    {{-- =========================================================
         مسار الطلب
    ========================================================== --}}

    <div class="workflow-section">
        <x-workflow-toolbar
            type="showroom_sweets"
            :record="$showroomSweetsRequest"
        />
    </div>


    {{-- =========================================================
         المحتوى
    ========================================================== --}}

    <div class="request-main-grid">

        {{-- =====================================================
             الأصناف
        ====================================================== --}}

        <div class="request-panel">

            <div class="request-panel-header">

                <div class="request-panel-title">
                    أصناف الحلويات المطلوبة
                </div>

                <div class="request-panel-count">
                    {{ $showroomSweetsRequest->items->count() }}
                    صنف
                </div>

            </div>


            <div class="request-panel-body">

                @if($showroomSweetsRequest->items->isNotEmpty())

                    <div class="products-grid">

                        @foreach($showroomSweetsRequest->items as $index => $item)

                            @php
                                $formatQty = static function ($value): string {
                                    return rtrim(
                                        rtrim(
                                            number_format(
                                                (float) $value,
                                                3,
                                                '.',
                                                ''
                                            ),
                                            '0'
                                        ),
                                        '.'
                                    );
                                };

                                $quantity =
                                    $formatQty($item->quantity);

                                $reservedQuantity =
                                    $formatQty(
                                        $item->reserved_quantity
                                        ?? 0
                                    );

                                $availableQuantity =
                                    $formatQty(
                                        $item->availableQuantity()
                                    );

                                $productImage = $item->product?->image;

                                $productImageUrl = null;

                                if ($productImage) {

                                    if (
                                        \Illuminate\Support\Str::startsWith(
                                            $productImage,
                                            ['http://', 'https://']
                                        )
                                    ) {
                                        $productImageUrl = $productImage;
                                    } else {
                                        $productImageUrl =
                                            \Illuminate\Support\Facades\Storage::url(
                                                $productImage
                                            );
                                    }
                                }
                            @endphp


                            <div class="product-card">

                                {{-- الصورة --}}
                                <div class="product-image">

                                    @if($productImageUrl)

                                        <img
                                            src="{{ $productImageUrl }}"
                                            alt="{{ $item->displayProductName() }}"
                                            loading="lazy"
                                        >

                                    @else

                                        <div class="product-placeholder">

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
                                


                                {{-- التفاصيل --}}
                                <div class="product-details">

                                                
  

                                       


                                    {{-- طلب الكيك الخاص --}}

                                    


                                    <div class="product-category">
                                        {{ $item->product?->category?->name_ar
                                            ?? $item->product?->category?->name
                                            ?? 'بدون فئة' }}
                                    </div>


                                    <div class="product-quantity">

                                        {{ $quantity }}

                                        <span>
                                            {{ $item->requested_unit }}
                                        </span>

                                    </div>

                                    @if((float) ($item->reserved_quantity ?? 0) > 0)
                                        <div class="reservation-breakdown">
                                            <span class="reserved">
                                                محجوز للعملاء:
                                                <strong>
                                                    {{ $reservedQuantity }}
                                                    {{ $item->requested_unit }}
                                                </strong>
                                            </span>

                                            <span class="available">
                                                متاح للعرض:
                                                <strong>
                                                    {{ $availableQuantity }}
                                                    {{ $item->requested_unit }}
                                                </strong>
                                            </span>
                                        </div>

                                        @if($item->reservation_notes)
                                            <div class="reservation-note">
                                                {{ $item->reservation_notes }}
                                            </div>
                                        @endif
                                    @endif


                                    @if($item->product?->sku)

                                        <div class="product-sku">
                                            الرمز:
                                            {{ $item->product->sku }}
                                        </div>

                                    @endif


                                    @if($item->notes)

                                        <div class="product-note">

                                            <strong>
                                                ملاحظة:
                                            </strong>

                                            {{ $item->notes }}

                                        </div>

                                    @endif

                                </div>

                            </div>

                        @endforeach

                    </div>

                @else

                    <div class="products-empty">
                        لا توجد أصناف في هذا الطلب.
                    </div>

                @endif

            </div>

        </div>


        {{-- =====================================================
             العمود الجانبي
        ====================================================== --}}

        <div>

            {{-- بيانات الطلب --}}
            <div class="request-panel">

                <div class="request-panel-header">

                    <div class="request-panel-title">
                        بيانات الطلب
                    </div>

                </div>


                <div class="request-panel-body">

                    <div class="details-list">

                        <div class="detail-row">

                            <span class="detail-label">
                                رقم الطلب
                            </span>

                            <span class="detail-value">
                                {{ $showroomSweetsRequest->request_number }}
                            </span>

                        </div>


                        <div class="detail-row">

                            <span class="detail-label">
                                الفرع الطالب
                            </span>

                            <span class="detail-value">
                                {{ $showroomSweetsRequest->requestingLocation?->name ?? '—' }}
                            </span>

                        </div>


                        <div class="detail-row">

                            <span class="detail-label">
                                المصنع
                            </span>

                            <span class="detail-value">
                                {{ $showroomSweetsRequest->factoryLocation?->name ?? '—' }}
                            </span>

                        </div>


                        <div class="detail-row">

                            <span class="detail-label">
                                أُنشئ بواسطة
                            </span>

                            <span class="detail-value">

                                {{ $showroomSweetsRequest->creator?->employee?->full_name
                                    ?? $showroomSweetsRequest->creator?->display_name
                                    ?? '—' }}

                            </span>

                        </div>


                        <div class="detail-row">

                            <span class="detail-label">
                                وقت الإنشاء
                            </span>

                            <span class="detail-value">
                                {{ \App\Support\ArabicDate::compactDateTime($showroomSweetsRequest->created_at) }}
                            </span>

                        </div>


                        @if($showroomSweetsRequest->fulfilled_at)

                            <div class="detail-row">

                                <span class="detail-label">
                                    وقت التنفيذ
                                </span>

                                <span
                                    class="detail-value"
                                    style="color:#16a34a"
                                >
                                    {{ \App\Support\ArabicDate::compactDateTime($showroomSweetsRequest->fulfilled_at) }}
                                </span>

                            </div>

                        @endif

                    </div>


                    {{-- ملاحظات الفرع --}}
                    @if($showroomSweetsRequest->notes)

                        <div class="note-box">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/>
                            </svg>


                            <div>

                                <strong>
                                    ملاحظات الفرع
                                </strong>

                                <div style="margin-top:.2rem">
                                    {{ $showroomSweetsRequest->notes }}
                                </div>

                            </div>

                        </div>

                    @endif


                    {{-- ملاحظات المصنع --}}
                    @if($showroomSweetsRequest->factory_notes)

                        <div class="note-box">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path d="M3 21V9l6 3V9l6 3V4h6v17z"/>
                            </svg>


                            <div>

                                <strong>
                                    ملاحظات المصنع
                                </strong>

                                <div style="margin-top:.2rem">
                                    {{ $showroomSweetsRequest->factory_notes }}
                                </div>

                            </div>

                        </div>

                    @endif

                </div>

            </div>


        </div>

    </div>

</div>

{{-- =========================================================
     Popup تحديث حالة الطلب
========================================================= --}}

@if(count($allowedTransitions) > 0)
        <div
            id="showroomSweetsStatusModal"
            class="status-modal"
            aria-hidden="true"
            onclick="closeShowroomSweetsStatusModalFromBackdrop(event)"
        >
            <div
                class="status-modal-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="showroomSweetsStatusModalTitle"
            >
                <div class="status-modal-header">
                    <div>
                        <span class="status-modal-eyebrow">
                            تحديث العملية
                        </span>

                        <h3 id="showroomSweetsStatusModalTitle">
                            تحديث حالة طلب الحلويات
                        </h3>

                        <p>
                            طلب رقم
                            <strong>
                                {{ $showroomSweetsRequest->request_number }}
                            </strong>
                        </p>
                    </div>

                    <button
                        type="button"
                        class="status-modal-close"
                        onclick="closeShowroomSweetsStatusModal()"
                        aria-label="إغلاق"
                    >
                        ×
                    </button>
                </div>

                <div class="status-current-box">
                    <span>الحالة الحالية</span>
                    <strong>{{ $statusLabel }}</strong>
                </div>

                <form
                    action="{{ route('showroom-sweets-requests.status', $showroomSweetsRequest) }}"
                    method="POST"
                >
                    @csrf
                    @method('PATCH')

                    <div class="status-modal-body">
                        <div class="form-group">
                            <label class="form-label">
                                الانتقال إلى
                            </label>

                            <div class="status-choice-grid">
                                @foreach($allowedTransitions as $nextStatus)
                                    <label class="status-choice">
                                        <input
                                            type="radio"
                                            name="status"
                                            value="{{ $nextStatus->value }}"
                                            required
                                            @checked($loop->first)
                                        >

                                        <span>
                                            <strong>
                                                {{ $nextStatus->label() }}
                                            </strong>

                                            <small>
                                                اختر هذه المرحلة كحالة جديدة للطلب
                                            </small>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                ملاحظة
                                <span style="font-weight:500;color:var(--text-muted)">
                                    (اختياري)
                                </span>
                            </label>

                            <textarea
                                name="factory_notes"
                                class="form-textarea"
                                rows="3"
                                placeholder="مثال: تم تجهيز الطلب وسيكون جاهزًا للاستلام..."
                            >{{ old('factory_notes', $showroomSweetsRequest->factory_notes) }}</textarea>
                        </div>
                    </div>

                    <div class="status-modal-footer">
                        <button
                            type="button"
                            class="btn btn-ghost"
                            onclick="closeShowroomSweetsStatusModal()"
                        >
                            إلغاء
                        </button>

                        <button
                            class="btn btn-gold"
                            type="submit"
                        >
                            تأكيد تحديث الحالة
                        </button>
                    </div>
                </form>
            </div>
        </div>
@endif


@if(count($allowedTransitions) > 0)
        <script>
            function openShowroomSweetsStatusModal() {
                const modal = document.getElementById(
                    'showroomSweetsStatusModal'
                );

                if (!modal) return;

                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function closeShowroomSweetsStatusModal() {
                const modal = document.getElementById(
                    'showroomSweetsStatusModal'
                );

                if (!modal) return;

                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }

            function closeShowroomSweetsStatusModalFromBackdrop(event) {
                if (event.target.id === 'showroomSweetsStatusModal') {
                    closeShowroomSweetsStatusModal();
                }
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeShowroomSweetsStatusModal();
                }
            });
        </script>
@endif
@endsection