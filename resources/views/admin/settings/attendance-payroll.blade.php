@extends('layouts.app')

@section('title', 'إعدادات الحضور والرواتب')

@section('content')
<style>
.att-settings{max-width:1080px;margin:0 auto}
.att-settings-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1rem}
.att-settings-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.att-setting-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden}
.att-setting-head{display:flex;align-items:center;gap:.75rem;padding:1rem 1.1rem;border-bottom:1px solid var(--border)}
.att-setting-icon{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;color:var(--theme-primary);background:color-mix(in srgb,var(--theme-primary) 10%,transparent)}
.att-setting-head h3{margin:0;font-size:.95rem;font-weight:850}.att-setting-head p{margin:.2rem 0 0;color:var(--text-muted);font-size:.73rem}
.att-setting-body{padding:1rem 1.1rem}
.att-toggle-row{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:.9rem 0;border-bottom:1px dashed var(--border)}
.att-toggle-row:last-child{border-bottom:0}
.att-toggle-copy strong{display:block;font-size:.84rem}.att-toggle-copy small{display:block;color:var(--text-muted);font-size:.7rem;margin-top:.2rem;line-height:1.6}
.att-switch{position:relative;display:inline-flex;flex:0 0 auto;width:46px;height:26px}
.att-switch input{opacity:0;width:0;height:0}
.att-slider{position:absolute;inset:0;cursor:pointer;border-radius:999px;background:color-mix(in srgb,var(--border) 85%,transparent);transition:.2s}
.att-slider:before{content:"";position:absolute;width:20px;height:20px;right:3px;top:3px;border-radius:50%;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.18);transition:.2s}
.att-switch input:checked + .att-slider{background:var(--theme-primary)}
.att-switch input:checked + .att-slider:before{transform:translateX(-20px)}
.att-switch input:disabled + .att-slider{opacity:.45;cursor:not-allowed}
.att-number-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.8rem}
.att-warning{padding:.85rem 1rem;border:1px solid color-mix(in srgb,var(--theme-warning) 30%,var(--border));background:color-mix(in srgb,var(--theme-warning) 7%,var(--surface));border-radius:12px;font-size:.76rem;line-height:1.7}
.att-savebar{position:sticky;bottom:0;margin-top:1rem;padding:.85rem 1rem;border:1px solid var(--border);border-radius:14px;background:color-mix(in srgb,var(--surface) 94%,transparent);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:space-between;gap:1rem;z-index:10}
.att-savebar small{color:var(--text-muted)}
@media(max-width:800px){.att-settings-grid{grid-template-columns:1fr}.att-number-grid{grid-template-columns:1fr}.att-settings-head,.att-savebar{align-items:stretch;flex-direction:column}.att-savebar .btn{width:100%}}
</style>

