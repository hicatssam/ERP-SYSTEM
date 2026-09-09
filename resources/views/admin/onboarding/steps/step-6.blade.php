@php
    $business = $run->business_data ?? [];
    $branding = $run->branding_data ?? [];
    $ops = $run->operational_data ?? [];
    $plan = $run->module_plan ?? [];
    $selectedNames = $allModules
        ->whereIn('code', $plan['selected_codes'] ?? [])
        ->pluck('name')
        ->all();
@endphp

<div class="onb-card">
    <div class="onb-card-head">
        <strong>6. مراجعة وتطبيق</strong>
    </div>

    <div class="onb-card-body">
        <div class="onb-review">
            <div class="onb-review-box">
                <h3>نوع النشاط</h3>
                <div class="onb-review-row">
                    <span>الملف</span>
                    <strong>{{ $profile?->name ?? $run->business_profile_code }}</strong>
                </div>
            </div>

            <div class="onb-review-box">
                <h3>بيانات المنشأة</h3>
                <div class="onb-review-row"><span>الاسم</span><strong>{{ $business['business_legal_name'] ?? '—' }}</strong></div>
                <div class="onb-review-row"><span>الهاتف</span><strong>{{ $business['business_phone'] ?? '—' }}</strong></div>
                <div class="onb-review-row"><span>المدينة</span><strong>{{ $business['business_city'] ?? '—' }}</strong></div>
            </div>

            <div class="onb-review-box">
                <h3>الهوية</h3>
                <div class="onb-review-row"><span>اسم النظام</span><strong>{{ $branding['system_name'] ?? '—' }}</strong></div>
                <div class="onb-review-row"><span>اللون الرئيسي</span><strong>{{ $branding['theme_primary'] ?? '—' }}</strong></div>
                <div class="onb-review-row"><span>الشعار</span><strong>{{ !empty($branding['brand_logo']) ? 'محدد' : 'غير محدد' }}</strong></div>
            </div>

            <div class="onb-review-box">
                <h3>التشغيل</h3>
                <div class="onb-review-row"><span>Timezone</span><strong>{{ $ops['timezone'] ?? '—' }}</strong></div>
                <div class="onb-review-row"><span>Prefix الطلب</span><strong>{{ $ops['order_number_prefix'] ?? '—' }}</strong></div>
                <div class="onb-review-row"><span>Prefix الفاتورة</span><strong>{{ $ops['invoice_prefix'] ?? '—' }}</strong></div>
            </div>

            <div class="onb-review-box onb-full">
                <h3>الوحدات المختارة</h3>
                <div style="display:flex;gap:.45rem;flex-wrap:wrap">
                    @forelse($selectedNames as $name)
                        <span class="onb-chip">{{ $name }}</span>
                    @empty
                        <span class="text-muted">لا توجد وحدات اختيارية محددة.</span>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="onb-warning" style="margin-top:1rem">
            لن يتم تغيير العملة الأساسية. عند الضغط على "تطبيق إعداد العميل" سيتم حفظ الإعدادات النهائية وتفعيل الوحدات المختارة.
        </div>

        <div class="onb-actions">
            <a class="btn btn-ghost" href="{{ route('onboarding.index', ['step' => 5]) }}">السابق</a>

            <form
                method="POST"
                action="{{ route('onboarding.apply', $run) }}"
                onsubmit="return confirm('هل تريد تطبيق إعداد العميل الآن؟');"
            >
                @csrf
                <button type="submit" class="btn btn-gold">تطبيق إعداد العميل</button>
            </form>
        </div>
    </div>
</div>
