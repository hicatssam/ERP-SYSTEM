@extends('layouts.app')

@section('title', 'حسابات الدفع - ' . $location->name)

@php
    // أيقونة SVG احتياطية تُستخدم فقط إذا ما في لوجو مرفوع لطريقة الدفع
    if (!function_exists('paIconFor')) {
        function paIconFor($type)
        {
            return match ($type) {
                'bank_transfer' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="22" x2="21" y2="22"/><line x1="6" y1="18" x2="6" y2="11"/><line x1="10" y1="18" x2="10" y2="11"/><line x1="14" y1="18" x2="14" y2="11"/><line x1="18" y1="18" x2="18" y2="11"/><polygon points="12 2 20 7 4 7"/></svg>',
                'electronic_wallet' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>',
                'cash' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>',
                'card_pos' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><line x1="6" y1="15" x2="10" y2="15"/></svg>',
                default => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
            };
        }
    }

    // رابط اللوجو الفعلي لطريقة الدفع (لو موجود)، وإلا null فنرجع للأيقونة
    if (!function_exists('paLogoUrl')) {
        function paLogoUrl($method)
        {
            if (!$method) {
                return null;
            }

            $raw = $method->logo_path ?: $method->logo;

            if (!$raw) {
                return null;
            }

            if (preg_match('#^https?://#i', $raw)) {
                return $raw;
            }

            return asset('storage/' . ltrim($raw, '/'));
        }
    }
@endphp

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-heading">
            حسابات الدفع
        </h1>

        <p class="page-subheading">
            إدارة حسابات الدفع الخاصة بفرع:
            <strong>{{ $location->name }}</strong>
        </p>
    </div>

    <div style="display:flex;gap:0.5rem">

        <button
            type="button"
            class="btn btn-gold"
            onclick="paOpenAddModal()"
        >
            + إضافة حساب دفع
        </button>

        <a
            href="{{ route('locations.index') }}"
            class="btn btn-ghost"
        >
            العودة للفروع
        </a>

    </div>
</div>


@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:1rem">
        <strong>فيه أخطاء لازم تنتبه لها:</strong>
        <ul style="margin:0.4rem 0 0;padding-inline-start:1.2rem">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif


{{-- شبكة بطاقات الحسابات --}}

