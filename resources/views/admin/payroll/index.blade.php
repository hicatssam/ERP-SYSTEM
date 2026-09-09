@extends('layouts.app')

@section('title', 'الرواتب وكشوف الموظفين')

@section('content')
<style>
    .payroll-page {
        max-width: 1220px;
        margin: 0 auto;
    }

    .payroll-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .payroll-header-actions {
        display: flex;
        align-items: center;
        gap: .65rem;
        flex-wrap: wrap;
    }

    .payroll-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .9rem;
        margin-bottom: 1rem;
    }

    .payroll-stat {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 1rem;
        min-height: 110px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .payroll-stat-label {
        color: var(--text-muted);
        font-size: .8rem;
    }

    .payroll-stat-value {
        font-size: 1.45rem;
        font-weight: 800;
        color: var(--text);
    }

    .payroll-stat-note {
        color: var(--text-muted);
        font-size: .72rem;
    }

    .payroll-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .payroll-toolbar-title {
        display: flex;
        align-items: center;
        gap: .65rem;
    }

    .payroll-toolbar-icon {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        display: grid;
        place-items: center;
        color: var(--theme-primary);
        background: color-mix(in srgb, var(--theme-primary) 10%, transparent);
    }

    .payroll-table-wrap {
        overflow-x: auto;
    }

    .payroll-table {
        width: 100%;
        border-collapse: collapse;
    }

    .payroll-table th,
    .payroll-table td {
        padding: .95rem .85rem;
        text-align: right;
        border-bottom: 1px solid var(--border);
        white-space: nowrap;
    }

    .payroll-table th {
        font-size: .76rem;
        color: var(--text-muted);
        font-weight: 800;
        background: color-mix(in srgb, var(--surface) 88%, var(--background));
    }

    .payroll-table td {
        font-size: .86rem;
        color: var(--text);
    }

    .payroll-period-name {
        display: flex;
        flex-direction: column;
        gap: .2rem;
    }

    .payroll-period-name strong {
        font-size: .9rem;
    }

    .payroll-period-name small {
        color: var(--text-muted);
    }

    .payroll-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .35rem;
        border-radius: 999px;
        padding: .3rem .65rem;
        font-size: .72rem;
        font-weight: 800;
    }

    .payroll-status-draft {
        background: color-mix(in srgb, var(--theme-info) 12%, transparent);
        color: var(--theme-info);
    }

    .payroll-status-calculated {
        background: color-mix(in srgb, var(--theme-warning) 14%, transparent);
        color: var(--theme-warning);
    }

    .payroll-status-approved {
        background: color-mix(in srgb, var(--theme-primary) 12%, transparent);
        color: var(--theme-primary);
    }

    .payroll-status-paid,
    .payroll-status-closed {
        background: color-mix(in srgb, var(--theme-success) 12%, transparent);
        color: var(--theme-success);
    }

    .payroll-empty {
        padding: 3rem 1.5rem;
        text-align: center;
        color: var(--text-muted);
    }

    .payroll-empty-icon {
        width: 68px;
        height: 68px;
        margin: 0 auto .9rem;
        border-radius: 18px;
        display: grid;
        place-items: center;
        color: var(--theme-primary);
        background: color-mix(in srgb, var(--theme-primary) 10%, transparent);
    }

    /* Modal */
    .payroll-modal {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .payroll-modal.is-open {
        display: flex;
    }

    .payroll-modal-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, .56);
        backdrop-filter: blur(3px);
    }

    .payroll-modal-dialog {
        position: relative;
        z-index: 2;
        width: min(720px, 100%);
        max-height: calc(100vh - 2rem);
        overflow-y: auto;
        background: var(--surface);
        color: var(--text);
        border: 1px solid var(--border);
        border-radius: 20px;
        box-shadow: 0 24px 70px rgba(0, 0, 0, .22);
    }

    .payroll-modal-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.15rem 1.25rem;
        border-bottom: 1px solid var(--border);
    }

    .payroll-modal-header h3 {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 800;
    }

    .payroll-modal-header p {
        margin: .25rem 0 0;
        color: var(--text-muted);
        font-size: .78rem;
    }

    .payroll-modal-close {
        width: 38px;
        height: 38px;
        border: 0;
        border-radius: 11px;
        display: grid;
        place-items: center;
        cursor: pointer;
        color: var(--text-muted);
        background: color-mix(in srgb, var(--border) 55%, transparent);
    }

    .payroll-modal-close:hover {
        color: var(--theme-danger);
        background: color-mix(in srgb, var(--theme-danger) 8%, transparent);
    }

    .payroll-modal-body {
        padding: 1.25rem;
    }

    .payroll-modal-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }

    .payroll-modal-grid .full {
        grid-column: 1 / -1;
    }

    .payroll-modal-footer {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: .7rem;
        padding: 1rem 1.25rem 1.25rem;
        border-top: 1px solid var(--border);
    }

    .payroll-currency-chip {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        border-radius: 999px;
        padding: .25rem .55rem;
        font-size: .72rem;
        background: color-mix(in srgb, var(--theme-primary) 9%, transparent);
        color: var(--theme-primary);
        font-weight: 700;
    }

    @media (max-width: 900px) {
        .payroll-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 700px) {
        .payroll-header,
        .payroll-toolbar {
            align-items: stretch;
            flex-direction: column;
        }

        .payroll-header-actions .btn,
        .payroll-toolbar .btn {
            width: 100%;
        }

        .payroll-stats,
        .payroll-modal-grid {
            grid-template-columns: 1fr;
        }

        .payroll-modal-grid .full {
            grid-column: auto;
        }
    }
</style>

@php
    $periodCollection = $periods->getCollection();

    $draftCount = $periodCollection->where('status', 'draft')->count();
    $calculatedCount = $periodCollection->where('status', 'calculated')->count();
    $approvedCount = $periodCollection->where('status', 'approved')->count();
    $paidCount = $periodCollection
        ->filter(fn ($period) => in_array($period->status, ['paid', 'closed'], true))
        ->count();

    $statusLabels = [
        'draft' => 'مسودة',
        'calculated' => 'محتسبة',
        'approved' => 'معتمدة',
        'paid' => 'مدفوعة',
        'closed' => 'مغلقة',
    ];
@endphp

<div class="payroll-page">
    <div class="payroll-header">
        <div>
            <h1 class="page-heading">الرواتب وكشوف الموظفين</h1>
            <p class="page-subheading">
                إدارة دورات الرواتب، الاستحقاقات، الدفعات وكشوف حساب الموظفين.
            </p>
        </div>

        <div class="payroll-header-actions">
            @can('payroll.manage')
                <button
                    type="button"
                    class="btn btn-gold"
                    id="openPayrollCreateModal"
                >
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 5v14M5 12h14"/>
                    </svg>

                    إنشاء دورة رواتب
                </button>
            @endcan
        </div>
    </div>

    <div class="payroll-stats">
        <div class="payroll-stat">
            <span class="payroll-stat-label">المسودات</span>
            <strong class="payroll-stat-value">{{ $draftCount }}</strong>
            <span class="payroll-stat-note">دورات لم يتم احتسابها بعد</span>
        </div>

        <div class="payroll-stat">
            <span class="payroll-stat-label">محتسبة</span>
            <strong class="payroll-stat-value">{{ $calculatedCount }}</strong>
            <span class="payroll-stat-note">جاهزة للمراجعة والاعتماد</span>
        </div>

        <div class="payroll-stat">
            <span class="payroll-stat-label">معتمدة</span>
            <strong class="payroll-stat-value">{{ $approvedCount }}</strong>
            <span class="payroll-stat-note">مستحقات تم ترحيلها للموظفين</span>
        </div>

        <div class="payroll-stat">
            <span class="payroll-stat-label">مدفوعة / مغلقة</span>
            <strong class="payroll-stat-value">{{ $paidCount }}</strong>
            <span class="payroll-stat-note">دورات تم إنهاء صرفها</span>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="payroll-toolbar">
                <div class="payroll-toolbar-title">
                    <div class="payroll-toolbar-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="5" width="18" height="14" rx="2"/>
                            <path d="M7 9h10M7 13h4M15 13h2"/>
                        </svg>
                    </div>

                    <div>
                        <div class="card-title">دورات الرواتب</div>
                        <small class="text-muted">
                            جميع دورات الرواتب المسجلة في النظام
                        </small>
                    </div>
                </div>

                @if($baseCurrency)
                    <span class="payroll-currency-chip">
                        العملة الأساسية:
                        {{ $baseCurrency->code }}
                        {{ $baseCurrency->symbol }}
                    </span>
                @endif
            </div>
        </div>

        <div class="card-body" style="padding:0">
            @if($periods->count())
                <div class="payroll-table-wrap">
                    <table class="payroll-table">
                        <thead>
                            <tr>
                                <th>الكود</th>
                                <th>دورة الرواتب</th>
                                <th>الفترة</th>
                                <th>الحالة</th>
                                <th>العملة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($periods as $period)
                                <tr>
                                    <td>
                                        <strong>{{ $period->code }}</strong>
                                    </td>

                                    <td>
                                        <div class="payroll-period-name">
                                            <strong>{{ $period->name }}</strong>

                                            @if($period->notes)
                                                <small>{{ \Illuminate\Support\Str::limit($period->notes, 55) }}</small>
                                            @endif
                                        </div>
                                    </td>

                                    <td>
                                        {{ $period->start_date?->format('Y-m-d') }}
                                        <span class="text-muted">—</span>
                                        {{ $period->end_date?->format('Y-m-d') }}
                                    </td>

                                    <td>
                                        <span class="payroll-status payroll-status-{{ $period->status }}">
                                            {{ $statusLabels[$period->status] ?? \App\Support\ArabicDisplay::status($period->status) }}
                                        </span>
                                    </td>

                                    <td>
                                        {{ $baseCurrency?->displayName() ?? 'غير محددة' }}
                                    </td>

                                    <td>
                                        <a
                                            class="btn btn-sm btn-outline"
                                            href="{{ route('payroll.show', $period) }}"
                                        >
                                            فتح الدورة
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="payroll-empty">
                    <div class="payroll-empty-icon">
                        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="5" width="18" height="14" rx="2"/>
                            <path d="M7 9h10M7 13h4M15 13h2"/>
                        </svg>
                    </div>

                    <strong>لا توجد دورات رواتب بعد</strong>

                    <p style="margin:.4rem 0 0">
                        أنشئ أول دورة رواتب للبدء باحتساب مستحقات الموظفين.
                    </p>
                </div>
            @endif
        </div>
    </div>

    @if($periods->hasPages())
        <div style="margin-top:1rem">
            {{ $periods->links() }}
        </div>
    @endif
</div>

{{-- =========================================================
    Create Payroll Period Modal
========================================================= --}}
@can('payroll.manage')
<div
    class="payroll-modal"
    id="payrollCreateModal"
    aria-hidden="true"
>
    <div
        class="payroll-modal-backdrop"
        data-payroll-modal-close
    ></div>

    <div
        class="payroll-modal-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="payrollCreateModalTitle"
    >
        <div class="payroll-modal-header">
            <div>
                <h3 id="payrollCreateModalTitle">
                    إنشاء دورة رواتب جديدة
                </h3>

                <p>
                    حدد الفترة التي سيتم على أساسها احتساب رواتب الموظفين.
                </p>
            </div>

            <button
                type="button"
                class="payroll-modal-close"
                data-payroll-modal-close
                aria-label="إغلاق"
            >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6 6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form
            method="POST"
            action="{{ route('payroll.periods.store') }}"
        >
            @csrf

            <div class="payroll-modal-body">
                @if($errors->any())
                    <div class="alert alert-danger" style="margin-bottom:1rem">
                        <strong>تعذر إنشاء دورة الرواتب:</strong>

                        <ul style="margin:.5rem 0 0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="payroll-modal-grid">
                    <div class="form-group full">
                        <label class="form-label">
                            اسم الدورة *
                        </label>

                        <input
                            class="form-input"
                            name="name"
                            value="{{ old('name') }}"
                            placeholder="مثال: رواتب أغسطس 2026"
                            maxlength="190"
                            required
                            autofocus
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            من تاريخ *
                        </label>

                        <input
                            class="form-input"
                            type="date"
                            name="start_date"
                            value="{{ old('start_date') }}"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            إلى تاريخ *
                        </label>

                        <input
                            class="form-input"
                            type="date"
                            name="end_date"
                            value="{{ old('end_date') }}"
                            required
                        >
                    </div>

                    <div class="form-group full">
                        <label class="form-label">
                            ملاحظات
                        </label>

                        <textarea
                            class="form-input"
                            name="notes"
                            rows="4"
                            maxlength="1000"
                            placeholder="أي ملاحظات خاصة بهذه الدورة..."
                        >{{ old('notes') }}</textarea>
                    </div>

                    @if($baseCurrency)
                        <div class="full">
                            <div
                                style="
                                    display:flex;
                                    align-items:center;
                                    justify-content:space-between;
                                    gap:1rem;
                                    padding:.8rem .9rem;
                                    border:1px solid var(--border);
                                    border-radius:12px;
                                    background:color-mix(in srgb,var(--theme-primary) 5%,transparent);
                                "
                            >
                                <span class="text-muted">
                                    العملة التي ستستخدمها الدورة
                                </span>

                                <strong>
                                    {{ $baseCurrency->code }}
                                    {{ $baseCurrency->symbol }}
                                </strong>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="payroll-modal-footer">
                <button
                    type="submit"
                    class="btn btn-gold"
                >
                    إنشاء دورة الرواتب
                </button>

                <button
                    type="button"
                    class="btn btn-ghost"
                    data-payroll-modal-close
                >
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>
@endcan

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('payrollCreateModal');
        const openButton = document.getElementById('openPayrollCreateModal');

        if (!modal) {
            return;
        }

        const closeButtons = modal.querySelectorAll('[data-payroll-modal-close]');

        const openModal = () => {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';

            setTimeout(() => {
                const firstInput = modal.querySelector('input[name="name"]');
                firstInput?.focus();
            }, 80);
        };

        const closeModal = () => {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        };

        openButton?.addEventListener('click', openModal);

        closeButtons.forEach((button) => {
            button.addEventListener('click', closeModal);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                closeModal();
            }
        });

        @if($errors->any())
            openModal();
        @endif
    });
</script>
@endpush
@endsection
