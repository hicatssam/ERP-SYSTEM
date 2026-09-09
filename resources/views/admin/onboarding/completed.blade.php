@extends('layouts.app')

@section('title', 'إعداد العميل')

@section('content')
    @include('admin.onboarding.partials.styles')

    <style>
        .onb-summary-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.25rem;
            margin-bottom: 1rem;
            border: 1px solid var(--border);
            border-radius: 18px;
            background:
                linear-gradient(
                    135deg,
                    color-mix(in srgb, var(--theme-primary) 9%, var(--surface)),
                    var(--surface)
                );
        }

        .onb-summary-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: 0;
        }

        .onb-summary-logo {
            width: 92px;
            height: 72px;
            flex: 0 0 92px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: #fff;
        }

        .onb-summary-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: .4rem;
        }

        .onb-summary-title {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text);
        }

        .onb-summary-subtitle {
            margin: .25rem 0 0;
            color: var(--text-muted);
        }

        .onb-summary-actions {
            display: flex;
            align-items: center;
            gap: .65rem;
            flex-wrap: wrap;
        }

        .onb-status-chip {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .35rem .65rem;
            border-radius: 999px;
            background:
                color-mix(
                    in srgb,
                    var(--theme-success) 12%,
                    transparent
                );
            color: var(--theme-success);
            font-size: .75rem;
            font-weight: 700;
        }

        .onb-color-preview {
            width: 22px;
            height: 22px;
            border-radius: 7px;
            border: 1px solid var(--border);
            display: inline-block;
            vertical-align: middle;
            margin-inline-start: .4rem;
        }

        .onb-module-summary {
            display: flex;
            gap: .45rem;
            flex-wrap: wrap;
        }

        @media (max-width: 760px) {
            .onb-summary-hero {
                align-items: stretch;
                flex-direction: column;
            }

            .onb-summary-actions {
                width: 100%;
            }

            .onb-summary-actions .btn {
                flex: 1;
            }
        }
    </style>

    @php
        $brandLogo = $brandingData['brand_logo'] ?? null;
        $systemName =
            $brandingData['system_name']
            ?? $businessData['business_legal_name']
            ?? 'النظام';

        $primaryColor = $brandingData['theme_primary'] ?? null;
    @endphp

    <div class="onb-shell">
        {{-- =====================================================
            Header / Current Setup
        ====================================================== --}}
        <div class="onb-summary-hero">
            <div class="onb-summary-brand">
                <div class="onb-summary-logo">
                    @if($brandLogo)
                        <img
                            src="{{ asset($brandLogo) }}"
                            alt="{{ $systemName }}"
                        >
                    @else
                        <strong>{{ mb_substr($systemName, 0, 2) }}</strong>
                    @endif
                </div>

                <div>
                    <div class="onb-status-chip">
                        الإعداد مطبق
                    </div>

                    <h1 class="onb-summary-title">
                        {{ $systemName }}
                    </h1>

                    <p class="onb-summary-subtitle">
                        {{ $currentProfile?->name ?? 'نوع النشاط غير محدد' }}
                    </p>
                </div>
            </div>

            <div class="onb-summary-actions">
                <form
                    method="POST"
                    action="{{ route('onboarding.start') }}"
                    onsubmit="return confirm(
                        'فتح وضع تعديل إعداد العميل؟ لن يتغير النظام الحالي حتى تضغط تطبيق في الخطوة الأخيرة.'
                    );"
                >
                    @csrf

                    <button
                        type="submit"
                        class="btn btn-gold"
                    >
                        تعديل إعداد العميل
                    </button>
                </form>
            </div>
        </div>

        {{-- =====================================================
            Current Information
        ====================================================== --}}
        <div class="onb-review">
            <div class="onb-review-box">
                <h3>نوع النشاط</h3>

                <div class="onb-review-row">
                    <span>النشاط الحالي</span>
                    <strong>
                        {{ $currentProfile?->name ?? '—' }}
                    </strong>
                </div>

                <div class="onb-review-row">
                    <span>الكود</span>
                    <strong>
                        {{ $currentProfile?->code ?? '—' }}
                    </strong>
                </div>
            </div>

            <div class="onb-review-box">
                <h3>بيانات المنشأة</h3>

                <div class="onb-review-row">
                    <span>الاسم القانوني</span>
                    <strong>
                        {{ $businessData['business_legal_name'] ?: '—' }}
                    </strong>
                </div>

                <div class="onb-review-row">
                    <span>الهاتف</span>
                    <strong>
                        {{ $businessData['business_phone'] ?: '—' }}
                    </strong>
                </div>

                <div class="onb-review-row">
                    <span>البريد</span>
                    <strong>
                        {{ $businessData['business_email'] ?: '—' }}
                    </strong>
                </div>

                <div class="onb-review-row">
                    <span>المدينة</span>
                    <strong>
                        {{ $businessData['business_city'] ?: '—' }}
                    </strong>
                </div>

                <div class="onb-review-row">
                    <span>العنوان</span>
                    <strong>
                        {{ $businessData['business_address'] ?: '—' }}
                    </strong>
                </div>
            </div>

            <div class="onb-review-box">
                <h3>الهوية</h3>

                <div class="onb-review-row">
                    <span>اسم النظام</span>
                    <strong>
                        {{ $brandingData['system_name'] ?: '—' }}
                    </strong>
                </div>

                <div class="onb-review-row">
                    <span>الاسم الإنجليزي</span>
                    <strong>
                        {{ $brandingData['system_name_en'] ?: '—' }}
                    </strong>
                </div>

                <div class="onb-review-row">
                    <span>اللون الرئيسي</span>

                    <strong>
                        {{ $primaryColor ?: '—' }}

                        @if($primaryColor)
                            <span
                                class="onb-color-preview"
                                style="background: {{ $primaryColor }};"
                            ></span>
                        @endif
                    </strong>
                </div>

                <div class="onb-review-row">
                    <span>الشعار</span>
                    <strong>
                        {{ $brandLogo ? 'محدد' : 'غير محدد' }}
                    </strong>
                </div>
            </div>

            <div class="onb-review-box">
                <h3>الإعدادات التشغيلية</h3>

                <div class="onb-review-row">
                    <span>المنطقة الزمنية</span>
                    <strong>
                        {{ $operationalData['timezone'] ?? '—' }}
                    </strong>
                </div>

                <div class="onb-review-row">
                    <span>بادئة الطلب</span>
                    <strong>
                        {{ $operationalData['order_number_prefix'] ?? '—' }}
                    </strong>
                </div>

                <div class="onb-review-row">
                    <span>بادئة الفاتورة</span>
                    <strong>
                        {{ $operationalData['invoice_prefix'] ?? '—' }}
                    </strong>
                </div>

                <div class="onb-review-row">
                    <span>العملة الأساسية</span>
                    <strong>
                        @if($baseCurrency)
                            {{ $baseCurrency->code }}
                            {{ $baseCurrency->symbol }}
                        @else
                            —
                        @endif
                    </strong>
                </div>
            </div>

            <div class="onb-review-box onb-full">
                <h3>الوحدات المفعلة حاليًا</h3>

                <div class="onb-module-summary">
                    @forelse($enabledModules as $module)
                        <span class="onb-chip">
                            {{ $module->name }}
                        </span>
                    @empty
                        <span class="text-muted">
                            لا توجد وحدات مفعلة.
                        </span>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- =====================================================
            Audit Information
        ====================================================== --}}
        <div
            class="onb-warning"
            style="margin-top: 1rem;"
        >
            هذه هي الإعدادات المطبقة فعليًا على النظام الآن.
            عند الضغط على <strong>تعديل إعداد العميل</strong>
            ستفتح نسخة تعديل جديدة مبنية على القيم الحالية،
            ولن يتغير أي شيء حتى الضغط على
            <strong>تطبيق إعداد العميل</strong>
            في الخطوة الأخيرة.

            @if($completedAt)
                <br>
                آخر تطبيق:
                <strong>{{ $completedAt }}</strong>
            @endif

            @if($onboardingVersion)
                · إصدار المعالج:
                <strong>{{ $onboardingVersion }}</strong>
            @endif
        </div>
    </div>
@endsection