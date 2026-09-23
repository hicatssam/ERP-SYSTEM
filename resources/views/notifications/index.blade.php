@extends('layouts.app')

@section('title', 'الإشعارات')
@section('page-title', 'الإشعارات')

@push('styles')
<style>
    .notifications-page {
        display: grid;
        gap: 1.25rem;
    }

    .notifications-hero {
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        min-height: 145px;
        padding: 1.75rem;
        border: 1px solid rgba(188, 145, 48, .18);
        border-radius: 22px;
        background:
            radial-gradient(circle at 10% 20%, rgba(198, 155, 55, .14), transparent 35%),
            linear-gradient(135deg, var(--card, #fff), rgba(198, 155, 55, .06));
        box-shadow: 0 15px 45px rgba(15, 23, 42, .06);
    }

    .notifications-hero::after {
        content: "";
        position: absolute;
        left: -45px;
        bottom: -75px;
        width: 190px;
        height: 190px;
        border: 35px solid rgba(198, 155, 55, .06);
        border-radius: 50%;
        pointer-events: none;
    }

    .notifications-hero-content {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .notifications-hero-icon {
        width: 58px;
        height: 58px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        border-radius: 18px;
        color: #fff;
        background: linear-gradient(135deg, #d2aa51, #a97b1e);
        box-shadow: 0 12px 28px rgba(169, 123, 30, .27);
    }

    .notifications-hero-icon svg {
        width: 27px;
        height: 27px;
    }

    .notifications-hero h2 {
        margin: 0 0 .3rem;
        color: var(--text, #172033);
        font-size: 1.3rem;
        font-weight: 900;
    }

    .notifications-hero p {
        margin: 0;
        color: var(--text-muted, #687386);
        font-size: .88rem;
        line-height: 1.7;
    }

    .notifications-stats {
        position: relative;
        z-index: 1;
        display: flex;
        gap: .75rem;
    }

    .notification-stat {
        min-width: 105px;
        padding: .85rem 1rem;
        text-align: center;
        border: 1px solid var(--border, #e8ebf0);
        border-radius: 15px;
        background: rgba(255, 255, 255, .72);
        backdrop-filter: blur(10px);
    }

    .notification-stat strong {
        display: block;
        color: var(--text, #172033);
        font-size: 1.25rem;
        font-weight: 900;
    }

    .notification-stat span {
        color: var(--text-muted, #687386);
        font-size: .72rem;
    }

    .notification-tools {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem;
        border: 1px solid var(--border, #e8ebf0);
        border-radius: 18px;
        background: var(--card, #fff);
        box-shadow: 0 8px 30px rgba(15, 23, 42, .04);
    }

    .notification-search {
        position: relative;
        width: min(100%, 360px);
    }

    .notification-search svg {
        position: absolute;
        top: 50%;
        right: 14px;
        width: 18px;
        height: 18px;
        color: var(--text-muted, #7b8494);
        transform: translateY(-50%);
        pointer-events: none;
    }

    .notification-search input {
        width: 100%;
        height: 43px;
        padding: 0 43px 0 14px;
        color: var(--text, #172033);
        border: 1px solid var(--border, #e1e5ea);
        border-radius: 13px;
        outline: none;
        background: var(--body-bg, #f8f9fb);
        transition: .2s ease;
    }

    .notification-search input:focus {
        border-color: #c59a3e;
        background: var(--card, #fff);
        box-shadow: 0 0 0 4px rgba(197, 154, 62, .11);
    }

    .notification-filters {
        display: flex;
        align-items: center;
        gap: .45rem;
        flex-wrap: wrap;
    }

    .notification-filter {
        padding: .58rem .9rem;
        border: 1px solid var(--border, #e1e5ea);
        border-radius: 11px;
        color: var(--text-muted, #687386);
        background: transparent;
        font-family: inherit;
        font-size: .78rem;
        font-weight: 700;
        cursor: pointer;
        transition: .2s ease;
    }

    .notification-filter:hover {
        color: #a77b22;
        border-color: rgba(188, 145, 48, .45);
    }

    .notification-filter.active {
        color: #fff;
        border-color: #b88a2b;
        background: linear-gradient(135deg, #cda74f, #a8781b);
        box-shadow: 0 7px 18px rgba(168, 120, 27, .18);
    }

    .notifications-card {
        overflow: hidden;
        border: 1px solid var(--border, #e8ebf0);
        border-radius: 20px;
        background: var(--card, #fff);
        box-shadow: 0 12px 35px rgba(15, 23, 42, .05);
    }

    .notification-item {
        position: relative;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        gap: .8rem;
        padding: 1rem 1.15rem;
        border-bottom: 1px solid var(--border, #edf0f3);
        transition: background .2s ease, transform .2s ease;
    }

    .notification-item:last-child {
        border-bottom: 0;
    }

    .notification-item:hover {
        background: rgba(188, 145, 48, .035);
    }

    .notification-item.notification-unread {
        background: linear-gradient(
            90deg,
            rgba(198, 155, 55, .09),
            rgba(198, 155, 55, .025)
        );
    }

    .notification-item.notification-unread::before {
        content: "";
        position: absolute;
        top: 14px;
        right: 0;
        bottom: 14px;
        width: 3px;
        border-radius: 5px 0 0 5px;
        background: linear-gradient(#d7af50, #a97a1c);
    }

    .notification-open {
        min-width: 0;
        display: flex;
        align-items: center;
        gap: .9rem;
        padding: 0;
        text-align: right;
        border: 0;
        color: inherit;
        background: transparent;
        font-family: inherit;
        cursor: pointer;
    }

    .notification-icon {
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        border-radius: 14px;
    }

    .notification-icon svg {
        width: 20px;
        height: 20px;
    }

    .notification-icon.type-order {
        color: #9b7019;
        background: rgba(203, 161, 64, .15);
    }

    .notification-icon.type-inventory {
        color: #2563eb;
        background: rgba(37, 99, 235, .11);
    }

    .notification-icon.type-payment {
        color: #059669;
        background: rgba(5, 150, 105, .11);
    }

    .notification-icon.type-warning {
        color: #d97706;
        background: rgba(217, 119, 6, .12);
    }

    .notification-icon.type-info {
        color: #64748b;
        background: rgba(100, 116, 139, .12);
    }

    .notification-content {
        min-width: 0;
        flex: 1;
    }

    .notification-heading {
        display: flex;
        align-items: center;
        gap: .45rem;
        margin-bottom: .25rem;
    }

    .notification-title {
        overflow: hidden;
        color: var(--text, #172033);
        font-size: .89rem;
        font-weight: 700;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .notification-unread .notification-title {
        font-weight: 900;
    }

    .notification-dot {
        width: 7px;
        height: 7px;
        flex-shrink: 0;
        border-radius: 50%;
        background: #c89d3f;
        box-shadow: 0 0 0 4px rgba(200, 157, 63, .12);
    }

    .notification-message {
        display: -webkit-box;
        overflow: hidden;
        margin: 0;
        color: var(--text-muted, #687386);
        font-size: .8rem;
        line-height: 1.65;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 1;
    }

    .notification-meta {
        display: flex;
        align-items: center;
        gap: .65rem;
        margin-top: .35rem;
        color: var(--text-subtle, #929baa);
        font-size: .7rem;
    }

    .notification-type-label {
        padding: .15rem .45rem;
        border-radius: 6px;
        color: #916719;
        background: rgba(198, 155, 55, .1);
        font-weight: 700;
    }

    .notification-actions {
        display: flex;
        align-items: center;
        gap: .35rem;
    }

    .notification-action-button {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        border: 1px solid var(--border, #e2e6eb);
        border-radius: 11px;
        color: var(--text-muted, #687386);
        background: var(--card, #fff);
        cursor: pointer;
        transition: .2s ease;
    }

    .notification-action-button:hover {
        color: #a7781c;
        border-color: rgba(185, 139, 44, .45);
        background: rgba(198, 155, 55, .08);
        transform: translateY(-2px);
    }

    .notification-action-button svg {
        width: 17px;
        height: 17px;
    }

    .notification-filter-empty {
        padding: 3.5rem 1rem;
        text-align: center;
        color: var(--text-muted, #687386);
    }

    .notification-filter-empty svg {
        width: 45px;
        height: 45px;
        margin-bottom: .75rem;
        opacity: .45;
    }

    .notifications-empty {
        padding: 4rem 1.5rem;
        text-align: center;
    }

    .notifications-empty-icon {
        width: 76px;
        height: 76px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        border-radius: 24px;
        color: #b18730;
        background: rgba(198, 155, 55, .1);
    }

    .notifications-empty-icon svg {
        width: 34px;
        height: 34px;
    }

    .notifications-empty h3 {
        margin: 0 0 .4rem;
        color: var(--text, #172033);
        font-size: 1rem;
        font-weight: 900;
    }

    .notifications-empty p {
        margin: 0;
        color: var(--text-muted, #687386);
        font-size: .82rem;
    }

    .notification-modal {
        position: fixed;
        inset: 0;
        z-index: 3000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .notification-modal[hidden] {
        display: none !important;
    }

    .notification-modal-overlay {
        position: absolute;
        inset: 0;
        border: 0;
        background: rgba(15, 23, 42, .6);
        backdrop-filter: blur(7px);
        cursor: default;
    }

    .notification-modal-panel {
        position: relative;
        z-index: 1;
        width: min(100%, 610px);
        max-height: calc(100vh - 2rem);
        overflow: auto;
        border: 1px solid rgba(255, 255, 255, .15);
        border-radius: 24px;
        background: var(--card, #fff);
        box-shadow: 0 28px 80px rgba(15, 23, 42, .3);
        animation: notificationModalIn .22s ease;
    }

    @keyframes notificationModalIn {
        from {
            opacity: 0;
            transform: translateY(16px) scale(.97);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .notification-modal-header {
        display: flex;
        align-items: flex-start;
        gap: .9rem;
        padding: 1.35rem;
        border-bottom: 1px solid var(--border, #e8ebf0);
    }

    .notification-modal-icon {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        border-radius: 15px;
        color: #986c17;
        background: rgba(198, 155, 55, .13);
    }

    .notification-modal-icon svg {
        width: 22px;
        height: 22px;
    }

    .notification-modal-heading {
        min-width: 0;
        flex: 1;
    }

    .notification-modal-heading h3 {
        margin: 0 0 .35rem;
        color: var(--text, #172033);
        font-size: 1.05rem;
        font-weight: 900;
        line-height: 1.5;
    }

    .notification-modal-heading div {
        display: flex;
        align-items: center;
        gap: .6rem;
        color: var(--text-muted, #687386);
        font-size: .73rem;
    }

    .notification-modal-close {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        padding: 0;
        border: 1px solid var(--border, #e2e6eb);
        border-radius: 11px;
        color: var(--text-muted, #687386);
        background: transparent;
        cursor: pointer;
    }

    .notification-modal-close:hover {
        color: #dc2626;
        border-color: rgba(220, 38, 38, .25);
        background: rgba(220, 38, 38, .06);
    }

    .notification-modal-close svg {
        width: 18px;
        height: 18px;
    }

    .notification-modal-body {
        padding: 1.35rem;
    }

    .notification-modal-message {
        margin: 0;
        padding: 1rem;
        color: var(--text, #263244);
        border-right: 3px solid #c89d3f;
        border-radius: 12px;
        background: rgba(198, 155, 55, .07);
        font-size: .87rem;
        line-height: 1.9;
        white-space: pre-line;
    }

    .notification-details-title {
        margin: 1.35rem 0 .75rem;
        color: var(--text, #172033);
        font-size: .8rem;
        font-weight: 900;
    }

    .notification-details-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .7rem;
    }

    .notification-detail {
        padding: .85rem;
        border: 1px solid var(--border, #e8ebf0);
        border-radius: 12px;
        background: var(--body-bg, #f8f9fb);
    }

    .notification-detail span {
        display: block;
        margin-bottom: .25rem;
        color: var(--text-muted, #687386);
        font-size: .68rem;
    }

    .notification-detail strong {
        display: block;
        overflow-wrap: anywhere;
        color: var(--text, #172033);
        font-size: .82rem;
        font-weight: 800;
    }

    .notification-modal-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: .6rem;
        padding: 1rem 1.35rem;
        border-top: 1px solid var(--border, #e8ebf0);
        background: rgba(100, 116, 139, .025);
    }

    .notification-modal-link {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        padding: .65rem 1rem;
        border-radius: 11px;
        color: #fff;
        text-decoration: none;
        background: linear-gradient(135deg, #cda74f, #a8781b);
        box-shadow: 0 8px 20px rgba(168, 120, 27, .2);
        font-size: .78rem;
        font-weight: 800;
    }

    .notification-modal-link:hover {
        color: #fff;
        transform: translateY(-1px);
    }

    .notification-modal-link svg {
        width: 16px;
        height: 16px;
    }

    body.notification-modal-open {
        overflow: hidden;
    }

    .notifications-pagination {
        direction: rtl;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .55rem;
        flex-wrap: wrap;
        padding: 1.15rem;
        border-top: 1px solid var(--border, #e8ebf0);
        background: var(--card, #fff);
    }

    .pagination-button,
    .pagination-number {
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .35rem;
        padding: 0 .85rem;
        border: 1px solid var(--border, #e1e5ea);
        border-radius: 11px;
        color: var(--text-muted, #687386);
        background: var(--card, #fff);
        text-decoration: none;
        font-family: inherit;
        font-size: .78rem;
        font-weight: 800;
        transition: .2s ease;
    }

    .pagination-button svg {
        width: 16px !important;
        height: 16px !important;
        flex-shrink: 0;
    }

    .pagination-button:hover,
    .pagination-number:hover {
        color: #a8781b;
        border-color: rgba(188, 145, 48, .5);
        background: rgba(198, 155, 55, .08);
        transform: translateY(-1px);
    }

    .pagination-pages {
        display: flex;
        align-items: center;
        gap: .35rem;
    }

    .pagination-number {
        width: 38px;
        padding: 0;
    }

    .pagination-number.active {
        color: #fff;
        border-color: #b98a2d;
        background: linear-gradient(135deg, #d1aa4f, #a8781b);
        box-shadow: 0 7px 18px rgba(168, 120, 27, .22);
        pointer-events: none;
    }

    .pagination-disabled {
        color: #b8bec8;
        background: #f7f8fa;
        cursor: not-allowed;
        opacity: .65;
    }

    .pagination-disabled:hover {
        color: #b8bec8;
        border-color: var(--border, #e1e5ea);
        background: #f7f8fa;
        transform: none;
    }

    .pagination-dots {
        padding: 0 .15rem;
        color: var(--text-subtle, #929baa);
    }

    .pagination-summary {
        width: 100%;
        margin-top: .25rem;
        text-align: center;
        color: var(--text-muted, #687386);
        font-size: .72rem;
    }

    .pagination-summary strong {
        color: #a8781b;
        font-weight: 900;
    }

    @media (max-width: 768px) {
        .notifications-hero {
            align-items: stretch;
            flex-direction: column;
            padding: 1.2rem;
        }

        .notifications-stats {
            width: 100%;
        }

        .notification-stat {
            flex: 1;
            min-width: 0;
        }

        .notification-tools {
            align-items: stretch;
            flex-direction: column;
        }

        .notification-search {
            width: 100%;
        }

        .notification-filters {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
        }

        .notification-filter {
            padding-inline: .4rem;
        }

        .notification-item {
            gap: .4rem;
            padding: .9rem;
        }

        .notification-open {
            gap: .7rem;
        }

        .notification-icon {
            width: 39px;
            height: 39px;
            border-radius: 12px;
        }

        .notification-message {
            -webkit-line-clamp: 2;
        }

        .notification-type-label {
            display: none;
        }

        .notification-actions {
            flex-direction: column;
        }

        .notification-action-button {
            width: 33px;
            height: 33px;
        }

        .notification-details-grid {
            grid-template-columns: 1fr;
        }

        .notification-modal-panel {
            border-radius: 19px;
        }
    }

    @media (max-width: 480px) {
        .notifications-pagination {
            gap: .35rem;
            padding: .9rem .55rem;
        }

        .pagination-button {
            width: 38px;
            padding: 0;
        }

        .pagination-button span {
            display: none;
        }

        .pagination-number {
            width: 34px;
            height: 34px;
        }

        .notifications-hero-content {
            align-items: flex-start;
        }

        .notifications-hero-icon {
            width: 48px;
            height: 48px;
            border-radius: 15px;
        }

        .notifications-hero h2 {
            font-size: 1.08rem;
        }

        .notification-title {
            font-size: .82rem;
        }

        .notification-message {
            font-size: .75rem;
        }

        .notification-modal {
            align-items: flex-end;
            padding: 0;
        }

        .notification-modal-panel {
            width: 100%;
            max-height: 92vh;
            border-radius: 23px 23px 0 0;
        }
    }
</style>
@endpush

@section('content')
@php
    $items = $notifications ?? collect();

    $currentItems = $items instanceof \Illuminate\Pagination\AbstractPaginator
        ? $items->getCollection()
        : collect($items);

    $notificationsCount = $items instanceof \Illuminate\Pagination\AbstractPaginator
        ? $items->total()
        : $currentItems->count();

    $unreadCount = isset($unreadCount)
        ? (int) $unreadCount
        : $currentItems->whereNull('read_at')->count();

    $statusLabels = [
        'draft'           => 'مسودة',
        'pending'         => 'قيد الانتظار',
        'confirmed'       => 'مؤكد',
        'preparing'       => 'قيد التحضير',
        'ready'           => 'جاهز',
        'completed'       => 'مكتمل',
        'cancelled'       => 'ملغي',
        'payment_pending' => 'بانتظار الدفع',
        'paid'            => 'مدفوع',
        'failed'          => 'فشل',
        'refunded'        => 'مسترجع',

        // Special cake workflow
        'pending_factory_review' => 'بانتظار مراجعة المصنع',
        'accepted'               => 'مقبول',
        'rejected'               => 'مرفوض',
        'modification_requested' => 'مطلوب تعديل',
        'scheduled'              => 'مجدول للإنتاج',
        'in_preparation'         => 'قيد التحضير',
        'decorating'             => 'قيد التزيين',
        'quality_check'          => 'قيد فحص الجودة',
        'sent_to_branch'         => 'أُرسل إلى الفرع',
        'received_by_branch'     => 'استلمه الفرع',
        'ready_for_customer'     => 'جاهز للتسليم للعميل',
        'delayed'                => 'متأخر',
        'issue_open'             => 'توجد مشكلة',

        // Showroom sweets workflow
        'submitted'          => 'مُرسل للمصنع',
        'in_progress'        => 'قيد التجهيز',
        'ready_for_dispatch' => 'جاهز للتوصيل',
        'out_for_delivery'   => 'في الطريق إلى الفرع',
        'received_at_branch' => 'تم الاستلام في الفرع',
        'fulfilled'          => 'تم التنفيذ',
    ];

    $translateStatus = static function ($status) use ($statusLabels) {
        if ($status instanceof \BackedEnum) {
            $status = $status->value;
        }

        if (! filled($status)) {
            return null;
        }

        $status = (string) $status;

        return $statusLabels[$status] ?? str_replace('_', ' ', $status);
    };
@endphp

<div class="notifications-page">

    <section class="notifications-hero">
        <div class="notifications-hero-content">
            <div class="notifications-hero-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
            </div>

            <div>
                <h2>مركز الإشعارات</h2>
                <p>تابع الطلبات والمدفوعات والمخزون وجميع العمليات المهمة من مكان واحد.</p>
            </div>
        </div>

        <div class="notifications-stats">
            <div class="notification-stat">
                <strong>{{ $notificationsCount }}</strong>
                <span>إجمالي الإشعارات</span>
            </div>

            <div class="notification-stat">
                <strong id="unreadNotificationsCount">{{ $unreadCount }}</strong>
                <span>غير مقروء</span>
            </div>
        </div>
    </section>

    <div class="notification-tools">
        <div class="notification-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>

            <input
                type="search"
                id="notificationSearch"
                placeholder="ابحث في الإشعارات..."
                autocomplete="off"
            >
        </div>

        <div class="notification-filters" role="tablist">
            <button
                type="button"
                class="notification-filter active"
                data-filter="all"
                aria-selected="true"
            >
                الكل
            </button>

            <button
                type="button"
                class="notification-filter"
                data-filter="unread"
                aria-selected="false"
            >
                غير المقروء
            </button>

            <button
                type="button"
                class="notification-filter"
                data-filter="read"
                aria-selected="false"
            >
                المقروء
            </button>
        </div>
    </div>

    <div class="notifications-card">
        @if($currentItems->isEmpty())
            <div class="notifications-empty">
                <div class="notifications-empty-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                </div>

                <h3>لا توجد إشعارات</h3>
                <p>ستظهر إشعارات الطلبات والعمليات الجديدة هنا.</p>
            </div>
        @else
            <div id="notificationsList">
                @foreach($currentItems as $notif)
                    @php
                        $data = $notif->data ?? [];

                        if (is_string($data)) {
                            $data = json_decode($data, true) ?: [];
                        }

                        if (! is_array($data)) {
                            $data = [];
                        }

                        $title = $notif->title
                            ?? data_get($data, 'title')
                            ?? 'إشعار جديد';

                        $message = $notif->message
                            ?? data_get($data, 'message')
                            ?? data_get($data, 'body')
                            ?? '';

                        $rawType = data_get($data, 'type')
                            ?? ($notif->category ?? null)
                            ?? ($notif->type ?? 'info');

                        $normalizedType = strtolower((string) $rawType);

                        $semanticType = match (true) {
                            \Illuminate\Support\Str::contains($normalizedType, ['payment', 'invoice', 'دفع', 'فاتورة'])
                                => 'payment',

                            \Illuminate\Support\Str::contains($normalizedType, ['inventory', 'stock', 'lowstock', 'مخزون'])
                                => 'inventory',

                            \Illuminate\Support\Str::contains($normalizedType, ['warning', 'alert', 'تحذير'])
                                => 'warning',

                            \Illuminate\Support\Str::contains($normalizedType, ['order', 'cake', 'طلب'])
                                => 'order',

                            default => 'info',
                        };

                        $typeLabel = match ($semanticType) {
                            'order'     => 'طلب',
                            'inventory' => 'مخزون',
                            'payment'   => 'دفع',
                            'warning'   => 'تنبيه',
                            default     => 'عام',
                        };

                        $icon = match ($semanticType) {
                            'order' => '
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/>
                                    <rect x="9" y="3" width="6" height="4" rx="2"/>
                                </svg>
                            ',

                            'inventory' => '
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="2" y="7" width="20" height="14" rx="2"/>
                                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                                </svg>
                            ',

                            'payment' => '
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="2" y="5" width="20" height="14" rx="2"/>
                                    <line x1="2" y1="10" x2="22" y2="10"/>
                                </svg>
                            ',

                            'warning' => '
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                    <line x1="12" y1="9" x2="12" y2="13"/>
                                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                            ',

                            default => '
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="12" y1="8" x2="12" y2="12"/>
                                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                                </svg>
                            ',
                        };

                        $isUnread = is_null($notif->read_at);

                        $createdForHumans = $notif->created_at
                            ? $notif->created_at->diffForHumans()
                            : 'الآن';

                        $createdFullDate = $notif->created_at
                            ? $notif->created_at->translatedFormat('d F Y - h:i A')
                            : '';

                        $fromStatusValue = data_get($data, 'from_status')
                            ?? data_get($data, 'old_status');

                        $toStatusValue = data_get($data, 'to_status')
                            ?? data_get($data, 'new_status')
                            ?? data_get($data, 'status');

                        $details = collect([
                            [
                                'label' => 'رقم الطلب',
                                'value' => data_get($data, 'order_number')
                                    ?? data_get($data, 'cake_order_number'),
                            ],
                            [
                                'label' => 'رقم الفاتورة',
                                'value' => data_get($data, 'invoice_number'),
                            ],
                            [
                                'label' => 'الفرع',
                                'value' => data_get($data, 'location_name')
                                    ?? data_get($data, 'branch_name')
                                    ?? data_get($data, 'origin_branch_name'),
                            ],
                            [
                                'label' => 'المصنع',
                                'value' => data_get($data, 'factory_name'),
                            ],
                            [
                                'label' => 'العميل',
                                'value' => data_get($data, 'customer_name'),
                            ],
                            [
                                'label' => 'الحالة السابقة',
                                'value' => $translateStatus($fromStatusValue),
                            ],
                            [
                                'label' => 'الحالة الجديدة',
                                'value' => $translateStatus($toStatusValue),
                            ],
                            [
                                'label' => 'طريقة الدفع',
                                'value' => data_get($data, 'payment_method'),
                            ],
                            [
                                'label' => 'المبلغ',
                                'value' => data_get($data, 'total_amount')
                                    ?? data_get($data, 'amount'),
                            ],
                            [
                                'label' => 'المنتج',
                                'value' => data_get($data, 'product_name'),
                            ],
                            [
                                'label' => 'الكمية الحالية',
                                'value' => data_get($data, 'current_qty')
                                    ?? data_get($data, 'current_quantity'),
                            ],
                            [
                                'label' => 'الحد الأدنى',
                                'value' => data_get($data, 'minimum_qty')
                                    ?? data_get($data, 'minimum_quantity'),
                            ],
                        ])->filter(fn ($detail) => filled($detail['value']));

                        $actionUrl = data_get($data, 'action_url')
                            ?? data_get($data, 'url')
                            ?? ($notif->action_url ?? null);

                        if (
                            ! $actionUrl &&
                            data_get($data, 'order_id') &&
                            \Illuminate\Support\Facades\Route::has('orders.show')
                        ) {
                            $actionUrl = route('orders.show', data_get($data, 'order_id'));
                        }

                        if (
                            ! is_string($actionUrl) ||
                            ! preg_match('/^(https?:\\/\\/|\\/)/i', $actionUrl)
                        ) {
                            $actionUrl = null;
                        }

                        $searchText = \Illuminate\Support\Str::lower(
                            $title.' '.$message.' '.$typeLabel
                        );
                    @endphp

                    <article
                        class="notification-item {{ $isUnread ? 'notification-unread' : '' }}"
                        data-notification-id="{{ $notif->id }}"
                        data-read="{{ $isUnread ? 'no' : 'yes' }}"
                        data-search="{{ $searchText }}"
                    >
                        <button
                            type="button"
                            class="notification-open js-open-notification"
                            data-template="notification-details-{{ $notif->id }}"
                            data-title="{{ $title }}"
                            data-message="{{ $message }}"
                            data-time="{{ $createdFullDate }}"
                            data-type="{{ $typeLabel }}"
                            data-semantic-type="{{ $semanticType }}"
                            data-icon="{{ base64_encode($icon) }}"
                            data-action-url="{{ $actionUrl }}"
                            data-read-url="{{ $isUnread ? route('notifications.read', $notif->id) : '' }}"
                            data-unread="{{ $isUnread ? '1' : '0' }}"
                        >
                            <span class="notification-icon type-{{ $semanticType }}">
                                {!! $icon !!}
                            </span>

                            <span class="notification-content">
                                <span class="notification-heading">
                                    <span class="notification-title">
                                        {{ $title }}
                                    </span>

                                    @if($isUnread)
                                        <span class="notification-dot"></span>
                                    @endif
                                </span>

                                <span class="notification-message">
                                    {{ $message ?: 'اضغط لعرض تفاصيل هذا الإشعار.' }}
                                </span>

                                <span class="notification-meta">
                                    <span>{{ $createdForHumans }}</span>
                                    <span class="notification-type-label">
                                        {{ $typeLabel }}
                                    </span>
                                </span>
                            </span>
                        </button>

                        <div class="notification-actions">
                            <button
                                type="button"
                                class="notification-action-button js-open-notification"
                                data-template="notification-details-{{ $notif->id }}"
                                data-title="{{ $title }}"
                                data-message="{{ $message }}"
                                data-time="{{ $createdFullDate }}"
                                data-type="{{ $typeLabel }}"
                                data-semantic-type="{{ $semanticType }}"
                                data-icon="{{ base64_encode($icon) }}"
                                data-action-url="{{ $actionUrl }}"
                                data-read-url="{{ $isUnread ? route('notifications.read', $notif->id) : '' }}"
                                data-unread="{{ $isUnread ? '1' : '0' }}"
                                title="عرض التفاصيل"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>

                            @if($isUnread)
                                <form
                                    action="{{ route('notifications.read', $notif->id) }}"
                                    method="POST"
                                    class="js-read-notification-form"
                                >
                                    @csrf

                                    <button
                                        type="submit"
                                        class="notification-action-button"
                                        title="تعليم كمقروء"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="20 6 9 17 4 12"/>
                                        </svg>
                                    </button>
                                </form>
                            @endif
                        </div>

                        <template id="notification-details-{{ $notif->id }}">
                            @if($details->isNotEmpty())
                                <h4 class="notification-details-title">
                                    تفاصيل العملية
                                </h4>

                                <div class="notification-details-grid">
                                    @foreach($details as $detail)
                                        <div class="notification-detail">
                                            <span>{{ $detail['label'] }}</span>
                                            <strong>{{ $detail['value'] }}</strong>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </template>
                    </article>
                @endforeach
            </div>

            <div
                id="notificationFilterEmpty"
                class="notification-filter-empty"
                hidden
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>

                <div>لا توجد إشعارات مطابقة لعملية البحث.</div>
            </div>

            @if(
                $items instanceof \Illuminate\Pagination\LengthAwarePaginator &&
                $items->hasPages()
            )
                @php
                    $startPage = max(1, $items->currentPage() - 2);
                    $endPage = min($items->lastPage(), $items->currentPage() + 2);
                @endphp

                <nav class="notifications-pagination" aria-label="صفحات الإشعارات">
                    @if($items->onFirstPage())
                        <span class="pagination-button pagination-disabled">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"/>
                            </svg>
                            <span>السابق</span>
                        </span>
                    @else
                        <a href="{{ $items->previousPageUrl() }}" class="pagination-button" rel="prev">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"/>
                            </svg>
                            <span>السابق</span>
                        </a>
                    @endif

                    <div class="pagination-pages">
                        @if($startPage > 1)
                            <a href="{{ $items->url(1) }}" class="pagination-number">1</a>

                            @if($startPage > 2)
                                <span class="pagination-dots">…</span>
                            @endif
                        @endif

                        @for($page = $startPage; $page <= $endPage; $page++)
                            @if($page === $items->currentPage())
                                <span class="pagination-number active" aria-current="page">
                                    {{ $page }}
                                </span>
                            @else
                                <a href="{{ $items->url($page) }}" class="pagination-number">
                                    {{ $page }}
                                </a>
                            @endif
                        @endfor

                        @if($endPage < $items->lastPage())
                            @if($endPage < $items->lastPage() - 1)
                                <span class="pagination-dots">…</span>
                            @endif

                            <a href="{{ $items->url($items->lastPage()) }}" class="pagination-number">
                                {{ $items->lastPage() }}
                            </a>
                        @endif
                    </div>

                    @if($items->hasMorePages())
                        <a href="{{ $items->nextPageUrl() }}" class="pagination-button" rel="next">
                            <span>التالي</span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="15 18 9 12 15 6"/>
                            </svg>
                        </a>
                    @else
                        <span class="pagination-button pagination-disabled">
                            <span>التالي</span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="15 18 9 12 15 6"/>
                            </svg>
                        </span>
                    @endif

                    <div class="pagination-summary">
                        عرض
                        <strong>{{ $items->firstItem() }}</strong>
                        إلى
                        <strong>{{ $items->lastItem() }}</strong>
                        من
                        <strong>{{ $items->total() }}</strong>
                        إشعار
                    </div>
                </nav>
            @endif
        @endif
    </div>
</div>

<div
    class="notification-modal"
    id="notificationDetailsModal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="notificationModalTitle"
    hidden
>
    <button
        type="button"
        class="notification-modal-overlay js-close-notification-modal"
        aria-label="إغلاق"
    ></button>

    <div class="notification-modal-panel">
        <div class="notification-modal-header">
            <div
                class="notification-modal-icon"
                id="notificationModalIcon"
            ></div>

            <div class="notification-modal-heading">
                <h3 id="notificationModalTitle">تفاصيل الإشعار</h3>

                <div>
                    <span id="notificationModalType"></span>
                    <span>•</span>
                    <span id="notificationModalTime"></span>
                </div>
            </div>

            <button
                type="button"
                class="notification-modal-close js-close-notification-modal"
                aria-label="إغلاق"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <div class="notification-modal-body">
            <p
                class="notification-modal-message"
                id="notificationModalMessage"
            ></p>

            <div id="notificationModalDetails"></div>
        </div>

        <div class="notification-modal-footer">
            <a
                href="#"
                class="notification-modal-link"
                id="notificationModalAction"
                hidden
            >
                عرض العملية

                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="19" y1="12" x2="5" y2="12"/>
                    <polyline points="12 19 5 12 12 5"/>
                </svg>
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('notificationDetailsModal');
    const modalTitle = document.getElementById('notificationModalTitle');
    const modalMessage = document.getElementById('notificationModalMessage');
    const modalTime = document.getElementById('notificationModalTime');
    const modalType = document.getElementById('notificationModalType');
    const modalIcon = document.getElementById('notificationModalIcon');
    const modalDetails = document.getElementById('notificationModalDetails');
    const modalAction = document.getElementById('notificationModalAction');

    const searchInput = document.getElementById('notificationSearch');
    const filterButtons = document.querySelectorAll('.notification-filter');
    const notificationItems = document.querySelectorAll('.notification-item');
    const filterEmpty = document.getElementById('notificationFilterEmpty');
    const unreadCounter = document.getElementById('unreadNotificationsCount');

    const csrfToken =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || @json(csrf_token());

    let activeFilter = 'all';

    function openModal(button) {
        const templateId = button.dataset.template;
        const template = document.getElementById(templateId);

        modalTitle.textContent = button.dataset.title || 'تفاصيل الإشعار';
        modalMessage.textContent =
            button.dataset.message || 'لا توجد تفاصيل إضافية لهذا الإشعار.';

        modalTime.textContent = button.dataset.time || '';
        modalType.textContent = button.dataset.type || 'إشعار';

        try {
            modalIcon.innerHTML = atob(button.dataset.icon || '');
        } catch (error) {
            modalIcon.innerHTML = '';
        }

        modalIcon.className =
            'notification-modal-icon type-' +
            (button.dataset.semanticType || 'info');

        modalDetails.replaceChildren();

        if (template) {
            modalDetails.appendChild(template.content.cloneNode(true));
        }

        const actionUrl = button.dataset.actionUrl;

        if (actionUrl) {
            modalAction.href = actionUrl;
            modalAction.hidden = false;
        } else {
            modalAction.removeAttribute('href');
            modalAction.hidden = true;
        }

        modal.hidden = false;
        document.body.classList.add('notification-modal-open');

        const notificationItem = button.closest('.notification-item');

        if (
            notificationItem &&
            button.dataset.unread === '1' &&
            button.dataset.readUrl
        ) {
            markAsRead(button.dataset.readUrl, notificationItem);
        }
    }

    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('notification-modal-open');
    }

    async function markAsRead(url, notificationItem) {
        if (!url || notificationItem.dataset.read === 'yes') {
            return true;
        }

        try {
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('تعذر تحديث الإشعار');
            }

            notificationItem.dataset.read = 'yes';
            notificationItem.classList.remove('notification-unread');

            notificationItem
                .querySelector('.notification-dot')
                ?.remove();

            notificationItem
                .querySelector('.js-read-notification-form')
                ?.remove();

            notificationItem
                .querySelectorAll('.js-open-notification')
                .forEach(function (button) {
                    button.dataset.unread = '0';
                    button.dataset.readUrl = '';
                });

            const currentUnreadCount = Number(unreadCounter?.textContent || 0);

            if (unreadCounter && currentUnreadCount > 0) {
                unreadCounter.textContent = currentUnreadCount - 1;
            }

            applyFilters();

            return true;
        } catch (error) {
            console.error(error);

            return false;
        }
    }

    function applyFilters() {
        const query = (searchInput?.value || '')
            .trim()
            .toLocaleLowerCase();

        let visibleItems = 0;

        notificationItems.forEach(function (item) {
            const readState = item.dataset.read;
            const searchText = (item.dataset.search || '')
                .toLocaleLowerCase();

            const matchesSearch =
                !query || searchText.includes(query);

            const matchesFilter =
                activeFilter === 'all' ||
                (activeFilter === 'unread' && readState === 'no') ||
                (activeFilter === 'read' && readState === 'yes');

            const shouldShow = matchesSearch && matchesFilter;

            item.hidden = !shouldShow;

            if (shouldShow) {
                visibleItems++;
            }
        });

        if (filterEmpty) {
            filterEmpty.hidden = visibleItems !== 0;
        }
    }

    document
        .querySelectorAll('.js-open-notification')
        .forEach(function (button) {
            button.addEventListener('click', function () {
                openModal(button);
            });
        });

    document
        .querySelectorAll('.js-close-notification-modal')
        .forEach(function (button) {
            button.addEventListener('click', closeModal);
        });

    document
        .querySelectorAll('.js-read-notification-form')
        .forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                event.preventDefault();

                const notificationItem = form.closest('.notification-item');
                const submitButton = form.querySelector('button');

                if (submitButton) {
                    submitButton.disabled = true;
                }

                const success = await markAsRead(
                    form.action,
                    notificationItem
                );

                if (!success && submitButton) {
                    submitButton.disabled = false;
                }
            });
        });

    filterButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            activeFilter = button.dataset.filter || 'all';

            filterButtons.forEach(function (filterButton) {
                const isActive = filterButton === button;

                filterButton.classList.toggle('active', isActive);
                filterButton.setAttribute(
                    'aria-selected',
                    isActive ? 'true' : 'false'
                );
            });

            applyFilters();
        });
    });

    searchInput?.addEventListener('input', applyFilters);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });
});
</script>
@endpush