<div class="att-settings">
    <div class="att-settings-head">
        <div>
            <div style="display:flex;gap:.45rem;align-items:center;color:var(--text-muted);font-size:.75rem;margin-bottom:.35rem">
                <a href="{{ route('settings.index') }}" style="color:var(--theme-primary);text-decoration:none">إعدادات النظام</a>
                <span>‹</span>
                <span>الحضور والرواتب</span>
            </div>

            <h1 class="page-heading">إعدادات الحضور والرواتب</h1>
            <p class="page-subheading">
                التحكم بتفعيل الحضور، البصمة والوجه وسياسات تأثير الدوام على الرواتب.
            </p>
        </div>
    </div>

    <form method="POST" action="{{ route('settings.attendance-payroll.update') }}">
        @csrf
        @method('PUT')

        <div class="att-settings-grid">
            <div class="att-setting-card">
                <div class="att-setting-head">
                    <div class="att-setting-icon">
                        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="9"/>
                            <path d="M12 7v5l3 2"/>
                        </svg>
                    </div>
                    <div>
                        <h3>نظام الحضور والدوام</h3>
                        <p>التفعيل العام للحضور والورديات والإجازات.</p>
                    </div>
                </div>

                <div class="att-setting-body">
                    <div class="att-toggle-row">
                        <div class="att-toggle-copy">
                            <strong>تفعيل نظام الحضور والدوام</strong>
                            <small>
                                عند الإيقاف تختفي واجهات الحضور ويتم منع الوصول إليها من الـBackend بدون حذف أي بيانات.
                            </small>
                        </div>

                        <label class="att-switch">
                            <input
                                type="checkbox"
                                name="attendance_enabled"
                                id="attendanceEnabled"
                                value="1"
                                @checked(old('attendance_enabled', $settings['attendance_enabled']))
                            >
                            <span class="att-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="att-setting-card">
                <div class="att-setting-head">
                    <div class="att-setting-icon">
                        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="4" y="3" width="16" height="18" rx="3"/>
                            <path d="M9 8c0-1.7 1.3-3 3-3s3 1.3 3 3"/>
                            <path d="M8 12c0-2.2 1.8-4 4-4s4 1.8 4 4"/>
                            <path d="M10 16c0-1.1.9-2 2-2s2 .9 2 2"/>
                        </svg>
                    </div>
                    <div>
                        <h3>الحضور البيومتري</h3>
                        <p>تشغيل أجهزة البصمة وبصمة الوجه بالكاميرا.</p>
                    </div>
                </div>

                <div class="att-setting-body">
                    <div class="att-toggle-row">
                        <div class="att-toggle-copy">
                            <strong>تفعيل الحضور البيومتري</strong>
                            <small>
                                يعطل أجهزة البصمة وكشك الوجه وجميع نقاط التكامل البيومترية عند إيقافه.
                            </small>
                        </div>

                        <label class="att-switch">
                            <input
                                type="checkbox"
                                name="attendance_biometric_enabled"
                                id="biometricEnabled"
                                value="1"
                                @checked(old('attendance_biometric_enabled', $settings['attendance_biometric_enabled']))
                            >
                            <span class="att-slider"></span>
                        </label>
                    </div>

                    <div class="att-toggle-row">
                        <div class="att-toggle-copy">
                            <strong>اعتماد سجلات الجهاز قبل الرواتب</strong>
                            <small>
                                موصى به. بصمات الجهاز تنشئ سجل حضور لكن لا تدخل في Payroll حتى يعتمدها مستخدم مخوّل.
                            </small>
                        </div>

                        <label class="att-switch">
                            <input
                                type="checkbox"
                                name="attendance_device_require_approval"
                                id="deviceRequireApproval"
                                value="1"
                                @checked(old('attendance_device_require_approval', $settings['attendance_device_require_approval']))
                            >
                            <span class="att-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="att-setting-card">
                <div class="att-setting-head">
                    <div class="att-setting-icon">
                        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M8 3H6a3 3 0 0 0-3 3v2M16 3h2a3 3 0 0 1 3 3v2M8 21H6a3 3 0 0 1-3-3v-2M16 21h2a3 3 0 0 0 3-3v-2"/>
                            <circle cx="9" cy="10" r=".7" fill="currentColor" stroke="none"/>
                            <circle cx="15" cy="10" r=".7" fill="currentColor" stroke="none"/>
                            <path d="M9 15c1.5 1.2 4.5 1.2 6 0"/>
                        </svg>
                    </div>
                    <div>
                        <h3>بصمة الوجه — CompreFace</h3>
                        <p>تعرف على الوجه محليًا ومجانيًا بدون بوابة خارجية مدفوعة.</p>
                    </div>
                </div>

                <div class="att-setting-body">
                    <div class="att-toggle-row">
                        <div class="att-toggle-copy">
                            <strong>حالة الإعداد</strong>
                            <small>
                                @if($faceAttendance['configured'])
                                    <span style="color:var(--theme-success);font-weight:850">● مهيأ</span>
                                    — Laravel مربوط بعنوان CompreFace ومفتاح الخدمة موجود.
                                @else
                                    <span style="color:var(--theme-warning);font-weight:850">● غير مهيأ</span>
                                    — أضف COMPREFACE_BASE_URL وCOMPREFACE_API_KEY داخل ملف .env.
                                @endif
                            </small>
                        </div>
                    </div>

                    <div class="att-toggle-row">
                        <div class="att-toggle-copy" style="width:100%">
                            <strong>عنوان CompreFace المحلي</strong>
                            <small>
                                الاتصال يتم من Laravel إلى CompreFace فقط؛ API Key لا يظهر في المتصفح.
                            </small>

                            <code style="display:block;margin-top:.45rem;padding:.55rem .65rem;border-radius:9px;background:var(--off-white);border:1px solid var(--border);overflow-wrap:anywhere;direction:ltr;text-align:left">
                                {{ $faceAttendance['base_url'] ?: 'غير محدد' }}
                            </code>
                        </div>
                    </div>

                    <div class="att-toggle-row">
                        <div class="att-toggle-copy">
                            <strong>حد مطابقة الوجه</strong>
                            <small>
                                {{ number_format((float) $faceAttendance['similarity_threshold'] * 100, 0) }}%
                                — أي نتيجة أقل من هذا الحد لا تسجل حضورًا.
                            </small>
                        </div>
                    </div>

                    <div class="att-toggle-row">
                        <div class="att-toggle-copy">
                            <strong>اختبار الاتصال</strong>
                            <small id="comprefaceConnectionText">
                                اضغط لاختبار الاتصال الفعلي بخدمة CompreFace.
                            </small>
                        </div>

                        <button
                            class="btn btn-outline btn-sm"
                            type="button"
                            id="comprefaceConnectionButton"
                            data-url="{{ $faceAttendance['connection_test_url'] }}"
                        >
                            اختبار الاتصال
                        </button>
                    </div>

                    <div class="att-warning" style="margin-top:.8rem">
                        التسجيل والحضور يعتمدان على لقطة أمامية + حركة رأس يتم التحقق منها
                        عبر <strong>pose</strong> داخل CompreFace. لا يتم إرسال الوجه إلى خدمة سحابية خارجية.
                        تشغيل الكاميرا من هاتف داخل الشبكة يحتاج HTTPS، أما localhost على نفس الجهاز فيعمل للتجربة.
                    </div>
                </div>
            </div>

            <div class="att-setting-card">
                <div class="att-setting-head">
                    <div class="att-setting-icon">
                        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="5" width="18" height="14" rx="2"/>
                            <path d="M7 9h10M7 13h4M15 13h2"/>
                        </svg>
                    </div>
                    <div>
                        <h3>تأثير الحضور على الرواتب</h3>
                        <p>حدد ما الذي ينشئ حركات تلقائية داخل دورة الرواتب.</p>
                    </div>
                </div>

                <div class="att-setting-body">
                    <div class="att-toggle-row">
                        <div class="att-toggle-copy">
                            <strong>خصم التأخير والخروج المبكر</strong>
                            <small>يحسب بالدقائق وفق سعر الساعة للموظف.</small>
                        </div>
                        <label class="att-switch">
                            <input type="checkbox" name="payroll_late_deduction_enabled" value="1"
                                @checked(old('payroll_late_deduction_enabled', $settings['payroll_late_deduction_enabled']))>
                            <span class="att-slider"></span>
                        </label>
                    </div>

                    <div class="att-toggle-row">
                        <div class="att-toggle-copy">
                            <strong>خصم الغياب</strong>
                            <small>يحسب وفق سعر اليوم ولا يعتبر عدم وجود سجل حضور غيابًا تلقائيًا.</small>
                        </div>
                        <label class="att-switch">
                            <input type="checkbox" name="payroll_absence_deduction_enabled" value="1"
                                @checked(old('payroll_absence_deduction_enabled', $settings['payroll_absence_deduction_enabled']))>
                            <span class="att-slider"></span>
                        </label>
                    </div>

                    <div class="att-toggle-row">
                        <div class="att-toggle-copy">
                            <strong>احتساب الساعات الإضافية</strong>
                            <small>ينشئ Bonus تلقائيًا من سجلات الحضور المعتمدة.</small>
                        </div>
                        <label class="att-switch">
                            <input type="checkbox" name="payroll_overtime_enabled" value="1"
                                @checked(old('payroll_overtime_enabled', $settings['payroll_overtime_enabled']))>
                            <span class="att-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="att-setting-card">
                <div class="att-setting-head">
                    <div class="att-setting-icon">
                        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </div>
                    <div>
                        <h3>معايير الاحتساب</h3>
                        <p>القيم القياسية المستخدمة لتحويل الراتب الشهري إلى يومي/ساعي.</p>
                    </div>
                </div>

                <div class="att-setting-body">
                    <div class="att-number-grid">
                        <div class="form-group">
                            <label class="form-label">أيام العمل / شهر</label>
                            <input
                                class="form-input"
                                type="number"
                                step="0.5"
                                min="1"
                                max="31"
                                name="payroll_standard_work_days_per_month"
                                value="{{ old('payroll_standard_work_days_per_month', $settings['payroll_standard_work_days_per_month']) }}"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">ساعات العمل / يوم</label>
                            <input
                                class="form-input"
                                type="number"
                                step="0.5"
                                min="1"
                                max="24"
                                name="payroll_standard_hours_per_day"
                                value="{{ old('payroll_standard_hours_per_day', $settings['payroll_standard_hours_per_day']) }}"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">معامل الإضافي</label>
                            <input
                                class="form-input"
                                type="number"
                                step="0.05"
                                min="0"
                                max="10"
                                name="payroll_overtime_multiplier"
                                value="{{ old('payroll_overtime_multiplier', $settings['payroll_overtime_multiplier']) }}"
                                required
                            >
                        </div>
                    </div>

                    <div class="att-warning">
                        تعديل هذه القيم يؤثر على المزامنات الجديدة فقط. دورات الرواتب المعتمدة وكشوف الموظفين السابقة لا يتم تعديلها تلقائيًا.
                    </div>
                </div>
            </div>
        </div>

        <div class="att-savebar">
            <small>
                إيقاف أي ميزة لا يحذف بياناتها؛ يعطّل الوصول والمعالجة فقط.
            </small>

            <button class="btn btn-gold" type="submit">
                حفظ إعدادات الحضور والرواتب
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const attendance = document.getElementById('attendanceEnabled');
    const biometric = document.getElementById('biometricEnabled');
    const approval = document.getElementById('deviceRequireApproval');

    const syncDependencies = () => {
        const attendanceOn = attendance?.checked ?? false;

        if (biometric) {
            biometric.disabled = !attendanceOn;

            if (!attendanceOn) {
                biometric.checked = false;
            }
        }

        /*
         * لا نعطل خيار الاعتماد حتى لو كانت البصمة متوقفة،
         * لكي يبقى تفضيل الأمان محفوظًا عند تفعيلها لاحقًا.
         */
        if (approval) {
            approval.disabled = false;
        }
    };

    attendance?.addEventListener('change', syncDependencies);
    biometric?.addEventListener('change', syncDependencies);

    syncDependencies();

    const connectionButton =
        document.getElementById(
            'comprefaceConnectionButton'
        );

    const connectionText =
        document.getElementById(
            'comprefaceConnectionText'
        );

    connectionButton?.addEventListener(
        'click',
        async function () {
            const url =
                connectionButton.dataset.url;

            connectionButton.disabled = true;
            connectionButton.textContent =
                'جاري الاختبار...';

            if (connectionText) {
                connectionText.textContent =
                    'يتم الاتصال بخدمة CompreFace المحلية...';
            }

            try {
                const response = await fetch(
                    url,
                    {
                        headers: {
                            'Accept':
                                'application/json',
                            'X-Requested-With':
                                'XMLHttpRequest'
                        }
                    }
                );

                const data =
                    await response.json();

                if (
                    response.ok
                    && data.ok
                ) {
                    connectionText.innerHTML =
                        '<span style="color:var(--theme-success);font-weight:850">● الاتصال ناجح</span>'
                        + ' — CompreFace جاهز لاستقبال بصمات الوجه.';
                } else {
                    connectionText.innerHTML =
                        '<span style="color:var(--theme-danger);font-weight:850">● تعذر الاتصال</span>'
                        + ' — تحقق من تشغيل Docker والعنوان وAPI Key.';
                }
            } catch (error) {
                connectionText.innerHTML =
                    '<span style="color:var(--theme-danger);font-weight:850">● تعذر الاتصال</span>'
                    + ' — الخدمة غير متاحة حاليًا.';
            } finally {
                connectionButton.disabled = false;
                connectionButton.textContent =
                    'اختبار الاتصال';
            }
        }
    );
});
</script>
@endpush
@endsection
