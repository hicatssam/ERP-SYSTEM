@extends('layouts.app')

@section('title', 'مناطق التوصيل')

@section('content')
    @include('growth._styles')

    <style>
        .delivery-zone-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .9rem;
        }

        .delivery-zone-field {
            min-width: 0;
        }

        .delivery-zone-field-full {
            grid-column: 1 / -1;
        }

        .delivery-zone-help {
            display: block;
            margin-top: .35rem;
            color: var(--text-muted);
            font-size: .78rem;
            line-height: 1.7;
        }

        .delivery-zone-money {
            position: relative;
        }

        .delivery-zone-money .form-input {
            padding-inline-end: 2.6rem;
        }

        .delivery-zone-money-unit {
            position: absolute;
            inset-inline-end: .9rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: .8rem;
            pointer-events: none;
        }

        .delivery-zone-item {
            padding: 1rem 0;
            border-bottom: 1px solid var(--border);
        }

        .delivery-zone-item:first-child {
            padding-top: 0;
        }

        .delivery-zone-item:last-child {
            padding-bottom: 0;
            border-bottom: 0;
        }

        .delivery-zone-item-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            margin-bottom: .9rem;
        }

        .delivery-zone-item-head strong {
            color: var(--text);
            font-size: .95rem;
        }

        .delivery-zone-status {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .3rem .65rem;
            border: 1px solid var(--border);
            border-radius: 999px;
            color: var(--text-muted);
            font-size: .75rem;
        }

        .delivery-zone-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: .7rem;
            margin-top: .9rem;
            flex-wrap: wrap;
        }

        @media (max-width: 760px) {
            .delivery-zone-form-grid {
                grid-template-columns: 1fr;
            }

            .delivery-zone-field-full {
                grid-column: auto;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-heading">مناطق ورسوم التوصيل</h1>
            <p class="page-subheading">
                <a href="{{ route('delivery.tasks.index') }}">التوصيل</a>
                ‹ المناطق
            </p>
        </div>
    </div>

    <div class="growth-grid-2">
        {{-- =====================================================
            إضافة منطقة جديدة
        ====================================================== --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">منطقة توصيل جديدة</span>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('delivery.zones.store') }}">
                    @csrf

                    <div class="delivery-zone-form-grid">
                        <div class="delivery-zone-field delivery-zone-field-full">
                            <label class="form-label" for="location_id">
                                الفرع *
                            </label>

                            <select
                                class="form-select"
                                id="location_id"
                                name="location_id"
                                required
                            >
                                @foreach($locations as $location)
                                    <option
                                        value="{{ $location->id }}"
                                        @selected((string) old('location_id') === (string) $location->id)
                                    >
                                        {{ $location->name }}
                                    </option>
                                @endforeach
                            </select>

                            <small class="delivery-zone-help">
                                اختر الفرع الذي ستتبع له هذه المنطقة.
                            </small>
                        </div>

                        <div class="delivery-zone-field delivery-zone-field-full">
                            <label class="form-label" for="zone_name">
                                اسم المنطقة *
                            </label>

                            <input
                                class="form-input"
                                id="zone_name"
                                name="name"
                                value="{{ old('name') }}"
                                placeholder="مثال: الشيخ رضوان"
                                required
                            >

                            <small class="delivery-zone-help">
                                اسم الحي أو المنطقة التي تقومون بالتوصيل إليها.
                            </small>
                        </div>

                        <div class="delivery-zone-field">
                            <label class="form-label" for="zone_code">
                                كود المنطقة
                            </label>

                            <input
                                class="form-input"
                                id="zone_code"
                                name="code"
                                value="{{ old('code') }}"
                                placeholder="مثال: SHK-RDW"
                            >

                            <small class="delivery-zone-help">
                                رمز اختياري داخلي لتسهيل البحث والتقارير.
                            </small>
                        </div>

                        <div class="delivery-zone-field">
                            <label class="form-label" for="zone_fee">
                                رسوم التوصيل *
                            </label>

                            <div class="delivery-zone-money">
                                <input
                                    class="form-input"
                                    id="zone_fee"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="fee"
                                    value="{{ old('fee', 0) }}"
                                    placeholder="0.00"
                                    required
                                >
                                <span class="delivery-zone-money-unit">₪</span>
                            </div>

                            <small class="delivery-zone-help">
                                المبلغ الذي سيتم احتسابه كتوصيل لهذه المنطقة.
                            </small>
                        </div>

                        <div class="delivery-zone-field">
                            <label class="form-label" for="minimum_order_amount">
                                الحد الأدنى للطلب *
                            </label>

                            <div class="delivery-zone-money">
                                <input
                                    class="form-input"
                                    id="minimum_order_amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="minimum_order_amount"
                                    value="{{ old('minimum_order_amount', 0) }}"
                                    placeholder="0.00"
                                    required
                                >
                                <span class="delivery-zone-money-unit">₪</span>
                            </div>

                            <small class="delivery-zone-help">
                                أقل قيمة طلب مسموح بها للتوصيل إلى هذه المنطقة.
                                ضع 0 إذا لا يوجد حد أدنى.
                            </small>
                        </div>

                        <div class="delivery-zone-field">
                            <label class="form-label" for="estimated_minutes">
                                مدة التوصيل المتوقعة
                            </label>

                            <input
                                class="form-input"
                                id="estimated_minutes"
                                type="number"
                                min="1"
                                max="1440"
                                name="estimated_minutes"
                                value="{{ old('estimated_minutes') }}"
                                placeholder="مثال: 30"
                            >

                            <small class="delivery-zone-help">
                                الوقت التقريبي بالدقائق من تجهيز الطلب حتى وصوله.
                            </small>
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="btn btn-gold"
                        style="margin-top: 1rem;"
                    >
                        إضافة المنطقة
                    </button>
                </form>
            </div>
        </div>

        {{-- =====================================================
            المناطق الحالية
        ====================================================== --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">المناطق الحالية</span>
            </div>

            <div class="card-body">
                @forelse($zones as $zone)
                    <form
                        method="POST"
                        action="{{ route('delivery.zones.update', $zone) }}"
                        class="delivery-zone-item"
                    >
                        @csrf
                        @method('PATCH')

                        <input
                            type="hidden"
                            name="location_id"
                            value="{{ $zone->location_id }}"
                        >

                        <div class="delivery-zone-item-head">
                            <strong>
                                {{ $zone->location?->name }}
                            </strong>

                            <span class="delivery-zone-status">
                                {{ $zone->is_active ? 'منطقة فعالة' : 'منطقة موقوفة' }}
                            </span>
                        </div>

                        <div class="delivery-zone-form-grid">
                            <div class="delivery-zone-field delivery-zone-field-full">
                                <label class="form-label">
                                    اسم المنطقة *
                                </label>

                                <input
                                    class="form-input"
                                    name="name"
                                    value="{{ old('name', $zone->name) }}"
                                    placeholder="مثال: الشيخ رضوان"
                                    required
                                >
                            </div>

                            <div class="delivery-zone-field">
                                <label class="form-label">
                                    كود المنطقة
                                </label>

                                <input
                                    class="form-input"
                                    name="code"
                                    value="{{ old('code', $zone->code) }}"
                                    placeholder="مثال: SHK-RDW"
                                >
                            </div>

                            <div class="delivery-zone-field">
                                <label class="form-label">
                                    رسوم التوصيل
                                </label>

                                <div class="delivery-zone-money">
                                    <input
                                        class="form-input"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        name="fee"
                                        value="{{ old('fee', $zone->fee) }}"
                                        required
                                    >
                                    <span class="delivery-zone-money-unit">₪</span>
                                </div>
                            </div>

                            <div class="delivery-zone-field">
                                <label class="form-label">
                                    الحد الأدنى للطلب
                                </label>

                                <div class="delivery-zone-money">
                                    <input
                                        class="form-input"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        name="minimum_order_amount"
                                        value="{{ old('minimum_order_amount', $zone->minimum_order_amount) }}"
                                        required
                                    >
                                    <span class="delivery-zone-money-unit">₪</span>
                                </div>
                            </div>

                            <div class="delivery-zone-field">
                                <label class="form-label">
                                    مدة التوصيل المتوقعة
                                </label>

                                <input
                                    class="form-input"
                                    type="number"
                                    min="1"
                                    max="1440"
                                    name="estimated_minutes"
                                    value="{{ old('estimated_minutes', $zone->estimated_minutes) }}"
                                    placeholder="بالدقائق"
                                >
                            </div>

                            <div class="delivery-zone-field" style="align-self: end;">
                                <label class="form-check">
                                    <input
                                        type="checkbox"
                                        name="is_active"
                                        value="1"
                                        @checked($zone->is_active)
                                    >
                                    المنطقة فعالة ومتاحة للتوصيل
                                </label>
                            </div>
                        </div>

                        <div class="delivery-zone-actions">
                            <button type="submit" class="btn btn-outline btn-sm">
                                حفظ التعديلات
                            </button>
                        </div>
                    </form>
                @empty
                    <div class="growth-note">
                        لا توجد مناطق توصيل مضافة حتى الآن.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection