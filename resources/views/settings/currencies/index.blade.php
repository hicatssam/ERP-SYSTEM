@extends('layouts.app')

@section('title', 'إدارة العملات')
@section('page-title', 'إدارة العملات')

@section('content')

@php
    $currencyPayload = $currencies->getCollection()->map(function ($currency) {
        return [
            'id' => $currency->id,
            'code' => $currency->code,
            'name_ar' => $currency->name_ar,
            'name_en' => $currency->name,
            'symbol' => $currency->symbol,
            'icon' => $currency->icon,
            'image_url' => $currency->image_url,
            'decimal_places' => $currency->decimal_places,
            'is_active' => $currency->is_active,
            'is_base' => $currency->is_base,
            'sort_order' => $currency->sort_order,
            'notes' => $currency->notes,
            'update_url' => route('settings.currencies.update', $currency),
            'delete_url' => route('settings.currencies.destroy', $currency),
        ];
    })->values();
@endphp

<style>
    .currency-filter {
        display: grid;
        grid-template-columns: minmax(220px, 1fr) 180px auto auto;
        gap: .65rem;
        align-items: center;
    }

    .currency-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .currency-card {
        border: 1px solid var(--theme-border, #e5e7eb);
        border-radius: var(--theme-radius, 12px);
        background: var(--theme-surface, #fff);
        padding: 1rem;
    }

    .currency-card-head {
        display: flex;
        gap: .9rem;
        align-items: center;
    }

    .currency-avatar {
        width: 64px;
        height: 64px;
        flex: 0 0 64px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        border: 1px solid var(--theme-border, #e5e7eb);
        background: var(--theme-bg, #f8fafc);
        font-size: 1.35rem;
        font-weight: 800;
        overflow: hidden;
    }

    .currency-avatar img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        padding: 7px;
    }

    .currency-main {
        min-width: 0;
        flex: 1;
    }

    .currency-title-row {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
        align-items: center;
    }

    .currency-title-row h3 {
        margin: 0;
        font-size: 1rem;
    }

    .currency-code {
        margin-top: .3rem;
        font-weight: 800;
        direction: ltr;
        text-align: right;
    }

    .currency-code span {
        margin: 0 .25rem;
        color: var(--theme-text-muted, #6b7280);
    }

    .currency-en {
        margin-top: .2rem;
        color: var(--theme-text-muted, #6b7280);
        font-size: .8rem;
        direction: ltr;
        text-align: right;
    }

    .currency-badge {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        padding: .2rem .55rem;
        border-radius: 999px;
        font-size: .72rem;
        font-weight: 700;
    }

    .currency-badge.base {
        background: color-mix(in srgb, var(--theme-warning, #d99b22) 15%, transparent);
        color: var(--theme-warning, #b7791f);
    }

    .currency-badge.active {
        background: color-mix(in srgb, var(--theme-success, #16a34a) 13%, transparent);
        color: var(--theme-success, #15803d);
    }

    .currency-badge.inactive {
        background: color-mix(in srgb, var(--theme-danger, #dc2626) 10%, transparent);
        color: var(--theme-danger, #b91c1c);
    }

    .currency-meta {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .6rem;
        margin-top: .9rem;
        padding-top: .9rem;
        border-top: 1px solid var(--theme-border, #e5e7eb);
    }

    .currency-meta div {
        display: flex;
        justify-content: space-between;
        gap: .5rem;
        font-size: .82rem;
    }

    .currency-meta span {
        color: var(--theme-text-muted, #6b7280);
    }

    .currency-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
        margin-top: .9rem;
    }

    .currency-actions form {
        margin: 0;
    }

    .currency-info-box {
        margin-top: 1rem;
        padding: .8rem .9rem;
        border: 1px solid color-mix(
            in srgb,
            var(--theme-info, #2563eb) 22%,
            transparent
        );
        border-radius: var(--theme-radius, 10px);
        background: color-mix(
            in srgb,
            var(--theme-info, #2563eb) 7%,
            transparent
        );
        color: var(--theme-text, #111827);
        font-size: .8rem;
        line-height: 1.7;
    }

    /* Modal */
    .currency-modal {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .currency-modal.is-open {
        display: flex;
    }

    .currency-modal-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, .60);
        backdrop-filter: blur(2px);
    }

    .currency-modal-dialog {
        position: relative;
        z-index: 1;
        width: min(900px, 100%);
        max-height: calc(100vh - 40px);
        overflow: auto;
        border: 1px solid var(--theme-border, #e5e7eb);
        border-radius: 16px;
        background: var(--theme-surface, #fff);
        box-shadow: 0 24px 70px rgba(0, 0, 0, .24);
    }

    .currency-modal-dialog.small {
        width: min(500px, 100%);
    }

    .currency-modal-header {
        position: sticky;
        top: 0;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.1rem;
        border-bottom: 1px solid var(--theme-border, #e5e7eb);
        background: var(--theme-surface, #fff);
    }

    .currency-modal-header h2 {
        margin: 0;
        font-size: 1.05rem;
    }

    .currency-modal-close {
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 9px;
        background: var(--theme-bg, #f3f4f6);
        color: var(--theme-text, #111827);
        cursor: pointer;
        font-size: 1.25rem;
    }

    .currency-modal-body {
        padding: 1.1rem;
    }

    .currency-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: .65rem;
        padding: 1rem 1.1rem;
        border-top: 1px solid var(--theme-border, #e5e7eb);
    }

    .currency-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }

    .currency-help {
        display: block;
        margin-top: .35rem;
        color: var(--theme-text-muted, #6b7280);
        font-size: .78rem;
        line-height: 1.55;
    }

    .currency-switches {
        display: grid;
        grid-template-columns: 1fr;
        gap: .8rem;
        margin-top: 1rem;
    }

    .currency-switch-card {
        display: flex;
        align-items: flex-start;
        gap: .65rem;
        padding: .85rem;
        border: 1px solid var(--theme-border, #e5e7eb);
        border-radius: 10px;
        cursor: pointer;
    }

    .currency-switch-card input[type="checkbox"] {
        margin-top: .2rem;
    }

    .currency-switch-card strong,
    .currency-switch-card small {
        display: block;
    }

    .currency-switch-card small {
        margin-top: .2rem;
        color: var(--theme-text-muted, #6b7280);
        line-height: 1.5;
    }

    .currency-preview {
        display: none;
        margin-top: .6rem;
    }

    .currency-preview img {
        width: 58px;
        height: 58px;
        object-fit: contain;
        padding: 5px;
        border: 1px solid var(--theme-border, #e5e7eb);
        border-radius: 10px;
    }

    .delete-icon {
        width: 56px;
        height: 56px;
        margin: 0 auto .85rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: color-mix(in srgb, var(--theme-danger, #dc2626) 12%, transparent);
        color: var(--theme-danger, #dc2626);
        font-size: 1.5rem;
    }

    body.currency-modal-open {
        overflow: hidden;
    }

    @media(max-width: 900px) {
        .currency-grid {
            grid-template-columns: 1fr;
        }

        .currency-filter {
            grid-template-columns: 1fr;
        }
    }

    @media(max-width: 700px) {
        .currency-form-grid {
            grid-template-columns: 1fr;
        }

        .currency-modal {
            padding: 10px;
        }

        .currency-modal-dialog {
            max-height: calc(100vh - 20px);
        }
    }
</style>

<div class="page-header">
    <div>
        <h1 class="page-heading">إدارة العملات</h1>

        <p class="page-subheading">
            هذه هي نفس العملات المستخدمة فعليًا في المشتريات وأسعار الصرف.
        </p>
    </div>

    <div class="page-header-actions" style="display:flex;gap:.6rem;flex-wrap:wrap">
        @can('procurement.exchange_rates.view')
            @if(\Illuminate\Support\Facades\Route::has('procurement.exchange-rates.index'))
                <a
                    href="{{ route('procurement.exchange-rates.index') }}"
                    class="btn btn-outline"
                >
                    أسعار الصرف
                </a>
            @endif
        @endcan

        @can('system_currencies.manage')
            <button
                type="button"
                class="btn btn-gold"
                data-open-modal="createCurrencyModal"
            >
                + إضافة عملة
            </button>
        @endcan
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:1rem">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger" style="margin-bottom:1rem">
        {{ session('error') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:1rem">
        <strong>تعذر حفظ البيانات:</strong>

        <ul style="margin:.45rem 1rem 0 0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="currency-info-box">
    <strong>مهم:</strong>
    لم نغيّر العملة الأساسية الحالية ولا أسعار الصرف القديمة.
    أي عملة جديدة تضيفها هنا تُسجل مباشرة في جدول العملات الحقيقي
    وتظهر في صفحة أسعار الصرف بعد تفعيلها.
</div>

<div class="card" style="margin-top:1rem">
    <div class="card-body">
        <form
            method="GET"
            action="{{ route('settings.currencies.index') }}"
            class="currency-filter"
        >
            <input
                class="form-input"
                name="q"
                value="{{ request('q') }}"
                placeholder="ابحث بالكود أو الاسم أو الرمز..."
            >

            <select class="form-input" name="status">
                <option value="">كل الحالات</option>

                <option
                    value="active"
                    @selected(request('status') === 'active')
                >
                    فعال
                </option>

                <option
                    value="inactive"
                    @selected(request('status') === 'inactive')
                >
                    غير فعال
                </option>
            </select>

            <button class="btn btn-outline" type="submit">
                بحث
            </button>

            @if(request()->hasAny(['q', 'status']))
                <a
                    href="{{ route('settings.currencies.index') }}"
                    class="btn btn-ghost"
                >
                    مسح
                </a>
            @endif
        </form>
    </div>
</div>

<div class="currency-grid">
    @forelse($currencies as $currency)
        <article class="currency-card">
            <div class="currency-card-head">
                <div class="currency-avatar">
                    @if($currency->image_url)
                        <img
                            src="{{ $currency->image_url }}"
                            alt="{{ $currency->displayName() }}"
                        >
                    @elseif($currency->icon)
                        <span>{{ $currency->icon }}</span>
                    @else
                        <span>{{ $currency->symbol }}</span>
                    @endif
                </div>

                <div class="currency-main">
                    <div class="currency-title-row">
                        <h3>{{ $currency->displayName() }}</h3>

                        @if($currency->is_base)
                            <span class="currency-badge base">
                                العملة الأساسية
                            </span>
                        @endif

                        <span class="currency-badge {{ $currency->is_active ? 'active' : 'inactive' }}">
                            {{ $currency->is_active ? 'فعالة' : 'غير فعالة' }}
                        </span>
                    </div>

                    <div class="currency-code">
                        {{ $currency->code }}
                        <span>•</span>
                        {{ $currency->symbol ?: '—' }}
                    </div>

                    @if($currency->name)
                        <div class="currency-en">
                            {{ $currency->name }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="currency-meta">
                <div>
                    <span>الخانات العشرية</span>
                    <strong>{{ $currency->decimal_places }}</strong>
                </div>

                <div>
                    <span>ترتيب العرض</span>
                    <strong>{{ $currency->sort_order }}</strong>
                </div>
            </div>

            @can('system_currencies.manage')
                <div class="currency-actions">
                    <button
                        type="button"
                        class="btn btn-outline btn-sm js-edit-currency"
                        data-id="{{ $currency->id }}"
                    >
                        تعديل
                    </button>

                    @if(!$currency->is_base)
                        <form
                            method="POST"
                            action="{{ route('settings.currencies.toggle', $currency) }}"
                        >
                            @csrf
                            @method('PATCH')

                            <button
                                class="btn btn-ghost btn-sm"
                                type="submit"
                            >
                                {{ $currency->is_active ? 'تعطيل' : 'تفعيل' }}
                            </button>
                        </form>

                        <button
                            type="button"
                            class="btn btn-ghost btn-sm js-delete-currency"
                            data-id="{{ $currency->id }}"
                            style="color:var(--theme-danger, #dc2626)"
                        >
                            حذف
                        </button>
                    @endif
                </div>
            @endcan
        </article>
    @empty
        <div class="card">
            <div class="card-body" style="text-align:center;padding:2rem">
                لا توجد عملات مطابقة.
            </div>
        </div>
    @endforelse
</div>

<div style="margin-top:1rem">
    {{ $currencies->links() }}
</div>

@can('system_currencies.manage')
    {{-- Create Modal --}}
    <div
        class="currency-modal"
        id="createCurrencyModal"
        aria-hidden="true"
    >
        <div
            class="currency-modal-backdrop"
            data-close-modal
        ></div>

        <div
            class="currency-modal-dialog"
            role="dialog"
            aria-modal="true"
        >
            <form
                method="POST"
                action="{{ route('settings.currencies.store') }}"
                enctype="multipart/form-data"
            >
                @csrf

                <input
                    type="hidden"
                    name="_form_context"
                    value="create"
                >

                <div class="currency-modal-header">
                    <h2>إضافة عملة جديدة</h2>

                    <button
                        type="button"
                        class="currency-modal-close"
                        data-close-modal
                    >
                        ×
                    </button>
                </div>

                <div class="currency-modal-body">
                    <div class="currency-form-grid">
                        <div class="form-group">
                            <label class="form-label">كود العملة *</label>

                            <input
                                class="form-input"
                                name="code"
                                value="{{ old('_form_context') === 'create' ? old('code') : '' }}"
                                placeholder="مثال: USD"
                                maxlength="10"
                                dir="ltr"
                                required
                            >

                            <small class="currency-help">
                                مثال: ILS أو USD أو EUR أو SAR.
                            </small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">اسم العملة بالعربية *</label>

                            <input
                                class="form-input"
                                name="name_ar"
                                value="{{ old('_form_context') === 'create' ? old('name_ar') : '' }}"
                                placeholder="مثال: ريال سعودي"
                                maxlength="120"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">اسم العملة بالإنجليزية</label>

                            <input
                                class="form-input"
                                name="name_en"
                                value="{{ old('_form_context') === 'create' ? old('name_en') : '' }}"
                                placeholder="مثال: Saudi Riyal"
                                maxlength="120"
                                dir="ltr"
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">رمز العملة *</label>

                            <input
                                class="form-input"
                                name="symbol"
                                value="{{ old('_form_context') === 'create' ? old('symbol') : '' }}"
                                placeholder="مثال: ر.س"
                                maxlength="20"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">أيقونة العملة</label>

                            <input
                                class="form-input"
                                name="icon"
                                value="{{ old('_form_context') === 'create' ? old('icon') : '' }}"
                                placeholder="مثال: SAR أو 💱"
                                maxlength="50"
                            >

                            <small class="currency-help">
                                اختياري، للعرض فقط.
                            </small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">عدد الخانات العشرية *</label>

                            <input
                                class="form-input"
                                type="number"
                                name="decimal_places"
                                value="{{ old('_form_context') === 'create' ? old('decimal_places', 2) : 2 }}"
                                min="0"
                                max="6"
                                dir="ltr"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">ترتيب العرض</label>

                            <input
                                class="form-input"
                                type="number"
                                name="sort_order"
                                value="{{ old('_form_context') === 'create' ? old('sort_order', 0) : 0 }}"
                                min="0"
                                dir="ltr"
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">صورة / علم العملة</label>

                            <input
                                class="form-input"
                                type="file"
                                name="image"
                                accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
                            >

                            <small class="currency-help">
                                اختياري، بحد أقصى 2MB.
                            </small>
                        </div>
                    </div>

                    <div class="currency-switches">
                        <label class="currency-switch-card">
                            <span>
                                <input
                                    type="hidden"
                                    name="is_active"
                                    value="0"
                                >

                                <input
                                    type="checkbox"
                                    name="is_active"
                                    value="1"
                                    @checked(
                                        old('_form_context') === 'create'
                                            ? old('is_active', true)
                                            : true
                                    )
                                >
                            </span>

                            <span>
                                <strong>العملة فعالة</strong>
                                <small>
                                    إذا كانت فعالة ستظهر مباشرة ضمن العملات المتاحة في أسعار الصرف.
                                </small>
                            </span>
                        </label>
                    </div>

                    <div class="form-group" style="margin-top:1rem">
                        <label class="form-label">ملاحظات</label>

                        <textarea
                            class="form-input"
                            name="notes"
                            rows="3"
                            maxlength="2000"
                            placeholder="أي ملاحظات داخلية..."
                        >{{ old('_form_context') === 'create' ? old('notes') : '' }}</textarea>
                    </div>
                </div>

                <div class="currency-modal-footer">
                    <button
                        type="button"
                        class="btn btn-ghost"
                        data-close-modal
                    >
                        إلغاء
                    </button>

                    <button
                        type="submit"
                        class="btn btn-gold"
                    >
                        حفظ العملة
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Edit Modal --}}
    <div
        class="currency-modal"
        id="editCurrencyModal"
        aria-hidden="true"
    >
        <div
            class="currency-modal-backdrop"
            data-close-modal
        ></div>

        <div
            class="currency-modal-dialog"
            role="dialog"
            aria-modal="true"
        >
            <form
                method="POST"
                action=""
                id="editCurrencyForm"
                enctype="multipart/form-data"
            >
                @csrf
                @method('PUT')

                <input
                    type="hidden"
                    name="_form_context"
                    value="edit"
                >

                <input
                    type="hidden"
                    name="_currency_id"
                    id="editCurrencyId"
                    value="{{ old('_form_context') === 'edit' ? old('_currency_id') : '' }}"
                >

                <div class="currency-modal-header">
                    <h2 id="editCurrencyTitle">
                        تعديل العملة
                    </h2>

                    <button
                        type="button"
                        class="currency-modal-close"
                        data-close-modal
                    >
                        ×
                    </button>
                </div>

                <div class="currency-modal-body">
                    <div
                        id="editBaseNotice"
                        class="currency-info-box"
                        style="display:none;margin-top:0;margin-bottom:1rem"
                    >
                        هذه هي العملة الأساسية الحالية.
                        يمكنك تعديل الاسم والرمز والصورة، لكن لا يمكن تعطيلها من هذه الشاشة.
                    </div>

                    <div class="currency-form-grid">
                        <div class="form-group">
                            <label class="form-label">كود العملة *</label>

                            <input
                                class="form-input"
                                name="code"
                                id="editCode"
                                maxlength="10"
                                dir="ltr"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">اسم العملة بالعربية *</label>

                            <input
                                class="form-input"
                                name="name_ar"
                                id="editNameAr"
                                maxlength="120"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">اسم العملة بالإنجليزية</label>

                            <input
                                class="form-input"
                                name="name_en"
                                id="editNameEn"
                                maxlength="120"
                                dir="ltr"
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">رمز العملة *</label>

                            <input
                                class="form-input"
                                name="symbol"
                                id="editSymbol"
                                maxlength="20"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">أيقونة العملة</label>

                            <input
                                class="form-input"
                                name="icon"
                                id="editIcon"
                                maxlength="50"
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">عدد الخانات العشرية *</label>

                            <input
                                class="form-input"
                                type="number"
                                name="decimal_places"
                                id="editDecimalPlaces"
                                min="0"
                                max="6"
                                dir="ltr"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">ترتيب العرض</label>

                            <input
                                class="form-input"
                                type="number"
                                name="sort_order"
                                id="editSortOrder"
                                min="0"
                                dir="ltr"
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">تغيير الصورة / العلم</label>

                            <input
                                class="form-input"
                                type="file"
                                name="image"
                                accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
                            >

                            <div
                                class="currency-preview"
                                id="editImagePreview"
                            >
                                <img
                                    src=""
                                    alt="صورة العملة"
                                    id="editImage"
                                >
                            </div>
                        </div>
                    </div>

                    <div class="currency-switches">
                        <label class="currency-switch-card">
                            <span>
                                <input
                                    type="hidden"
                                    name="is_active"
                                    value="0"
                                >

                                <input
                                    type="checkbox"
                                    name="is_active"
                                    id="editIsActive"
                                    value="1"
                                >
                            </span>

                            <span>
                                <strong>العملة فعالة</strong>
                                <small>
                                    العملات الفعالة فقط تظهر في نموذج أسعار الصرف.
                                </small>
                            </span>
                        </label>
                    </div>

                    <div class="form-group" style="margin-top:1rem">
                        <label class="form-label">ملاحظات</label>

                        <textarea
                            class="form-input"
                            name="notes"
                            id="editNotes"
                            rows="3"
                            maxlength="2000"
                        ></textarea>
                    </div>
                </div>

                <div class="currency-modal-footer">
                    <button
                        type="button"
                        class="btn btn-ghost"
                        data-close-modal
                    >
                        إلغاء
                    </button>

                    <button
                        type="submit"
                        class="btn btn-gold"
                    >
                        حفظ التعديلات
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Delete Modal --}}
    <div
        class="currency-modal"
        id="deleteCurrencyModal"
        aria-hidden="true"
    >
        <div
            class="currency-modal-backdrop"
            data-close-modal
        ></div>

        <div class="currency-modal-dialog small">
            <form
                method="POST"
                action=""
                id="deleteCurrencyForm"
            >
                @csrf
                @method('DELETE')

                <div class="currency-modal-header">
                    <h2>حذف العملة</h2>

                    <button
                        type="button"
                        class="currency-modal-close"
                        data-close-modal
                    >
                        ×
                    </button>
                </div>

                <div class="currency-modal-body" style="text-align:center">
                    <div class="delete-icon">!</div>

                    <h3 style="margin:.2rem 0 .5rem">
                        هل تريد حذف هذه العملة؟
                    </h3>

                    <p style="margin:0;color:var(--theme-text-muted,#6b7280);line-height:1.7">
                        سيتم حذف
                        <strong id="deleteCurrencyName"></strong>
                        فقط إذا لم تكن مستخدمة في أي سعر صرف أو عملية مشتريات.
                        إذا كانت مستخدمة سيمنع النظام الحذف تلقائيًا.
                    </p>
                </div>

                <div class="currency-modal-footer">
                    <button
                        type="button"
                        class="btn btn-ghost"
                        data-close-modal
                    >
                        إلغاء
                    </button>

                    <button
                        type="submit"
                        class="btn btn-danger"
                    >
                        حذف العملة
                    </button>
                </div>
            </form>
        </div>
    </div>
@endcan

@push('scripts')
<script>
(() => {
    const currencies = {{ \Illuminate\Support\Js::from($currencyPayload) }};

    const oldContext = {{ \Illuminate\Support\Js::from(old('_form_context')) }};
    const oldEditId = {{ \Illuminate\Support\Js::from(old('_currency_id')) }};

    const oldEditValues = {{ \Illuminate\Support\Js::from([
        'code' => old('code'),
        'name_ar' => old('name_ar'),
        'name_en' => old('name_en'),
        'symbol' => old('symbol'),
        'icon' => old('icon'),
        'decimal_places' => old('decimal_places'),
        'is_active' => old('is_active'),
        'sort_order' => old('sort_order'),
        'notes' => old('notes'),
    ]) }};

    function openModal(id) {
        const modal = document.getElementById(id);

        if (!modal) {
            return;
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('currency-modal-open');
    }

    function closeModal(modal) {
        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');

        if (!document.querySelector('.currency-modal.is-open')) {
            document.body.classList.remove('currency-modal-open');
        }
    }

    document.querySelectorAll('[data-open-modal]').forEach(button => {
        button.addEventListener('click', () => {
            openModal(button.dataset.openModal);
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach(button => {
        button.addEventListener('click', () => {
            closeModal(button.closest('.currency-modal'));
        });
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closeModal(document.querySelector('.currency-modal.is-open'));
        }
    });

    const findCurrency = id => currencies.find(
        currency => String(currency.id) === String(id)
    );

    function fillEditModal(currency, overrideValues = null) {
        if (!currency) {
            return;
        }

        const values = overrideValues
            ? {...currency, ...overrideValues}
            : currency;

        document.getElementById('editCurrencyForm').action =
            currency.update_url;

        document.getElementById('editCurrencyId').value =
            currency.id;

        document.getElementById('editCode').value =
            values.code ?? '';

        document.getElementById('editNameAr').value =
            values.name_ar ?? '';

        document.getElementById('editNameEn').value =
            values.name_en ?? '';

        document.getElementById('editSymbol').value =
            values.symbol ?? '';

        document.getElementById('editIcon').value =
            values.icon ?? '';

        document.getElementById('editDecimalPlaces').value =
            values.decimal_places ?? 2;

        document.getElementById('editSortOrder').value =
            values.sort_order ?? 0;

        document.getElementById('editNotes').value =
            values.notes ?? '';

        const isActive =
            Number(values.is_active ?? 0) === 1
            || values.is_active === true
            || values.is_active === '1'
            || values.is_active === 'on';

        const activeInput =
            document.getElementById('editIsActive');

        activeInput.checked =
            currency.is_base ? true : isActive;

        activeInput.disabled =
            Boolean(currency.is_base);

        document.getElementById('editBaseNotice').style.display =
            currency.is_base ? 'block' : 'none';

        const preview =
            document.getElementById('editImagePreview');

        const image =
            document.getElementById('editImage');

        if (currency.image_url) {
            image.src = currency.image_url;
            preview.style.display = 'block';
        } else {
            image.removeAttribute('src');
            preview.style.display = 'none';
        }

        document.getElementById('editCurrencyTitle').textContent =
            `تعديل ${currency.name_ar || currency.name_en} (${currency.code})`;
    }

    document.querySelectorAll('.js-edit-currency').forEach(button => {
        button.addEventListener('click', () => {
            const currency = findCurrency(button.dataset.id);

            fillEditModal(currency);
            openModal('editCurrencyModal');
        });
    });

    document.querySelectorAll('.js-delete-currency').forEach(button => {
        button.addEventListener('click', () => {
            const currency = findCurrency(button.dataset.id);

            if (!currency) {
                return;
            }

            document.getElementById('deleteCurrencyForm').action =
                currency.delete_url;

            document.getElementById('deleteCurrencyName').textContent =
                `${currency.name_ar || currency.name_en} (${currency.code})`;

            openModal('deleteCurrencyModal');
        });
    });

    if (oldContext === 'create') {
        openModal('createCurrencyModal');
    }

    if (oldContext === 'edit' && oldEditId) {
        const currency = findCurrency(oldEditId);

        if (currency) {
            fillEditModal(currency, oldEditValues);
            openModal('editCurrencyModal');
        }
    }
})();
</script>
@endpush

@endsection
