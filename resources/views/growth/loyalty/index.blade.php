@extends('layouts.app')

@section('title', 'برنامج الولاء')

@section('content')
    @include('growth._styles')

    <style>
        .loyalty-page-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: .75rem;
            flex-wrap: wrap;
        }

        .loyalty-input-with-unit {
            position: relative;
        }

        .loyalty-input-with-unit .form-input {
            padding-inline-end: 4rem;
        }

        .loyalty-input-unit {
            position: absolute;
            inset-inline-end: .9rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: .77rem;
            pointer-events: none;
        }

        .loyalty-inline-action {
            display: grid;
            grid-template-columns: minmax(100px, 130px) auto;
            align-items: end;
            gap: .45rem;
        }

        .loyalty-action-label {
            display: block;
            margin-bottom: .3rem;
            color: var(--text-muted);
            font-size: .72rem;
            white-space: nowrap;
        }

        .loyalty-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 2147483000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(15, 23, 42, .56);
            backdrop-filter: blur(4px);
        }

        .loyalty-modal-overlay.is-open {
            display: flex;
        }

        .loyalty-modal {
            width: min(760px, 100%);
            max-height: calc(100vh - 48px);
            overflow-y: auto;
            background: var(--surface);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, .28);
        }

        .loyalty-modal-header {
            position: sticky;
            top: 0;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.2rem;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
        }

        .loyalty-modal-title {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 800;
        }

        .loyalty-modal-close {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: transparent;
            color: var(--text);
            font-size: 1.35rem;
            cursor: pointer;
        }

        .loyalty-modal-body {
            padding: 1.2rem;
        }

        .loyalty-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .95rem;
        }

        .loyalty-field {
            min-width: 0;
        }

        .loyalty-field-full {
            grid-column: 1 / -1;
        }

        .loyalty-help {
            display: block;
            margin-top: .35rem;
            color: var(--text-muted);
            font-size: .78rem;
            line-height: 1.7;
        }

        .loyalty-program-note {
            margin-top: 1rem;
            padding: .85rem 1rem;
            border: 1px dashed var(--border);
            border-radius: 12px;
            background: color-mix(in srgb, var(--theme-primary, #0F3D66) 4%, transparent);
            color: var(--text-muted);
            line-height: 1.8;
        }

        .loyalty-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: .7rem;
            margin-top: 1.1rem;
            flex-wrap: wrap;
        }

        body.loyalty-modal-open {
            overflow: hidden;
        }

        @media (max-width: 760px) {
            .loyalty-form-grid {
                grid-template-columns: 1fr;
            }

            .loyalty-field-full {
                grid-column: auto;
            }

            .loyalty-inline-action {
                grid-template-columns: 1fr;
            }

            .loyalty-modal-overlay {
                padding: 12px;
            }

            .loyalty-modal {
                max-height: calc(100vh - 24px);
            }
        }
    </style>

    {{-- =========================================================
        Header
    ========================================================== --}}
    <div class="page-header">
        <div>
            <h1 class="page-heading">برنامج الولاء</h1>

            <p class="page-subheading">
                دفتر نقاط مستقل للعملاء؛ لا يغيّر الفواتير أو المدفوعات تلقائيًا.
            </p>
        </div>

        @can('loyalty.manage')
            <div class="loyalty-page-actions">
                <button
                    type="button"
                    class="btn btn-gold"
                    id="openLoyaltyProgramModal"
                >
                    + إضافة برنامج
                </button>
            </div>
        @endcan
    </div>

    {{-- =========================================================
        Active Program Summary
    ========================================================== --}}
    @if($program)
        <div class="growth-grid">
            <div class="growth-stat">
                <small>البرنامج الفعّال</small>
                <strong>{{ $program->name }}</strong>
            </div>

            <div class="growth-stat">
                <small>النقاط المكتسبة لكل 1 ₪ مبيعات</small>
                <strong>{{ number_format((float) $program->points_per_currency_unit, 4) }}</strong>
            </div>

            <div class="growth-stat">
                <small>الحد الأدنى للطلب المؤهل</small>
                <strong>₪{{ number_format((float) $program->minimum_order_amount, 2) }}</strong>
            </div>

            <div class="growth-stat">
                <small>الحد الأدنى لنقاط الاستبدال</small>
                <strong>{{ number_format($program->minimum_redeem_points) }} نقطة</strong>
            </div>

            <div class="growth-stat">
                <small>القيمة الاسمية لكل نقطة</small>
                <strong>₪{{ number_format((float) $program->redemption_value_per_point, 4) }}</strong>
            </div>
        </div>
    @else
        <div class="alert alert-warning">
            لا يوجد برنامج ولاء فعّال حاليًا، لذلك لن يكتسب العملاء نقاطًا تلقائيًا
            حتى يتم إنشاء برنامج وتفعيله.
        </div>
    @endif

    {{-- =========================================================
        Customer Balances
    ========================================================== --}}
    <div class="card growth-section">
        <div class="card-header">
            <span class="card-title">أرصدة العملاء</span>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>العميل</th>
                        <th>الرصيد الحالي</th>
                        <th>إجمالي مكتسب</th>
                        <th>إجمالي مستبدل</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($accounts as $account)
                        <tr>
                            <td>
                                <a href="{{ route('crm.customers.show', $account->customer_id) }}">
                                    {{ $account->customer?->name }}
                                </a>

                                <br>

                                <small>{{ $account->customer?->phone }}</small>
                            </td>

                            <td>
                                <strong>
                                    {{ number_format($account->points_balance) }} نقطة
                                </strong>
                            </td>

                            <td>
                                {{ number_format($account->lifetime_earned) }} نقطة
                            </td>

                            <td>
                                {{ number_format($account->lifetime_redeemed) }} نقطة
                            </td>

                            <td>
                                <div class="growth-actions">
                                    @can('loyalty.adjust')
                                        <form
                                            method="POST"
                                            action="{{ route(
                                                'loyalty.customers.adjust',
                                                $account->customer_id
                                            ) }}"
                                            class="loyalty-inline-action"
                                        >
                                            @csrf

                                            <div>
                                                <label class="loyalty-action-label">
                                                    تعديل يدوي (+ / -)
                                                </label>

                                                <input
                                                    class="form-input"
                                                    type="number"
                                                    name="points"
                                                    placeholder="مثال: 50 أو -20"
                                                    required
                                                >
                                            </div>

                                            <input
                                                type="hidden"
                                                name="reason"
                                                value="تعديل يدوي من شاشة الولاء"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-outline btn-sm"
                                            >
                                                تعديل
                                            </button>
                                        </form>
                                    @endcan

                                    @can('loyalty.redeem')
                                        <form
                                            method="POST"
                                            action="{{ route(
                                                'loyalty.customers.redeem',
                                                $account->customer_id
                                            ) }}"
                                            class="loyalty-inline-action"
                                        >
                                            @csrf

                                            <div>
                                                <label class="loyalty-action-label">
                                                    استبدال نقاط
                                                </label>

                                                <input
                                                    class="form-input"
                                                    type="number"
                                                    min="1"
                                                    name="points"
                                                    placeholder="عدد النقاط"
                                                    required
                                                >
                                            </div>

                                            <input
                                                type="hidden"
                                                name="reason"
                                                value="استبدال نقاط يدوي"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-gold btn-sm"
                                            >
                                                استبدال
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state-sm">
                                    لا توجد حسابات ولاء حتى الآن.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div style="margin-top: 1rem;">
        {{ $accounts->links() }}
    </div>

    {{-- =========================================================
        Add Loyalty Program Modal
    ========================================================== --}}
    @can('loyalty.manage')
        <div
            class="loyalty-modal-overlay"
            id="loyaltyProgramModal"
            aria-hidden="true"
        >
            <div
                class="loyalty-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="loyaltyProgramModalTitle"
            >
                <div class="loyalty-modal-header">
                    <h2
                        class="loyalty-modal-title"
                        id="loyaltyProgramModalTitle"
                    >
                        إضافة برنامج ولاء
                    </h2>

                    <button
                        type="button"
                        class="loyalty-modal-close"
                        id="closeLoyaltyProgramModal"
                        aria-label="إغلاق"
                    >
                        ×
                    </button>
                </div>

                <div class="loyalty-modal-body">
                    <form
                        method="POST"
                        action="{{ route('loyalty.programs.store') }}"
                        id="loyaltyProgramForm"
                    >
                        @csrf

                        <div class="loyalty-form-grid">
                            <div class="loyalty-field loyalty-field-full">
                                <label class="form-label" for="loyalty_name">
                                    اسم البرنامج *
                                </label>

                                <input
                                    class="form-input"
                                    id="loyalty_name"
                                    name="name"
                                    value="{{ old('name', 'برنامج الولاء') }}"
                                    placeholder="مثال: برنامج الولاء"
                                    required
                                >

                                <small class="loyalty-help">
                                    الاسم الذي سيظهر في شاشة إدارة برنامج الولاء.
                                </small>
                            </div>

                            <div class="loyalty-field">
                                <label class="form-label" for="points_per_currency_unit">
                                    نقاط لكل 1 ₪ مبيعات *
                                </label>

                                <div class="loyalty-input-with-unit">
                                    <input
                                        class="form-input"
                                        id="points_per_currency_unit"
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        name="points_per_currency_unit"
                                        value="{{ old('points_per_currency_unit', 1) }}"
                                        required
                                    >

                                    <span class="loyalty-input-unit">نقطة</span>
                                </div>

                                <small class="loyalty-help">
                                    مثال: 1 يعني أن طلبًا بقيمة 50 ₪ يكتسب 50 نقطة.
                                </small>
                            </div>

                            <div class="loyalty-field">
                                <label class="form-label" for="minimum_order_amount">
                                    الحد الأدنى لقيمة الطلب المؤهل *
                                </label>

                                <div class="loyalty-input-with-unit">
                                    <input
                                        class="form-input"
                                        id="minimum_order_amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        name="minimum_order_amount"
                                        value="{{ old('minimum_order_amount', 0) }}"
                                        required
                                    >

                                    <span class="loyalty-input-unit">₪</span>
                                </div>

                                <small class="loyalty-help">
                                    ضع 0 إذا أردت احتساب النقاط لكل الطلبات.
                                </small>
                            </div>

                            <div class="loyalty-field">
                                <label class="form-label" for="redemption_value_per_point">
                                    القيمة الاسمية لكل نقطة *
                                </label>

                                <div class="loyalty-input-with-unit">
                                    <input
                                        class="form-input"
                                        id="redemption_value_per_point"
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        name="redemption_value_per_point"
                                        value="{{ old('redemption_value_per_point', 0) }}"
                                        required
                                    >

                                    <span class="loyalty-input-unit">₪ / نقطة</span>
                                </div>

                                <small class="loyalty-help">
                                    مثال: 0.10 يعني أن 100 نقطة قيمتها الاسمية 10 ₪.
                                </small>
                            </div>

                            <div class="loyalty-field">
                                <label class="form-label" for="minimum_redeem_points">
                                    الحد الأدنى للاستبدال *
                                </label>

                                <div class="loyalty-input-with-unit">
                                    <input
                                        class="form-input"
                                        id="minimum_redeem_points"
                                        type="number"
                                        min="0"
                                        name="minimum_redeem_points"
                                        value="{{ old('minimum_redeem_points', 0) }}"
                                        required
                                    >

                                    <span class="loyalty-input-unit">نقطة</span>
                                </div>

                                <small class="loyalty-help">
                                    أقل عدد نقاط يسمح للعميل ببدء الاستبدال.
                                </small>
                            </div>

                            <div class="loyalty-field loyalty-field-full">
                                <label class="form-check">
                                    <input
                                        type="checkbox"
                                        name="is_active"
                                        value="1"
                                        @checked(old('is_active'))
                                    >
                                    تفعيل هذا البرنامج مباشرة بعد الحفظ
                                </label>
                            </div>
                        </div>

                        <div class="loyalty-program-note">
                            <strong>مهم:</strong>
                            قيمة الاستبدال مرجعية فقط في Sprint 08.
                            تسجيل الاستبدال لا ينشئ خصمًا تلقائيًا على الفاتورة.
                        </div>

                        <div class="loyalty-modal-footer">
                            <button
                                type="button"
                                class="btn btn-ghost"
                                id="cancelLoyaltyProgramModal"
                            >
                                إلغاء
                            </button>

                            <button
                                type="submit"
                                class="btn btn-gold"
                            >
                                حفظ البرنامج
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan
@endsection

@push('scripts')
    @can('loyalty.manage')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.getElementById('loyaltyProgramModal');
                const openButton = document.getElementById('openLoyaltyProgramModal');
                const closeButton = document.getElementById('closeLoyaltyProgramModal');
                const cancelButton = document.getElementById('cancelLoyaltyProgramModal');

                if (!modal || !openButton) {
                    return;
                }

                const openModal = function () {
                    modal.classList.add('is-open');
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('loyalty-modal-open');

                    requestAnimationFrame(function () {
                        document.getElementById('loyalty_name')?.focus();
                    });
                };

                const closeModal = function () {
                    modal.classList.remove('is-open');
                    modal.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('loyalty-modal-open');
                };

                openButton.addEventListener('click', openModal);
                closeButton?.addEventListener('click', closeModal);
                cancelButton?.addEventListener('click', closeModal);

                modal.addEventListener('click', function (event) {
                    if (event.target === modal) {
                        closeModal();
                    }
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                        closeModal();
                    }
                });

                @if($errors->any())
                    openModal();
                @endif
            });
        </script>
    @endcan
@endpush