<div class="pa-accounts-grid">

    @forelse($accounts as $account)

        @php
            $rowType = $account->paymentMethod->type ?? 'other';
            $rowLogo = paLogoUrl($account->paymentMethod);
            $hasTransferData = $account->account_holder_name || $account->account_number || $account->iban || $account->phone_number;
        @endphp

        <div class="pa-account-card">

            <div class="pa-account-card-top">

                <div class="pa-account-titles">
                    <span class="pa-account-name">{{ $account->name }}</span>
                    @if($account->provider_name)
                        <span class="pa-account-provider">{{ $account->provider_name }}</span>
                    @endif
                </div>

                <span class="pa-account-badge-icon">
                    @if($rowLogo)
                        <img src="{{ $rowLogo }}" alt="{{ $account->paymentMethod->name ?? '' }}">
                    @else
                        {!! paIconFor($rowType) !!}
                    @endif
                </span>

            </div>

            <div class="pa-account-method-tag">
                {{ $account->paymentMethod?->name_ar ?? $account->paymentMethod?->name ?? '—' }}
            </div>

            <div class="pa-account-divider"></div>

            <div class="pa-transfer-block">

                <span class="pa-transfer-title">بيانات التحويل البنكي</span>

                @if($hasTransferData)

                    <dl class="pa-transfer-list">

                        @if($account->account_holder_name)
                            <div class="pa-transfer-row">
                                <dt>صاحب الحساب</dt>
                                <dd>{{ $account->account_holder_name }}</dd>
                            </div>
                        @endif

                        @if($account->account_number)
                            <div class="pa-transfer-row">
                                <dt>رقم الحساب</dt>
                                <dd>{{ $account->account_number }}</dd>
                            </div>
                        @endif

                        @if($account->iban)
                            <div class="pa-transfer-row">
                                <dt>IBAN</dt>
                                <dd>{{ $account->iban }}</dd>
                            </div>
                        @endif

                        @if($account->phone_number)
                            <div class="pa-transfer-row">
                                <dt>الجوال</dt>
                                <dd>📱 {{ $account->phone_number }}</dd>
                            </div>
                        @endif

                    </dl>

                @else

                    <p class="pa-no-transfer">لا توجد بيانات تحويل (دفع نقدي)</p>

                @endif

            </div>

            <div class="pa-account-card-footer">

                <div class="pa-account-meta">

                    @if($account->is_active)
                        <span class="badge badge-success">فعال</span>
                    @else
                        <span class="badge badge-danger">موقوف</span>
                    @endif

                    <span class="pa-sort-order">الترتيب: {{ $account->sort_order }}</span>

                </div>

                <div class="pa-actions">

                    <button
                        type="button"
                        class="btn btn-ghost btn-sm"
                        onclick="paOpenEditModal({
                            id: '{{ $account->id }}',
                            payment_method_id: '{{ $account->payment_method_id }}',
                            name: `{{ addslashes($account->name) }}`,
                            provider_name: `{{ addslashes($account->provider_name) }}`,
                            account_holder_name: `{{ addslashes($account->account_holder_name) }}`,
                            account_number: `{{ addslashes($account->account_number) }}`,
                            iban: `{{ addslashes($account->iban) }}`,
                            phone_number: `{{ addslashes($account->phone_number) }}`,
                            instructions: `{{ addslashes($account->instructions) }}`,
                            sort_order: '{{ $account->sort_order }}',
                            is_active: {{ $account->is_active ? 'true' : 'false' }},
                            updateUrl: '{{ route('locations.payment-accounts.update', [$location, $account]) }}'
                        })"
                    >
                        تعديل
                    </button>

                    <form
                        method="POST"
                        action="{{ route('locations.payment-accounts.destroy', [$location, $account]) }}"
                        onsubmit="return confirm('هل تريد حذف هذا الحساب؟')"
                        style="display:inline"
                    >

                        @csrf
                        @method('DELETE')

                        <button
                            class="btn btn-danger btn-sm"
                            type="submit"
                        >
                            حذف
                        </button>

                    </form>

                </div>

            </div>

        </div>

    @empty

        <div class="pa-empty-state">
            <p>لا توجد حسابات دفع مضافة لهذا الفرع.</p>
            <button type="button" class="btn btn-gold" onclick="paOpenAddModal()">+ إضافة أول حساب دفع</button>
        </div>

    @endforelse

</div>


{{-- Modal: إضافة حساب --}}

<div id="paAddModalBackdrop" class="pa-modal-backdrop" style="display:none">

    <div class="pa-modal">

        <div class="pa-modal-header">
            <span class="card-title">إضافة حساب دفع</span>
            <button type="button" class="pa-modal-close" onclick="paCloseAddModal()">×</button>
        </div>

        <form
            method="POST"
            action="{{ route('locations.payment-accounts.store', $location) }}"
        >

            @csrf

            <div class="pa-modal-body">

                <fieldset class="pa-section">

                    <legend class="pa-section-title">معلومات الحساب</legend>

                    <div class="form-group">

                        <label class="form-label">
                            طريقة الدفع
                        </label>

                        <div class="pa-method-grid" id="create_payment_method_grid">

                            @foreach($paymentMethods as $method)
                                @php
                                    $methodLogo = paLogoUrl($method);
                                @endphp

                                <label class="pa-method-card" data-type="{{ $method->type }}">

                                    <input
                                        type="radio"
                                        name="payment_method_id"
                                        value="{{ $method->id }}"
                                        onchange="paTogglePaymentFields(this, 'create')"
                                        @checked(old('payment_method_id') == $method->id)
                                        hidden
                                        required
                                    >

                                    <span class="pa-method-icon">
                                        @if($methodLogo)
                                            <img src="{{ $methodLogo }}" alt="{{ $method->name }}">
                                        @else
                                            {!! paIconFor($method->type) !!}
                                        @endif
                                    </span>

                                    <span class="pa-method-name">{{ $method->name_ar ?? $method->name }}</span>

                                </label>

                            @endforeach

                        </div>

                        <small class="form-hint">
                            اختر طريقة الدفع لتظهر الحقول المناسبة لها أدناه.
                        </small>

                    </div>


                    <div class="form-group">

                        <label class="form-label">
                            الاسم الظاهر للعميل
                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-input"
                            placeholder="مثال: بنك فلسطين"
                            value="{{ old('name') }}"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label class="form-label">
                            اسم المزود
                        </label>

                        <input
                            type="text"
                            name="provider_name"
                            class="form-input"
                            placeholder="مثال: Bank of Palestine"
                            value="{{ old('provider_name') }}"
                        >

                    </div>

                </fieldset>


                <fieldset class="pa-section" id="create_transfer_section">

                    <legend class="pa-section-title">بيانات التحويل البنكي</legend>

                    <div class="form-group" data-field-group="account_holder_name">

                        <label class="form-label">
                            اسم صاحب الحساب
                        </label>

                        <input
                            type="text"
                            name="account_holder_name"
                            class="form-input"
                            value="{{ old('account_holder_name') }}"
                        >

                    </div>


                    <div class="form-group" data-field-group="account_number">

                        <label class="form-label">
                            رقم الحساب
                        </label>

                        <input
                            type="text"
                            name="account_number"
                            class="form-input"
                            value="{{ old('account_number') }}"
                        >

                    </div>


                    <div class="form-group" data-field-group="iban">

                        <label class="form-label">
                            IBAN
                        </label>

                        <input
                            type="text"
                            name="iban"
                            class="form-input"
                            value="{{ old('iban') }}"
                        >

                    </div>


                    <div class="form-group" data-field-group="phone_number">

                        <label class="form-label">
                            رقم الجوال
                        </label>

                        <input
                            type="text"
                            name="phone_number"
                            class="form-input"
                            placeholder="059XXXXXXX"
                            value="{{ old('phone_number') }}"
                        >

                    </div>

                    <p
                        class="form-hint"
                        data-cash-note
                        style="display:none"
                    >
                        لا توجد بيانات تحويل مطلوبة لطريقة الدفع النقدي.
                    </p>

                </fieldset>


                <fieldset class="pa-section">

                    <legend class="pa-section-title">إعدادات العرض</legend>

                    <div class="form-group">

                        <label class="form-label">
                            تعليمات للعميل
                        </label>

                        <textarea
                            name="instructions"
                            class="form-input"
                            rows="3"
                        >{{ old('instructions') }}</textarea>

                    </div>


                    <div class="form-group">

                        <label class="form-label">
                            الترتيب
                        </label>

                        <input
                            type="number"
                            name="sort_order"
                            value="{{ old('sort_order', 0) }}"
                            min="0"
                            class="form-input"
                        >

                    </div>


                    <div class="form-group">

                        <label class="pa-checkbox-label">

                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                @checked(old('is_active', true))
                            >

                            حساب فعال

                        </label>

                    </div>

                </fieldset>

            </div>

            <div class="pa-modal-footer">
                <button type="button" class="btn btn-ghost" onclick="paCloseAddModal()">إلغاء</button>
                <button type="submit" class="btn btn-gold">+ إضافة الحساب</button>
            </div>

        </form>

    </div>

</div>


{{-- Modal: تعديل حساب --}}

