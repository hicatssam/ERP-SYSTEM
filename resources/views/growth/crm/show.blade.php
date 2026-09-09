@extends('layouts.app')

@section('title', 'ملف العميل 360°')

@section('content')
    @include('growth._styles')

    {{-- =========================================================
        Page Header
    ========================================================== --}}
    <div class="page-header">
        <div>
            <h1 class="page-heading">
                {{ $customer->name }} — 360°
            </h1>

            <p class="page-subheading">
                <a href="{{ route('crm.index') }}">CRM</a>
                <span>‹</span>
                <span>{{ $customer->phone }}</span>
            </p>
        </div>

        <a href="{{ route('customers.show', $customer) }}" class="btn btn-ghost">
            ملف العميل المالي / الأساسي
        </a>
    </div>

    {{-- =========================================================
        Customer Summary
    ========================================================== --}}
    <div class="growth-grid">
        <div class="growth-stat">
            <small>عدد الطلبات</small>
            <strong>{{ number_format($stats['orders']) }}</strong>
        </div>

        <div class="growth-stat">
            <small>إجمالي المبيعات</small>
            <strong>₪{{ number_format($stats['sales'], 2) }}</strong>
        </div>

        <div class="growth-stat">
            <small>المستحق</small>
            <strong>₪{{ number_format($stats['outstanding'], 2) }}</strong>
        </div>

        @if($loyaltyEnabled)
            <div class="growth-stat">
                <small>نقاط الولاء</small>
                <strong>{{ number_format($loyalty?->points_balance ?? 0) }}</strong>
            </div>
        @endif
    </div>

    {{-- =========================================================
        CRM Profile + Tags
    ========================================================== --}}
    <div class="growth-grid-2 growth-section">
        {{-- CRM Profile --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">ملف CRM</span>
            </div>

            <div class="card-body">
                @can('crm.manage')
                    <form
                        method="POST"
                        action="{{ route('crm.customers.profile.update', $customer) }}"
                        class="growth-form-grid"
                    >
                        @csrf
                        @method('PATCH')

                        <div>
                            <label class="form-label">البريد الإلكتروني</label>
                            <input
                                type="email"
                                class="form-input"
                                name="email"
                                value="{{ old('email', $customer->email) }}"
                                placeholder="example@email.com"
                            >
                        </div>

                        <div>
                            <label class="form-label">تاريخ الميلاد</label>
                            <input
                                type="date"
                                class="form-input"
                                name="birthday"
                                value="{{ old(
                                    'birthday',
                                    $customer->birthday
                                        ? \Carbon\Carbon::parse($customer->birthday)->format('Y-m-d')
                                        : ''
                                ) }}"
                            >
                        </div>

                        <div>
                            <label class="form-label">التواصل المفضل</label>
                            <select class="form-select" name="preferred_contact_channel">
                                <option value="">— اختر —</option>

                                @foreach([
                                    'phone' => 'هاتف',
                                    'whatsapp' => 'واتساب',
                                    'email' => 'بريد',
                                    'sms' => 'SMS',
                                ] as $value => $label)
                                    <option
                                        value="{{ $value }}"
                                        @selected(
                                            old(
                                                'preferred_contact_channel',
                                                $customer->preferred_contact_channel
                                            ) === $value
                                        )
                                    >
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label">حالة العميل</label>
                            <select class="form-select" name="crm_status">
                                @foreach([
                                    'lead' => 'محتمل',
                                    'active' => 'نشط',
                                    'vip' => 'VIP',
                                    'inactive' => 'غير نشط',
                                ] as $value => $label)
                                    <option
                                        value="{{ $value }}"
                                        @selected(old('crm_status', $customer->crm_status) === $value)
                                    >
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label">مسؤول CRM</label>
                            <select class="form-select" name="crm_owner_id">
                                <option value="">بدون مسؤول محدد</option>

                                @foreach($crmOwners as $owner)
                                    <option
                                        value="{{ $owner->id }}"
                                        @selected(
                                            (string) old('crm_owner_id', $customer->crm_owner_id)
                                            === (string) $owner->id
                                        )
                                    >
                                        {{ $owner->employee?->full_name
                                            ?? $owner->display_name
                                            ?? $owner->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label">الموافقة التسويقية</label>

                            <label class="form-check">
                                <input
                                    type="checkbox"
                                    name="marketing_opt_in"
                                    value="1"
                                    @checked(old('marketing_opt_in', $customer->marketing_opt_in))
                                >
                                وافق العميل صراحة
                            </label>

                            @if($customer->marketing_opt_in_at)
                                <small class="text-muted">
                                    تاريخ الموافقة:
                                    {{ \Carbon\Carbon::parse($customer->marketing_opt_in_at)->format('Y-m-d H:i') }}
                                </small>
                            @endif
                        </div>

                        <div style="align-self: end;">
                            <button type="submit" class="btn btn-gold">
                                حفظ بيانات CRM
                            </button>
                        </div>
                    </form>
                @else
                    <div class="growth-timeline">
                        <div class="growth-timeline-item">
                            <strong>البريد الإلكتروني</strong>
                            <div>{{ $customer->email ?: 'لا يوجد بريد' }}</div>
                        </div>

                        <div class="growth-timeline-item">
                            <strong>مسؤول CRM</strong>
                            <div>
                                {{ $crmOwner?->employee?->full_name
                                    ?? $crmOwner?->display_name
                                    ?? 'غير محدد' }}
                            </div>
                        </div>
                    </div>
                @endcan
            </div>
        </div>

        {{-- Tags --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">الوسوم</span>
            </div>

            <div class="card-body">
                <div class="growth-actions" style="margin-bottom: .9rem;">
                    @forelse($selectedTags as $tag)
                        <span
                            class="growth-chip"
                            style="border-color: {{ $tag->color ?: 'var(--border)' }};"
                        >
                            {{ $tag->name }}
                        </span>
                    @empty
                        <span class="text-muted">لا توجد وسوم لهذا العميل.</span>
                    @endforelse
                </div>

                @can('crm.manage')
                    <form
                        method="POST"
                        action="{{ route('crm.customers.tags.sync', $customer) }}"
                    >
                        @csrf

                        <div class="growth-actions">
                            @foreach($allTags as $tag)
                                <label class="growth-chip">
                                    <input
                                        type="checkbox"
                                        name="tag_ids[]"
                                        value="{{ $tag->id }}"
                                        @checked($selectedTags->contains('id', $tag->id))
                                    >
                                    {{ $tag->name }}
                                </label>
                            @endforeach
                        </div>

                        <button
                            type="submit"
                            class="btn btn-outline btn-sm"
                            style="margin-top: .85rem;"
                        >
                            تحديث الوسوم
                        </button>
                    </form>
                @endcan
            </div>
        </div>
    </div>

    {{-- =========================================================
        Addresses
    ========================================================== --}}
    <div class="card growth-section">
        <div class="card-header">
            <span class="card-title">عناوين العميل</span>
        </div>

        <div class="card-body">
            <div class="growth-grid-2">
                @forelse($addresses as $address)
                    <div class="growth-stat">
                        <div
                            style="
                                display: flex;
                                align-items: center;
                                justify-content: space-between;
                                gap: .75rem;
                                margin-bottom: .5rem;
                            "
                        >
                            <strong>{{ $address->label }}</strong>

                            @if($address->is_default)
                                <span class="growth-chip">افتراضي</span>
                            @endif
                        </div>

                        <p style="margin: 0 0 .45rem;">
                            {{ $address->displayAddress() }}
                        </p>

                        @if($address->recipient_name || $address->phone)
                            <small>
                                {{ $address->recipient_name }}
                                @if($address->recipient_name && $address->phone)
                                    ·
                                @endif
                                {{ $address->phone }}
                            </small>
                        @endif

                        @can('crm.manage')
                            <div class="growth-actions" style="margin-top: .8rem;">
                                @if(!$address->is_default && $address->is_active)
                                    <form
                                        method="POST"
                                        action="{{ route('crm.addresses.default', [$customer, $address]) }}"
                                    >
                                        @csrf

                                        <button type="submit" class="btn btn-outline btn-sm">
                                            جعله افتراضيًا
                                        </button>
                                    </form>
                                @endif

                                @if($address->is_active)
                                    <form
                                        method="POST"
                                        action="{{ route('crm.addresses.destroy', [$customer, $address]) }}"
                                        onsubmit="return confirm('هل تريد تعطيل هذا العنوان؟');"
                                    >
                                        @csrf
                                        @method('DELETE')

                                        <button type="submit" class="btn btn-ghost btn-sm">
                                            تعطيل
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endcan
                    </div>
                @empty
                    <div class="growth-note">
                        لا توجد عناوين مسجلة للعميل حتى الآن.
                    </div>
                @endforelse
            </div>

            @can('crm.manage')
                <hr style="margin: 1.25rem 0;">

                <h3 style="margin: 0 0 1rem; font-size: 1rem;">
                    إضافة عنوان جديد
                </h3>

                <form
                    method="POST"
                    action="{{ route('crm.addresses.store', $customer) }}"
                    class="growth-form-grid"
                >
                    @csrf

                    <div>
                        <label class="form-label">اسم العنوان *</label>
                        <input
                            class="form-input"
                            name="label"
                            value="{{ old('label') }}"
                            placeholder="المنزل / العمل"
                            required
                        >
                    </div>

                    <div>
                        <label class="form-label">اسم المستلم</label>
                        <input
                            class="form-input"
                            name="recipient_name"
                            value="{{ old('recipient_name') }}"
                            placeholder="اسم المستلم"
                        >
                    </div>

                    <div>
                        <label class="form-label">الهاتف</label>
                        <input
                            class="form-input"
                            name="phone"
                            value="{{ old('phone') }}"
                            placeholder="رقم الهاتف"
                        >
                    </div>

                    <div>
                        <label class="form-label">العنوان الأساسي *</label>
                        <input
                            class="form-input"
                            name="address_line1"
                            value="{{ old('address_line1') }}"
                            placeholder="الشارع / المبنى"
                            required
                        >
                    </div>

                    <div>
                        <label class="form-label">المنطقة</label>
                        <input
                            class="form-input"
                            name="area"
                            value="{{ old('area') }}"
                            placeholder="المنطقة"
                        >
                    </div>

                    <div>
                        <label class="form-label">المدينة</label>
                        <input
                            class="form-input"
                            name="city"
                            value="{{ old('city') }}"
                            placeholder="المدينة"
                        >
                    </div>

                    <div>
                        <label class="form-label">علامة مميزة</label>
                        <input
                            class="form-input"
                            name="landmark"
                            value="{{ old('landmark') }}"
                            placeholder="بجوار..."
                        >
                    </div>

                    <div style="align-self: end;">
                        <label class="form-check">
                            <input
                                type="checkbox"
                                name="is_default"
                                value="1"
                                @checked(old('is_default'))
                            >
                            تعيين كعنوان افتراضي
                        </label>
                    </div>

                    <div style="align-self: end;">
                        <button type="submit" class="btn btn-gold">
                            إضافة العنوان
                        </button>
                    </div>
                </form>
            @endcan
        </div>
    </div>

    {{-- =========================================================
        Interactions + Loyalty
    ========================================================== --}}
    <div class="growth-grid-2 growth-section">
        {{-- Interactions --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">التفاعلات والمتابعات</span>
            </div>

            <div class="card-body">
                @can('crm.interactions.manage')
                    <form
                        method="POST"
                        action="{{ route('crm.interactions.store', $customer) }}"
                    >
                        @csrf

                        <div class="growth-form-grid">
                            <div>
                                <label class="form-label">نوع التفاعل *</label>
                                <select class="form-select" name="type" required>
                                    @foreach([
                                        'note' => 'ملاحظة',
                                        'call' => 'اتصال',
                                        'whatsapp' => 'واتساب',
                                        'email' => 'بريد',
                                        'visit' => 'زيارة',
                                        'follow_up' => 'متابعة',
                                        'complaint' => 'شكوى',
                                    ] as $value => $label)
                                        <option
                                            value="{{ $value }}"
                                            @selected(old('type') === $value)
                                        >
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="form-label">العنوان</label>
                                <input
                                    class="form-input"
                                    name="subject"
                                    value="{{ old('subject') }}"
                                    placeholder="عنوان التفاعل"
                                >
                            </div>

                            <div>
                                <label class="form-label">موعد المتابعة القادمة</label>
                                <input
                                    class="form-input"
                                    type="datetime-local"
                                    name="next_follow_up_at"
                                    value="{{ old('next_follow_up_at') }}"
                                >
                            </div>

                            <div style="grid-column: 1 / -1;">
                                <label class="form-label">التفاصيل *</label>
                                <textarea
                                    class="form-input"
                                    name="notes"
                                    rows="4"
                                    placeholder="تفاصيل التفاعل..."
                                    required
                                >{{ old('notes') }}</textarea>
                            </div>

                            <div style="grid-column: 1 / -1;">
                                <button type="submit" class="btn btn-gold">
                                    تسجيل التفاعل
                                </button>
                            </div>
                        </div>
                    </form>

                    <hr style="margin: 1.25rem 0;">
                @endcan

                <div class="growth-timeline">
                    @forelse($interactions as $interaction)
                        <div class="growth-timeline-item">
                            <strong>
                                {{ $interaction->subject ?: $interaction->type }}
                            </strong>

                            <div style="margin-top: .35rem;">
                                {{ $interaction->notes }}
                            </div>

                            <small>
                                {{ $interaction->created_at?->format('Y-m-d H:i') }}
                                ·
                                {{ $interaction->user?->employee?->full_name
                                    ?? $interaction->user?->display_name
                                    ?? 'النظام' }}
                            </small>

                            @if(
                                $interaction->next_follow_up_at
                                && !$interaction->completed_at
                            )
                                <div
                                    style="
                                        margin-top: .6rem;
                                        display: flex;
                                        align-items: center;
                                        gap: .6rem;
                                        flex-wrap: wrap;
                                    "
                                >
                                    <span>
                                        متابعة:
                                        {{ $interaction->next_follow_up_at->format('Y-m-d H:i') }}
                                    </span>

                                    @can('crm.interactions.manage')
                                        <form
                                            method="POST"
                                            action="{{ route(
                                                'crm.interactions.complete',
                                                [$customer, $interaction]
                                            ) }}"
                                        >
                                            @csrf

                                            <button type="submit" class="btn btn-outline btn-sm">
                                                إغلاق المتابعة
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="growth-note">
                            لا توجد تفاعلات أو متابعات مسجلة.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Loyalty --}}
        @if($loyaltyEnabled)
            <div class="card">
                <div class="card-header">
                    <span class="card-title">برنامج الولاء</span>
                </div>

                <div class="card-body">
                    @if($loyalty)
                        <div class="growth-stat">
                            <small>الرصيد الحالي</small>
                            <strong>
                                {{ number_format($loyalty->points_balance) }}
                                نقطة
                            </strong>
                        </div>

                        <div class="growth-timeline" style="margin-top: .9rem;">
                            @forelse($loyalty->transactions as $transaction)
                                <div class="growth-timeline-item">
                                    <div>
                                        <strong
                                            class="{{ $transaction->points >= 0
                                                ? 'loyalty-positive'
                                                : 'loyalty-negative' }}"
                                        >
                                            {{ $transaction->points > 0 ? '+' : '' }}
                                            {{ number_format($transaction->points) }}
                                        </strong>

                                        <span>
                                            · {{ $transaction->type->label() }}
                                        </span>
                                    </div>

                                    <small>
                                        {{ $transaction->note ?: 'بدون ملاحظة' }}
                                        ·
                                        {{ $transaction->created_at?->format('Y-m-d H:i') }}
                                    </small>
                                </div>
                            @empty
                                <div class="growth-note">
                                    لا توجد حركات ولاء حتى الآن.
                                </div>
                            @endforelse
                        </div>
                    @else
                        <div class="growth-note">
                            لا يوجد حساب ولاء للعميل حتى الآن.
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection