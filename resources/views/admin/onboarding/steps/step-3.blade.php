@php($data = $run->branding_data ?? [])

<form
    method="POST"
    enctype="multipart/form-data"
    action="{{ route('onboarding.step.save', [$run, 3]) }}"
>
    @csrf
    @method('PATCH')

    <div class="onb-card">
        <div class="onb-card-head">
            <strong>3. الهوية والثيم</strong>
        </div>

        <div class="onb-card-body">
            <div class="onb-grid">
                <div>
                    <label class="form-label">اسم النظام بالعربية *</label>
                    <input class="form-input" name="system_name" value="{{ old('system_name', $data['system_name'] ?? '') }}" required>
                </div>

                <div>
                    <label class="form-label">اسم النظام بالإنجليزية</label>
                    <input class="form-input" name="system_name_en" value="{{ old('system_name_en', $data['system_name_en'] ?? '') }}">
                </div>

                <div>
                    <label class="form-label">الشعار المختصر بالعربية</label>
                    <input class="form-input" name="brand_tagline_ar" value="{{ old('brand_tagline_ar', $data['brand_tagline_ar'] ?? '') }}">
                </div>

                <div>
                    <label class="form-label">الشعار المختصر بالإنجليزية</label>
                    <input class="form-input" name="brand_tagline_en" value="{{ old('brand_tagline_en', $data['brand_tagline_en'] ?? '') }}">
                </div>

                <div class="onb-full">
                    <label class="form-label">نص التذييل</label>
                    <input class="form-input" name="brand_footer_text" value="{{ old('brand_footer_text', $data['brand_footer_text'] ?? '') }}">
                </div>

                <div>
                    <label class="form-label">اللون الرئيسي</label>
                    <input type="color" class="form-input" name="theme_primary" value="{{ old('theme_primary', $data['theme_primary'] ?? '#0F3D66') }}">
                </div>

                <div>
                    <label class="form-label">اللون الثانوي</label>
                    <input type="color" class="form-input" name="theme_secondary" value="{{ old('theme_secondary', $data['theme_secondary'] ?? '#1E5B8F') }}">
                </div>

                <div>
                    <label class="form-label">لون التمييز</label>
                    <input type="color" class="form-input" name="theme_accent" value="{{ old('theme_accent', $data['theme_accent'] ?? '#2F80ED') }}">
                </div>

                <div>
                    <label class="form-label">لون خلفية القائمة</label>
                    <input type="color" class="form-input" name="theme_sidebar_bg" value="{{ old('theme_sidebar_bg', $data['theme_sidebar_bg'] ?? '#0F3D66') }}">
                </div>

                <input type="hidden" name="theme_background" value="{{ $data['theme_background'] ?? '#F5F9FF' }}">
                <input type="hidden" name="theme_surface" value="{{ $data['theme_surface'] ?? '#FFFFFF' }}">
                <input type="hidden" name="theme_text" value="{{ $data['theme_text'] ?? '#16324A' }}">
                <input type="hidden" name="theme_sidebar_text" value="{{ $data['theme_sidebar_text'] ?? '#F3F8FF' }}">
                <input type="hidden" name="theme_sidebar_active" value="{{ $data['theme_sidebar_active'] ?? '#65B5FF' }}">
                <input type="hidden" name="theme_header_bg" value="{{ $data['theme_header_bg'] ?? '#FFFFFF' }}">
                <input type="hidden" name="theme_radius" value="{{ $data['theme_radius'] ?? 12 }}">
                <input type="hidden" name="theme_font_family" value="{{ $data['theme_font_family'] ?? 'Cairo' }}">
                <input type="hidden" name="system_theme_preset" value="{{ $data['system_theme_preset'] ?? 'custom' }}">

                @foreach([
                    'brand_logo' => 'الشعار الرئيسي',
                    'brand_logo_small' => 'الشعار المصغر',
                    'brand_report_logo' => 'شعار التقارير',
                    'brand_favicon' => 'Favicon',
                    'brand_login_background' => 'خلفية تسجيل الدخول',
                    'brand_stamp' => 'الختم',
                    'brand_signature' => 'التوقيع',
                ] as $key => $label)
                    <div>
                        <label class="form-label">{{ $label }}</label>
                        <input type="file" class="form-input" name="{{ $key }}" accept="image/*,.ico">
                        @if(!empty($data[$key]))
                            <div class="onb-preview" style="margin-top:.55rem">
                                <img src="{{ asset($data[$key]) }}" alt="{{ $label }}">
                                <small>الصورة الحالية ستبقى ما لم ترفع بديلًا.</small>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="onb-actions">
                <a class="btn btn-ghost" href="{{ route('onboarding.index', ['step' => 2]) }}">السابق</a>
                <button type="submit" class="btn btn-gold">حفظ ومتابعة</button>
            </div>
        </div>
    </div>
</form>