<div id="paEditModalBackdrop" class="pa-modal-backdrop" style="display:none">

    <div class="pa-modal">

        <div class="pa-modal-header">
            <span class="card-title">تعديل حساب الدفع</span>
            <button type="button" class="pa-modal-close" onclick="paCloseEditModal()">×</button>
        </div>

        <form
            id="paEditForm"
            method="POST"
            action=""
        >

            @csrf
            @method('PUT')

            <input type="hidden" name="account_id" id="edit_account_id_hidden">

            <div class="pa-modal-body">

                <fieldset class="pa-section">

                    <legend class="pa-section-title">معلومات الحساب</legend>

                    <div class="form-group">

                        <label class="form-label">طريقة الدفع</label>

                        <div class="pa-method-grid" id="edit_payment_method_grid">

                            @foreach($paymentMethods as $method)
                                @php
                                    $methodLogo = paLogoUrl($method);
                                @endphp

                                <label class="pa-method-card" data-type="{{ $method->type }}">

                                    <input
                                        type="radio"
                                        name="payment_method_id"
                                        value="{{ $method->id }}"
                                        onchange="paTogglePaymentFields(this, 'edit')"
                                        hidden
                                        required
                                    >

                                    <span class="pa-method-icon">
                                        @if($methodLogo)
                                            <img src="{{ $methodLogo }}" alt="{{ $method->name }}">
                                        @else
                                            {!! paIconFor($method->type) !!}
                                        @endif
                                    </span>

                                    <span class="pa-method-name">{{ $method->name_ar ?? $method->name }}</span>

                                </label>

                            @endforeach

                        </div>

                    </div>

                    <div class="form-group">
                        <label class="form-label">الاسم الظاهر للعميل</label>
                        <input type="text" name="name" id="edit_name" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">اسم المزود</label>
                        <input type="text" name="provider_name" id="edit_provider_name" class="form-input">
                    </div>

                </fieldset>

                <fieldset class="pa-section" id="edit_transfer_section">

                    <legend class="pa-section-title">بيانات التحويل البنكي</legend>

                    <div class="form-group" data-field-group="account_holder_name">
                        <label class="form-label">اسم صاحب الحساب</label>
                        <input type="text" name="account_holder_name" id="edit_account_holder_name" class="form-input">
                    </div>

                    <div class="form-group" data-field-group="account_number">
                        <label class="form-label">رقم الحساب</label>
                        <input type="text" name="account_number" id="edit_account_number" class="form-input">
                    </div>

                    <div class="form-group" data-field-group="iban">
                        <label class="form-label">IBAN</label>
                        <input type="text" name="iban" id="edit_iban" class="form-input">
                    </div>

                    <div class="form-group" data-field-group="phone_number">
                        <label class="form-label">رقم الجوال</label>
                        <input type="text" name="phone_number" id="edit_phone_number" class="form-input">
                    </div>

                    <p class="form-hint" data-cash-note style="display:none">
                        لا توجد بيانات تحويل مطلوبة لطريقة الدفع النقدي.
                    </p>

                </fieldset>

                <fieldset class="pa-section">

                    <legend class="pa-section-title">إعدادات العرض</legend>

                    <div class="form-group">
                        <label class="form-label">تعليمات للعميل</label>
                        <textarea name="instructions" id="edit_instructions" class="form-input" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">الترتيب</label>
                        <input type="number" name="sort_order" id="edit_sort_order" min="0" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="pa-checkbox-label">
                            <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                            حساب فعال
                        </label>
                    </div>

                </fieldset>

            </div>

            <div class="pa-modal-footer">
                <button type="button" class="btn btn-ghost" onclick="paCloseEditModal()">إلغاء</button>
                <button type="submit" class="btn btn-gold">حفظ التعديلات</button>
            </div>

        </form>

    </div>

</div>


<style>
    /* ===== شبكة بطاقات الحسابات ===== */

    .pa-accounts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1rem;
    }

    .pa-account-card {
        display: flex;
        flex-direction: column;
        background: var(--card-bg, #fff);
        border: 1px solid var(--border-color, #e5e5e5);
        border-radius: 12px;
        padding: 1rem 1.1rem;
        transition: border-color .15s ease, box-shadow .15s ease;
    }

    .pa-account-card:hover {
        border-color: var(--gold, #c9a24a);
        box-shadow: 0 4px 14px rgba(0,0,0,0.06);
    }

    .pa-account-card-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
    }

    .pa-account-titles {
        display: flex;
        flex-direction: column;
        gap: 0.1rem;
    }

    .pa-account-name {
        font-weight: 700;
        font-size: 1rem;
    }

    .pa-account-provider {
        font-size: 0.78rem;
        opacity: 0.6;
    }

    .pa-account-badge-icon {
        flex-shrink: 0;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(201, 162, 74, 0.12);
        color: var(--gold, #c9a24a);
        overflow: hidden;
    }

    .pa-account-badge-icon svg {
        width: 20px;
        height: 20px;
    }

    .pa-account-badge-icon img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        background: #fff;
    }

    .pa-account-method-tag {
        display: inline-block;
        margin-top: 0.6rem;
        font-size: 0.72rem;
        font-weight: 600;
        padding: 0.15rem 0.55rem;
        border-radius: 999px;
        background: rgba(0,0,0,0.05);
        width: fit-content;
        opacity: 0.75;
    }

    .pa-account-divider {
        height: 1px;
        background: var(--border-color, #eee);
        margin: 0.85rem 0;
    }

    .pa-transfer-title {
        display: block;
        font-size: 0.75rem;
        font-weight: 700;
        opacity: 0.55;
        margin-bottom: 0.5rem;
    }

    .pa-transfer-list {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        margin: 0;
    }

    .pa-transfer-row {
        display: flex;
        justify-content: space-between;
        gap: 0.5rem;
        font-size: 0.85rem;
    }

    .pa-transfer-row dt {
        opacity: 0.6;
    }

    .pa-transfer-row dd {
        margin: 0;
        font-weight: 600;
        text-align: left;
        word-break: break-all;
    }

    .pa-no-transfer {
        margin: 0;
        font-size: 0.82rem;
        opacity: 0.55;
    }

    .pa-account-card-footer {
        margin-top: 1rem;
        padding-top: 0.85rem;
        border-top: 1px dashed var(--border-color, #eee);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .pa-account-meta {
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }

    .pa-sort-order {
        font-size: 0.75rem;
        opacity: 0.55;
    }

    .pa-actions {
        display: flex;
        gap: 0.4rem;
        white-space: nowrap;
    }

    .pa-empty-state {
        grid-column: 1 / -1;
        text-align: center;
        padding: 3rem 1rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
        opacity: 0.85;
    }

    /* ===== نموذج الإضافة/التعديل ===== */

    .pa-section {
        border: 1px solid var(--border-color, #e5e5e5);
        border-radius: 8px;
        padding: 0.75rem 1rem 0.25rem;
        margin: 0 0 1rem;
    }

    .pa-section-title {
        padding: 0 0.4rem;
        font-size: 0.85rem;
        font-weight: 600;
        opacity: 0.75;
    }

    .pa-checkbox-label {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        cursor: pointer;
    }

    .form-hint {
        display: block;
        font-size: 0.75rem;
        opacity: 0.6;
        margin-top: 0.2rem;
    }

    .pa-method-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
        gap: 0.5rem;
        margin: 0.35rem 0;
    }

    .pa-method-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        padding: 0.65rem 0.4rem;
        border: 1.5px solid var(--border-color, #e2e2e2);
        border-radius: 10px;
        cursor: pointer;
        text-align: center;
        transition: border-color .15s ease, background-color .15s ease, transform .1s ease;
        user-select: none;
    }

    .pa-method-card:hover {
        border-color: var(--gold, #c9a24a);
    }

    .pa-method-card:active {
        transform: scale(0.97);
    }

    .pa-method-card.is-active {
        border-color: var(--gold, #c9a24a);
        background: rgba(201, 162, 74, 0.1);
    }

    .pa-method-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
    }

    .pa-method-icon svg {
        width: 22px;
        height: 22px;
        display: block;
    }

    .pa-method-icon img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .pa-method-name {
        font-size: 0.72rem;
        font-weight: 600;
        line-height: 1.2;
    }

    /* ===== Modal مشترك ===== */

    .pa-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: 1rem;
    }

    .pa-modal {
        background: var(--card-bg, #fff);
        width: min(560px, 92vw);
        max-height: 88vh;
        overflow-y: auto;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    }

    .pa-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--border-color, #e5e5e5);
    }

    .pa-modal-body {
        padding: 1rem 1.25rem;
    }

    .pa-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
        padding: 1rem 1.25rem;
        border-top: 1px solid var(--border-color, #e5e5e5);
    }

    .pa-modal-close {
        background: none;
        border: none;
        font-size: 1.4rem;
        line-height: 1;
        cursor: pointer;
        opacity: 0.6;
    }

    .pa-modal-close:hover {
        opacity: 1;
    }
</style>


<script>
    // إظهار/إخفاء الحقول حسب طريقة الدفع المختارة (بالاعتماد على enum: cash / electronic_wallet / bank_transfer / card_pos / other)
    function paTogglePaymentFields(radioEl, scope) {

        var card = radioEl.closest('.pa-method-card');
        var type = card ? card.getAttribute('data-type') : null;

        var grid = radioEl.closest('.pa-method-grid');
        if (grid) {
            grid.querySelectorAll('.pa-method-card').forEach(function (c) {
                c.classList.remove('is-active');
            });
            if (card) card.classList.add('is-active');
        }

        var sectionId = scope === 'edit' ? 'edit_transfer_section' : 'create_transfer_section';
        var section = document.getElementById(sectionId);

        if (!section) return;

        var groups = section.querySelectorAll('[data-field-group]');
        var cashNote = section.querySelector('[data-cash-note]');

        if (!type) {
            groups.forEach(function (g) { g.style.display = ''; });
            if (cashNote) cashNote.style.display = 'none';
            return;
        }

        if (type === 'cash') {
            groups.forEach(function (g) { g.style.display = 'none'; });
            if (cashNote) cashNote.style.display = 'block';
            return;
        }

        if (cashNote) cashNote.style.display = 'none';

        groups.forEach(function (g) {
            var field = g.getAttribute('data-field-group');

            if (type === 'bank_transfer') {
                // بنكي: صاحب الحساب + رقم الحساب + IBAN، وإخفاء الجوال
                g.style.display = (field === 'phone_number') ? 'none' : '';
            } else if (type === 'electronic_wallet') {
                // محفظة إلكترونية: صاحب الحساب + الجوال، وإخفاء رقم الحساب و IBAN
                g.style.display = (field === 'account_number' || field === 'iban') ? 'none' : '';
            } else if (type === 'card_pos') {
                // بطاقة/POS: صاحب الحساب + رقم الحساب، وإخفاء IBAN والجوال
                g.style.display = (field === 'iban' || field === 'phone_number') ? 'none' : '';
            } else {
                // other: عرض كل الحقول بدون افتراضات
                g.style.display = '';
            }
        });
    }

    // تحديد بطاقة طريقة دفع معينة برمجيًا (تستخدم عند فتح مودال التعديل)
    function paSelectMethodCard(gridId, methodId, scope) {

        var grid = document.getElementById(gridId);

        if (!grid) return;

        var radios = grid.querySelectorAll('input[name="payment_method_id"]');
        var matched = null;

        radios.forEach(function (radio) {
            var card = radio.closest('.pa-method-card');
            var isMatch = String(radio.value) === String(methodId);

            radio.checked = isMatch;

            if (card) {
                card.classList.toggle('is-active', isMatch);
            }

            if (isMatch) matched = radio;
        });

        if (matched) {
            paTogglePaymentFields(matched, scope);
        } else {
            var sectionId = scope === 'edit' ? 'edit_transfer_section' : 'create_transfer_section';
            var section = document.getElementById(sectionId);
            if (section) {
                section.querySelectorAll('[data-field-group]').forEach(function (g) { g.style.display = ''; });
                var note = section.querySelector('[data-cash-note]');
                if (note) note.style.display = 'none';
            }
        }
    }

    // فتح/إغلاق مودال الإضافة
    function paOpenAddModal() {
        document.getElementById('paAddModalBackdrop').style.display = 'flex';
    }

    function paCloseAddModal() {
        document.getElementById('paAddModalBackdrop').style.display = 'none';
    }

    // فتح مودال التعديل وتعبئته ببيانات الحساب
    function paOpenEditModal(data) {

        document.getElementById('paEditForm').action = data.updateUrl;
        document.getElementById('edit_account_id_hidden').value = data.id || '';

        paSelectMethodCard('edit_payment_method_grid', data.payment_method_id, 'edit');

        document.getElementById('edit_name').value = data.name === 'null' ? '' : data.name;
        document.getElementById('edit_provider_name').value = data.provider_name === 'null' ? '' : data.provider_name;
        document.getElementById('edit_account_holder_name').value = data.account_holder_name === 'null' ? '' : data.account_holder_name;
        document.getElementById('edit_account_number').value = data.account_number === 'null' ? '' : data.account_number;
        document.getElementById('edit_iban').value = data.iban === 'null' ? '' : data.iban;
        document.getElementById('edit_phone_number').value = data.phone_number === 'null' ? '' : data.phone_number;
        document.getElementById('edit_instructions').value = data.instructions === 'null' ? '' : data.instructions;
        document.getElementById('edit_sort_order').value = data.sort_order || 0;
        document.getElementById('edit_is_active').checked = !!data.is_active;

        document.getElementById('paEditModalBackdrop').style.display = 'flex';
    }

    function paCloseEditModal() {
        document.getElementById('paEditModalBackdrop').style.display = 'none';
    }

    document.getElementById('paAddModalBackdrop').addEventListener('click', function (e) {
        if (e.target === this) {
            paCloseAddModal();
        }
    });

    document.getElementById('paEditModalBackdrop').addEventListener('click', function (e) {
        if (e.target === this) {
            paCloseEditModal();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        var checkedCreate = document.querySelector('#create_payment_method_grid input[name="payment_method_id"]:checked');
        if (checkedCreate) {
            paTogglePaymentFields(checkedCreate, 'create');
        }
    });

    // إعادة فتح مودال التعديل تلقائيًا إذا رجعت أخطاء فاليديشن من عملية تعديل
    @if($errors->any() && old('_method') === 'PUT' && old('account_id'))
        document.addEventListener('DOMContentLoaded', function () {
            paOpenEditModal({
                id: '{{ old('account_id') }}',
                payment_method_id: '{{ old('payment_method_id') }}',
                name: `{{ addslashes(old('name', '')) }}`,
                provider_name: `{{ addslashes(old('provider_name', '')) }}`,
                account_holder_name: `{{ addslashes(old('account_holder_name', '')) }}`,
                account_number: `{{ addslashes(old('account_number', '')) }}`,
                iban: `{{ addslashes(old('iban', '')) }}`,
                phone_number: `{{ addslashes(old('phone_number', '')) }}`,
                instructions: `{{ addslashes(old('instructions', '')) }}`,
                sort_order: '{{ old('sort_order', 0) }}',
                is_active: {{ old('is_active') ? 'true' : 'false' }},
                updateUrl: '{{ route('locations.payment-accounts.update', [$location, old('account_id')]) }}'
            });
        });
    @endif

    // إعادة فتح مودال الإضافة تلقائيًا إذا رجعت أخطاء فاليديشن من عملية إضافة
    @if($errors->any() && old('_method') !== 'PUT')
        document.addEventListener('DOMContentLoaded', function () {
            paOpenAddModal();
        });
    @endif
</script>

@endsection