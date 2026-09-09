@extends('layouts.app')

@section('title', 'إعدادات النظام')
@section('page-title', 'إعدادات النظام')

@section('content')

<div class="page-header">
    <div class="page-header-text">
        <h1 class="page-heading">إعدادات النظام</h1>
        <p class="page-subheading">
            إدارة التشغيل والهوية البصرية والثيم من مكان واحد
        </p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:1rem">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:1rem">
        <strong>يرجى مراجعة البيانات التالية:</strong>
        <ul style="margin:.5rem 0 0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@include('admin.settings.partials.attendance-payroll-card')

<form
    action="{{ route('settings.update') }}"
    method="POST"
    enctype="multipart/form-data"
    id="system-settings-form"
>
    @csrf

    @php
        $groupMeta = [
            'general'       => ['label' => 'إعدادات عامة', 'description' => 'إعدادات النظام الأساسية'],
            'branding'      => ['label' => 'الهوية والشعار', 'description' => 'الشعارات والختم والتوقيع والهوية'],
            'theme'         => ['label' => 'ألوان وثيم النظام', 'description' => 'تغيير ألوان النظام والخط والمظهر العام'],
            'customer_display' => ['label' => 'شاشة طلبات العملاء', 'description' => 'تخصيص تصميم الشاشة فقط — اسم المطعم والشعار والهوية تؤخذ تلقائيًا من قسم الهوية والشعار'],
            'customer_menu' => ['label' => 'منيو QR وطلب العميل', 'description' => 'الألوان والغلاف والبطاقات ومظهر المنيو العام'],
            'chat'          => ['label' => 'ثيم وخلفية المحادثات', 'description' => 'تخصيص خلفية وألوان القنوات والرسائل وحقل الكتابة'],
            'modules'       => ['label' => 'الوحدات والميزات', 'description' => 'تشغيل أو إيقاف الوحدات الاختيارية في النظام'],
            'financial'     => ['label' => 'الإعدادات المالية', 'description' => 'سياسات الدفع والتحصيل'],
            'cake_orders'   => ['label' => 'طلبات الكيك الخاصة', 'description' => 'خيارات سير طلبات الكيك'],
            'cashier'       => ['label' => 'إعدادات الكاشير', 'description' => 'إعدادات الجلسات والدفع'],
            'orders'        => ['label' => 'إعدادات الطلبات', 'description' => 'خيارات الطلبات والترقيم'],
            'invoices'      => ['label' => 'إعدادات الفواتير', 'description' => 'إعدادات وترقيم الفواتير'],
            'inventory'     => ['label' => 'إعدادات المخزون', 'description' => 'خيارات المخزون والحركات'],
            'notifications' => ['label' => 'إعدادات الإشعارات', 'description' => 'الصوت والتنبيهات'],
        ];

        $imageKeys = [
            'brand_logo',
            'brand_logo_small',
            'brand_favicon',
            'brand_report_logo',
            'brand_stamp',
            'brand_signature',
            'brand_login_background',
            'chat_background_image',
            'customer_display_background_image',
            'customer_menu_cover',
        ];

        $themeDefaults = [
            'theme_primary'        => '#0A2948',
            'theme_secondary'      => '#C98516',
            'theme_accent'         => '#C98516',
            'theme_background'     => '#F5F6F8',
            'theme_surface'        => '#FFFFFF',
            'theme_text'           => '#172435',
            'theme_text_muted'     => '#687482',
            'theme_border'         => '#DDE2E7',
            'theme_sidebar_bg'     => '#0A2948',
            'theme_sidebar_text'   => '#FFFFFF',
            'theme_sidebar_active'       => '#C98516',
            'theme_sidebar_footer_bg'    => '#FFFFFF',
            'theme_sidebar_footer_text'  => '#172435',
            'theme_sidebar_footer_muted' => '#687482',
            'theme_sidebar_footer_border'=> '#DDE2E7',
            'theme_sidebar_footer_icon'  => '#687482',
            'theme_header_bg'            => '#FFFFFF',
            'theme_success'        => '#197438',
            'theme_warning'        => '#C98516',
            'theme_danger'         => '#E22929',
            'theme_info'           => '#2F72C4',
        ];


        $customerDisplayDefaults = [
            'customer_display_background_color' => '#090909',
            'customer_display_overlay_color'    => '#000000',
            'customer_display_header_bg'        => '#090909',
            'customer_display_panel_bg'         => '#111111',
            'customer_display_card_bg'          => '#181818',
            'customer_display_text_color'       => '#FFFFFF',
            'customer_display_muted_color'      => '#A3A3A3',
            'customer_display_preparing_color'  => '#F0B429',
            'customer_display_ready_color'      => '#24C36B',
            'customer_display_accent_color'     => '#D7A51D',
            'customer_display_border_color'     => '#2A2A2A',
        ];

        $customerDisplayBackground = old(
            'customer_display_background_image',
            \App\Models\SystemSetting::get(
                'customer_display_background_image',
                ''
            )
        );

        $systemThemePresets = [
            'dahab-gold' => [
                'name' => 'Dahab Gold',
                'description' => 'الثيم الذهبي الرسمي — فاتح وفخم.',
                'swatches' => ['#0A2948', '#C98516', '#FFFFFF'],
            ],
            'midnight' => [
                'name' => 'Midnight',
                'description' => 'ثيم داكن احترافي للاستخدام الطويل.',
                'swatches' => ['#0B1220', '#1E293B', '#38BDF8'],
            ],
            'emerald' => [
                'name' => 'Emerald',
                'description' => 'أخضر عصري وهادئ مع واجهة فاتحة.',
                'swatches' => ['#064E3B', '#10B981', '#F8FAFC'],
            ],
            'ocean' => [
                'name' => 'Ocean Blue',
                'description' => 'أزرق نظيف يناسب الأنظمة الإدارية.',
                'swatches' => ['#0F3D66', '#2F80ED', '#F5F9FF'],
            ],
            'rose' => [
                'name' => 'Rose',
                'description' => 'وردي خمري أنيق بواجهة دافئة.',
                'swatches' => ['#881337', '#E11D48', '#FFF7F8'],
            ],
            'graphite' => [
                'name' => 'Graphite',
                'description' => 'فحمي معدني مع لمسة برتقالية.',
                'swatches' => ['#1F2937', '#F59E0B', '#F3F4F6'],
            ],
        ];
    @endphp

    <div class="settings-shell">

        <aside class="settings-nav">
            @foreach($settings as $group => $groupSettings)
                @php
                    $meta = $groupMeta[$group] ?? [
                        'label' => $group,
                        'description' => '',
                    ];
                @endphp

                <button
                    type="button"
                    class="settings-nav-item {{ $loop->first ? 'active' : '' }}"
                    data-settings-target="settings-group-{{ $group }}"
                >
                    <strong>{{ $meta['label'] }}</strong>
                    <span>{{ $meta['description'] }}</span>
                </button>
            @endforeach
        </aside>

        <div class="settings-content">

            @foreach($settings as $group => $groupSettings)
                @php
                    $meta = $groupMeta[$group] ?? [
                        'label' => $group,
                        'description' => '',
                    ];
                @endphp

                <section
                    id="settings-group-{{ $group }}"
                    class="settings-section {{ $loop->first ? 'active' : '' }}"
                >
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <div class="card-title">
                                    {{ $meta['label'] }}
                                </div>
                                <div class="settings-section-description">
                                    {{ $meta['description'] }}
                                </div>
                            </div>

                            <span class="badge badge-grey">
                                {{ $groupSettings->count() }} إعداد
                            </span>
                        </div>

                        <div class="card-body">

                            @if($group === 'theme')
                                @php
                                    $selectedSystemThemePreset = old(
                                        'system_theme_preset',
                                        \App\Models\SystemSetting::get(
                                            'system_theme_preset',
                                            'custom'
                                        )
                                    );
                                @endphp

                                <input
                                    type="hidden"
                                    name="system_theme_preset"
                                    id="setting_system_theme_preset"
                                    value="{{ $selectedSystemThemePreset }}"
                                >

                                <div class="system-theme-preset-section">
                                    <div class="system-theme-preset-head">
                                        <div>
                                            <strong>ثيمات جاهزة للنظام</strong>
                                            <span>
                                                اختر ثيمًا كاملًا، شاهد المعاينة فورًا،
                                                ثم احفظ الإعدادات. ويمكنك التعديل يدويًا بعد الاختيار.
                                            </span>
                                        </div>

                                        <span
                                            class="system-theme-preset-status"
                                            id="systemThemePresetStatus"
                                        >
                                            {{
                                                $selectedSystemThemePreset === 'custom'
                                                    ? 'مخصص'
                                                    : ($systemThemePresets[$selectedSystemThemePreset]['name'] ?? 'مخصص')
                                            }}
                                        </span>
                                    </div>

                                    <div
                                        class="system-theme-preset-grid"
                                        id="systemThemePresetGrid"
                                    >
                                        @foreach($systemThemePresets as $presetCode => $preset)
                                            <button
                                                type="button"
                                                class="system-theme-preset-card {{ $selectedSystemThemePreset === $presetCode ? 'active' : '' }}"
                                                data-system-theme-preset="{{ $presetCode }}"
                                            >
                                                <span class="system-theme-preset-preview">
                                                    <span
                                                        class="system-theme-mini-sidebar"
                                                        style="--mini-sidebar:{{ $preset['swatches'][0] }}"
                                                    ></span>
                                                    <span
                                                        class="system-theme-mini-main"
                                                        style="
                                                            --mini-accent:{{ $preset['swatches'][1] }};
                                                            --mini-surface:{{ $preset['swatches'][2] }};
                                                        "
                                                    >
                                                        <i></i>
                                                        <b></b>
                                                        <em></em>
                                                    </span>
                                                </span>

                                                <strong>{{ $preset['name'] }}</strong>
                                                <small>{{ $preset['description'] }}</small>

                                                <span class="system-theme-swatches">
                                                    @foreach($preset['swatches'] as $swatch)
                                                        <i style="--sw:{{ $swatch }}"></i>
                                                    @endforeach
                                                </span>
                                            </button>
                                        @endforeach
                                    </div>

                                    <div class="system-theme-preset-note">
                                        <strong>ملاحظة:</strong>
                                        أي تعديل يدوي على ألوان الثيم بعد اختيار قالب
                                        سيحوّل الحالة إلى <b>مخصص</b> بدون فقدان تعديلاتك.
                                    </div>
                                </div>

                                <div class="theme-preview" id="theme-preview">
                                    <div class="theme-preview-sidebar">
                                        <div class="theme-preview-logo">
                                            {{ \App\Models\SystemSetting::get('system_name', 'دهب') }}
                                        </div>
                                        <div class="theme-preview-link active">لوحة التحكم</div>
                                        <div class="theme-preview-link">الطلبات</div>
                                        <div class="theme-preview-link">التقارير</div>

                                        <div class="theme-preview-spacer"></div>

                                        <div class="theme-preview-footer">
                                            <div class="theme-preview-avatar">م</div>
                                            <div class="theme-preview-user">
                                                <strong>مدير النظام</strong>
                                                <span>Admin</span>
                                            </div>
                                            <div class="theme-preview-logout">↪</div>
                                        </div>
                                    </div>

                                    <div class="theme-preview-main">
                                        <div class="theme-preview-header">
                                            معاينة الثيم
                                        </div>

                                        <div class="theme-preview-card">
                                            <strong>بطاقة تجريبية</strong>
                                            <span>ستتغير الألوان مباشرة أثناء التعديل.</span>

                                            <button type="button" class="theme-preview-button">
                                                زر رئيسي
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @include('admin.settings.partials.attendance-payroll-card')

                            @include('admin.settings.partials.print-branding-card')

                            @if($group === 'chat')
                                @php
                                    $selectedChatPreset = old(
                                        'chat_theme_preset',
                                        \App\Models\SystemSetting::get(
                                            'chat_theme_preset',
                                            'whatsapp-soft'
                                        )
                                    );
                                @endphp

                                <input
                                    type="hidden"
                                    name="chat_theme_preset"
                                    id="setting_chat_theme_preset"
                                    value="{{ $selectedChatPreset }}"
                                >

                                <div class="chat-preset-section">
                                    <div class="chat-preset-head">
                                        <div>
                                            <strong>قوالب جاهزة للمحادثة</strong>
                                            <span>اختر ثيم سريع مثل واتساب ثم عدّل الألوان إذا رغبت.</span>
                                        </div>
                                    </div>

                                    <div class="chat-preset-grid" id="chatPresetGrid">
                                        <button type="button" class="chat-preset-card {{ $selectedChatPreset === 'whatsapp-soft' ? 'active' : '' }}" data-chat-preset="whatsapp-soft">
                                            <span class="chat-preset-swatches">
                                                <i style="--sw:#e7f7ef"></i><i style="--sw:#ffffff"></i><i style="--sw:#25d366"></i>
                                            </span>
                                            <strong>WhatsApp Soft</strong>
                                            <small>هادئ – فاتح – احترافي</small>
                                        </button>

                                        <button type="button" class="chat-preset-card {{ $selectedChatPreset === 'emerald' ? 'active' : '' }}" data-chat-preset="emerald">
                                            <span class="chat-preset-swatches">
                                                <i style="--sw:#ecfdf5"></i><i style="--sw:#ffffff"></i><i style="--sw:#10b981"></i>
                                            </span>
                                            <strong>Emerald</strong>
                                            <small>أخضر أنيق ومريح</small>
                                        </button>

                                        <button type="button" class="chat-preset-card {{ $selectedChatPreset === 'midnight' ? 'active' : '' }}" data-chat-preset="midnight">
                                            <span class="chat-preset-swatches">
                                                <i style="--sw:#0f172a"></i><i style="--sw:#1e293b"></i><i style="--sw:#38bdf8"></i>
                                            </span>
                                            <strong>Midnight</strong>
                                            <small>داكن عصري</small>
                                        </button>

                                        <button type="button" class="chat-preset-card {{ $selectedChatPreset === 'rose' ? 'active' : '' }}" data-chat-preset="rose">
                                            <span class="chat-preset-swatches">
                                                <i style="--sw:#fff1f2"></i><i style="--sw:#ffffff"></i><i style="--sw:#e11d48"></i>
                                            </span>
                                            <strong>Rose</strong>
                                            <small>دافئ وأنيق</small>
                                        </button>
                                    </div>
                                </div>
                            @endif

                            @if($group === 'chat')
                                <div class="chat-theme-preview" id="chat-theme-preview">
                                    <div class="chat-preview-sidebar">
                                        <div class="chat-preview-sidebar-head">
                                            المحادثات
                                        </div>

                                        <div class="chat-preview-channel active">
                                            <strong>فرع النصر</strong>
                                            <span>آخر رسالة تجريبية</span>
                                        </div>

                                        <div class="chat-preview-channel">
                                            <strong>فرع الرمال</strong>
                                            <span>لا توجد رسائل جديدة</span>
                                        </div>
                                    </div>

                                    <div class="chat-preview-main">
                                        <div class="chat-preview-head">
                                            فرع النصر
                                        </div>

                                        <div class="chat-preview-messages">
                                            <div class="chat-preview-bubble other">
                                                رسالة من مدير الفرع
                                            </div>

                                            <div class="chat-preview-bubble mine">
                                                وهذه رسالة من الإدارة
                                            </div>
                                        </div>

                                        <div class="chat-preview-composer">
                                            <div class="chat-preview-input">
                                                اكتب رسالتك...
                                            </div>

                                            <div class="chat-preview-send">
                                                ➤
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif


                            @if($group === 'customer_display')
                                <div
                                    class="customer-display-settings-preview"
                                    id="customer-display-settings-preview"
                                    @if($customerDisplayBackground)
                                        style="--customer-preview-bg-image:url('{{ asset($customerDisplayBackground) }}')"
                                    @endif
                                >
                                    <div class="customer-display-preview-overlay"></div>

                                    <div class="customer-display-preview-header">
                                        <div class="customer-display-preview-brand">
                                            <div class="customer-display-preview-logo">
                                                @php
                                                    $previewBrandName = \App\Models\SystemSetting::get(
                                                        'system_name',
                                                        'حلويات دهب'
                                                    );

                                                    $previewBrandNameEn = \App\Models\SystemSetting::get(
                                                        'system_name_en',
                                                        'Dahab Sweets'
                                                    );

                                                    $previewBrandLogoPath = \App\Models\SystemSetting::get(
                                                        'brand_logo',
                                                        ''
                                                    );

                                                    $previewBrandLogoSmallPath = \App\Models\SystemSetting::get(
                                                        'brand_logo_small',
                                                        ''
                                                    );

                                                    $previewBrandLogoUrl = $previewBrandLogoPath
                                                        ? asset($previewBrandLogoPath)
                                                        : (
                                                            $previewBrandLogoSmallPath
                                                                ? asset($previewBrandLogoSmallPath)
                                                                : (
                                                                    file_exists(
                                                                        public_path(
                                                                            'assets/images/logo.png'
                                                                        )
                                                                    )
                                                                        ? asset(
                                                                            'assets/images/logo.png'
                                                                        )
                                                                        : null
                                                                )
                                                        );
                                                @endphp

                                                @if($previewBrandLogoUrl)
                                                    <img
                                                        src="{{ $previewBrandLogoUrl }}"
                                                        alt="{{ $previewBrandName }}"
                                                    >
                                                @else
                                                    <span>LOGO</span>
                                                @endif
                                            </div>

                                            <div>
                                                <strong>
                                                    {{ $previewBrandName }}
                                                </strong>

                                                @if(filled($previewBrandNameEn))
                                                    <span>
                                                        {{ $previewBrandNameEn }}
                                                    </span>
                                                @endif

                                                <span>الفرع الرئيسي</span>
                                            </div>
                                        </div>

                                        <div class="customer-display-preview-clock">
                                            <strong>03:15 م</strong>
                                            <span>22 أغسطس 2026</span>
                                        </div>
                                    </div>

                                    <div class="customer-display-preview-board">
                                        <div class="customer-display-preview-column preparing">
                                            <div class="customer-display-preview-column-head">
                                                <div>
                                                    <i></i>
                                                    <strong>قيد التحضير</strong>
                                                </div>

                                                <span>2</span>
                                            </div>

                                            <div class="customer-display-preview-orders">
                                                <article>
                                                    <b>0125</b>
                                                    <strong>قيد التحضير</strong>
                                                    <small>طاولة 4 · داخل المطعم</small>
                                                </article>

                                                <article>
                                                    <b>0128</b>
                                                    <strong>بانتظار التحضير</strong>
                                                    <small>سفري</small>
                                                </article>
                                            </div>
                                        </div>

                                        <div class="customer-display-preview-column ready">
                                            <div class="customer-display-preview-column-head">
                                                <div>
                                                    <i></i>
                                                    <strong>جاهز للاستلام</strong>
                                                </div>

                                                <span>1</span>
                                            </div>

                                            <div class="customer-display-preview-orders">
                                                <article>
                                                    <b>0122</b>
                                                    <strong>جاهز للاستلام</strong>
                                                    <small>سفري</small>
                                                </article>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="customer-display-preview-footer">
                                        <span>● متصل</span>
                                        <span>معاينة مباشرة لتصميم شاشة العملاء</span>
                                    </div>
                                </div>

                                <div class="customer-display-preview-note">
                                    <strong>معاينة فورية:</strong>
                                    غيّر الألوان أو ارفع خلفية جديدة وستظهر النتيجة هنا مباشرة قبل الحفظ.
                                    يفضّل استخدام خلفية أفقية بدقة 1920×1080 أو أعلى.
                                </div>
                            @endif

                            <div class="settings-grid">

                                @foreach($groupSettings as $setting)
                                    @continue(in_array($setting->key, ['chat_theme_preset', 'system_theme_preset'], true))
                                    @php
                                        $value = old($setting->key, $setting->value);
                                    @endphp

                                    <div class="form-group setting-field">

                                        <label
                                            class="form-label"
                                            for="setting_{{ $setting->key }}"
                                        >
                                            {{ $setting->label ?? str_replace('_', ' ', $setting->key) }}
                                        </label>

                                        @if($setting->description)
                                            <span class="form-hint">
                                                {{ $setting->description }}
                                            </span>
                                        @endif


                                        @if(in_array($setting->key, $imageKeys, true) || $setting->type === 'image')

                                            <div class="brand-upload-box">

                                                @if($setting->value)
                                                    <div class="brand-current-image">
                                                        <img
                                                            src="{{ asset($setting->value) }}"
                                                            alt="{{ $setting->label }}"
                                                            id="preview_{{ $setting->key }}"
                                                        >
                                                    </div>
                                                @else
                                                    <div
                                                        class="brand-current-image empty"
                                                        id="preview_wrap_{{ $setting->key }}"
                                                    >
                                                        <span>لا توجد صورة حالياً</span>
                                                        <img
                                                            src=""
                                                            alt=""
                                                            id="preview_{{ $setting->key }}"
                                                            style="display:none"
                                                        >
                                                    </div>
                                                @endif

                                                <input
                                                    type="file"
                                                    id="setting_{{ $setting->key }}"
                                                    name="{{ $setting->key }}"
                                                    class="form-input brand-image-input"
                                                    data-preview="preview_{{ $setting->key }}"
                                                    accept=".png,.jpg,.jpeg,.webp,.ico"
                                                >

                                                @if($setting->value)
                                                    <label class="remove-image-option">
                                                        <input
                                                            type="checkbox"
                                                            name="remove_{{ $setting->key }}"
                                                            value="1"
                                                        >
                                                        حذف الصورة الحالية
                                                    </label>
                                                @endif
                                            </div>


                                        @elseif($setting->type === 'color')

                                            <div class="color-setting-row">
                                                <input
                                                    type="color"
                                                    id="setting_{{ $setting->key }}_picker"
                                                    value="{{ $value ?: ($themeDefaults[$setting->key] ?? '#000000') }}"
                                                    class="color-picker"
                                                    data-color-key="{{ $setting->key }}"
                                                >

                                                <input
                                                    type="text"
                                                    id="setting_{{ $setting->key }}"
                                                    name="{{ $setting->key }}"
                                                    value="{{ $value }}"
                                                    class="form-input color-value"
                                                    maxlength="7"
                                                    data-color-key="{{ $setting->key }}"
                                                >
                                            </div>


                                        @elseif($setting->type === 'boolean')

                                            <div class="boolean-options">
                                                <label>
                                                    <input
                                                        type="radio"
                                                        name="{{ $setting->key }}"
                                                        value="1"
                                                        {{ (string) $value === '1' ? 'checked' : '' }}
                                                    >
                                                    مفعّل
                                                </label>

                                                <label>
                                                    <input
                                                        type="radio"
                                                        name="{{ $setting->key }}"
                                                        value="0"
                                                        {{ (string) $value === '0' ? 'checked' : '' }}
                                                    >
                                                    معطّل
                                                </label>
                                            </div>


                                        @elseif($setting->type === 'select' && $setting->key === 'theme_font_family')

                                            <select
                                                id="setting_{{ $setting->key }}"
                                                name="{{ $setting->key }}"
                                                class="form-input"
                                            >
                                                @foreach(['Cairo', 'Tajawal', 'Arial', 'Tahoma'] as $font)
                                                    <option
                                                        value="{{ $font }}"
                                                        {{ $value === $font ? 'selected' : '' }}
                                                    >
                                                        {{ $font }}
                                                    </option>
                                                @endforeach
                                            </select>


                                        @elseif($setting->type === 'integer')

                                            <input
                                                type="number"
                                                id="setting_{{ $setting->key }}"
                                                name="{{ $setting->key }}"
                                                class="form-input"
                                                value="{{ $value }}"
                                                step="1"
                                            >


                                        @elseif($setting->type === 'decimal')

                                            <input
                                                type="number"
                                                id="setting_{{ $setting->key }}"
                                                name="{{ $setting->key }}"
                                                class="form-input"
                                                value="{{ $value }}"
                                                step="0.01"
                                            >


                                        @elseif(in_array($setting->type, ['text', 'textarea'], true))

                                            <textarea
                                                id="setting_{{ $setting->key }}"
                                                name="{{ $setting->key }}"
                                                class="form-textarea"
                                                rows="3"
                                            >{{ $value }}</textarea>


                                        @else

                                            <input
                                                type="text"
                                                id="setting_{{ $setting->key }}"
                                                name="{{ $setting->key }}"
                                                class="form-input"
                                                value="{{ $value }}"
                                            >

                                        @endif

                                    </div>
                                @endforeach

                            </div>

                            @if($group === 'theme')
                                <div class="theme-actions">
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-sm"
                                        id="reset-theme-defaults"
                                    >
                                        استعادة ألوان دهب الافتراضية
                                    </button>
                                </div>
                            @endif

                        </div>
                    </div>
                </section>
            @endforeach

            <div class="settings-save-bar">
                <div>
                    <strong>حفظ التغييرات</strong>
                    <span>
                        سيتم تطبيق الهوية والثيم بعد الحفظ مباشرة.
                    </span>
                </div>

                <div class="settings-save-actions">
                    <a href="{{ route('dashboard') }}" class="btn btn-ghost">
                        إلغاء
                    </a>

                    <button class="btn btn-gold" type="submit">
                        حفظ الإعدادات
                    </button>
                </div>
            </div>

        </div>
    </div>
</form>


<style>
    .settings-shell {
        display: grid;
        grid-template-columns: 240px minmax(0, 1fr);
        gap: 1.25rem;
        align-items: start;
    }

    .settings-nav {
        position: sticky;
        top: 84px;
        display: grid;
        gap: .45rem;
    }

    .settings-nav-item {
        width: 100%;
        border: 1px solid var(--border);
        background: var(--card-bg);
        color: var(--text-main);
        border-radius: 10px;
        text-align: right;
        padding: .8rem .9rem;
        cursor: pointer;
        transition: .15s ease;
    }

    .settings-nav-item strong,
    .settings-nav-item span {
        display: block;
    }

    .settings-nav-item strong {
        font-size: .87rem;
    }

    .settings-nav-item span {
        margin-top: .15rem;
        color: var(--text-muted);
        font-size: .72rem;
    }

    .settings-nav-item.active {
        border-color: var(--gold);
        box-shadow: 0 0 0 1px var(--gold);
    }

    .settings-section {
        display: none;
    }

    .settings-section.active {
        display: block;
    }

    .settings-section-description {
        color: var(--text-muted);
        font-size: .76rem;
        margin-top: .15rem;
    }

    .settings-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem 1.25rem;
    }

    .setting-field {
        min-width: 0;
    }

    .brand-upload-box {
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: .8rem;
        background: rgba(0, 0, 0, .015);
    }

    .brand-current-image {
        height: 120px;
        border: 1px dashed var(--border);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        background: #fff;
        margin-bottom: .75rem;
    }

    .brand-current-image img {
        max-width: 100%;
        max-height: 105px;
        object-fit: contain;
    }

    .brand-current-image.empty span {
        color: var(--text-muted);
        font-size: .76rem;
    }

    .remove-image-option {
        display: flex;
        align-items: center;
        gap: .4rem;
        margin-top: .55rem;
        color: var(--error);
        font-size: .78rem;
        cursor: pointer;
    }

    .color-setting-row {
        display: grid;
        grid-template-columns: 54px 1fr;
        gap: .6rem;
        align-items: center;
    }

    .color-picker {
        width: 54px;
        height: 42px;
        padding: 2px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: transparent;
        cursor: pointer;
    }

    .boolean-options {
        display: flex;
        align-items: center;
        gap: 1rem;
        min-height: 42px;
    }

    .boolean-options label {
        display: flex;
        align-items: center;
        gap: .4rem;
        font-size: .85rem;
        cursor: pointer;
    }

    .boolean-options input {
        accent-color: var(--gold);
    }

    .theme-preview {
        display: grid;
        grid-template-columns: 170px 1fr;
        min-height: 250px;
        overflow: hidden;
        border: 1px solid var(--border);
        border-radius: 12px;
        margin-bottom: 1.25rem;
        background: var(--theme-preview-bg, #F5F6F8);
    }

    .theme-preview-sidebar {
        background: var(--theme-preview-sidebar, #0A2948);
        color: var(--theme-preview-sidebar-text, #FFFFFF);
        padding: 1rem 1rem 0;
        display: flex;
        flex-direction: column;
        min-height: 250px;
    }

    .theme-preview-logo {
        font-weight: 800;
        font-size: 1.05rem;
        margin-bottom: 1rem;
        color: var(--theme-preview-accent, #C98516);
    }

    .theme-preview-link {
        padding: .55rem .65rem;
        border-radius: 7px;
        margin-bottom: .35rem;
        font-size: .78rem;
    }

    .theme-preview-link.active {
        background: var(--theme-preview-active, #C98516);
        color: #fff;
    }

    .theme-preview-spacer {
        flex: 1;
        min-height: 18px;
    }

    .theme-preview-footer {
        margin: 0 -1rem;
        padding: .65rem .75rem;
        min-height: 58px;
        display: flex;
        align-items: center;
        gap: .55rem;
        background: var(--theme-preview-footer-bg, #FFFFFF);
        color: var(--theme-preview-footer-text, #172435);
        border-top: 1px solid var(--theme-preview-footer-border, #DDE2E7);
    }

    .theme-preview-avatar {
        width: 30px;
        height: 30px;
        flex: 0 0 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: color-mix(
            in srgb,
            var(--theme-preview-active, #C98516) 18%,
            #FFFFFF
        );
        color: var(--theme-preview-footer-text, #172435);
        font-weight: 800;
        font-size: .75rem;
    }

    .theme-preview-user {
        min-width: 0;
        flex: 1;
    }

    .theme-preview-user strong,
    .theme-preview-user span {
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .theme-preview-user strong {
        color: var(--theme-preview-footer-text, #172435);
        font-size: .72rem;
    }

    .theme-preview-user span {
        color: var(--theme-preview-footer-muted, #687482);
        font-size: .64rem;
        margin-top: .05rem;
    }

    .theme-preview-logout {
        color: var(--theme-preview-footer-icon, #687482);
        font-weight: 800;
        font-size: .85rem;
    }

    .theme-preview-main {
        background: var(--theme-preview-bg, #F5F6F8);
    }

    .theme-preview-header {
        background: var(--theme-preview-header, #FFFFFF);
        border-bottom: 1px solid var(--theme-preview-border, #DDE2E7);
        color: var(--theme-preview-text, #172435);
        padding: .8rem 1rem;
        font-weight: 700;
    }

    .theme-preview-card {
        margin: 1rem;
        background: var(--theme-preview-surface, #FFFFFF);
        border: 1px solid var(--theme-preview-border, #DDE2E7);
        color: var(--theme-preview-text, #172435);
        border-radius: var(--theme-preview-radius, 10px);
        padding: 1rem;
    }

    .theme-preview-card strong,
    .theme-preview-card span {
        display: block;
    }

    .theme-preview-card span {
        margin-top: .3rem;
        color: var(--theme-preview-muted, #687482);
        font-size: .76rem;
    }

    .theme-preview-button {
        margin-top: 1rem;
        border: 0;
        border-radius: 7px;
        background: var(--theme-preview-accent, #C98516);
        color: #fff;
        padding: .55rem .9rem;
        font-family: inherit;
    }



    /* =========================================
       Customer Order Display preview
    ========================================= */

    .customer-display-settings-preview {
        --customer-preview-background: #090909;
        --customer-preview-overlay: #000000;
        --customer-preview-overlay-opacity: .72;
        --customer-preview-header: #090909;
        --customer-preview-panel: #111111;
        --customer-preview-panel-opacity: 92%;
        --customer-preview-card: #181818;
        --customer-preview-text: #FFFFFF;
        --customer-preview-muted: #A3A3A3;
        --customer-preview-preparing: #F0B429;
        --customer-preview-ready: #24C36B;
        --customer-preview-accent: #D7A51D;
        --customer-preview-border: #2A2A2A;
        --customer-preview-radius: 22px;
        --customer-preview-blur: 8px;
        --customer-preview-logo-size: 48px;
        --customer-preview-number-size: 38px;
        --customer-preview-bg-image: none;

        position: relative;
        isolation: isolate;
        min-height: 390px;
        margin-bottom: 1rem;
        overflow: hidden;
        color: var(--customer-preview-text);
        background:
            var(--customer-preview-bg-image)
            center / cover no-repeat,
            var(--customer-preview-background);
        border: 1px solid var(--customer-preview-border);
        border-radius: 18px;
        box-shadow: 0 18px 45px rgba(15, 23, 42, .12);
    }

    .customer-display-preview-overlay {
        position: absolute;
        z-index: -1;
        inset: 0;
        background: var(--customer-preview-overlay);
        opacity: var(--customer-preview-overlay-opacity);
        pointer-events: none;
    }

    .customer-display-preview-header,
    .customer-display-preview-footer {
        position: relative;
        z-index: 1;
        background:
            color-mix(
                in srgb,
                var(--customer-preview-header) 94%,
                transparent
            );
        backdrop-filter: blur(var(--customer-preview-blur));
    }

    .customer-display-preview-header {
        min-height: 64px;
        padding: .65rem .85rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        border-bottom: 1px solid var(--customer-preview-border);
    }

    .customer-display-preview-brand {
        display: flex;
        align-items: center;
        gap: .65rem;
    }

    .customer-display-preview-logo {
        width: var(--customer-preview-logo-size);
        height: var(--customer-preview-logo-size);
        flex: 0 0 var(--customer-preview-logo-size);
        display: grid;
        place-items: center;
        overflow: hidden;
        border-radius:
            max(
                9px,
                calc(var(--customer-preview-radius) * .45)
            );
        background: rgba(255,255,255,.07);
        color: var(--customer-preview-accent);
        font-size: .55rem;
        font-weight: 900;
    }

    .customer-display-preview-logo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        padding: 4px;
    }

    .customer-display-preview-brand strong,
    .customer-display-preview-brand span,
    .customer-display-preview-clock strong,
    .customer-display-preview-clock span {
        display: block;
    }

    .customer-display-preview-brand strong {
        font-size: .78rem;
    }

    .customer-display-preview-brand span {
        margin-top: .15rem;
        color: var(--customer-preview-accent);
        font-size: .59rem;
        font-weight: 800;
    }

    .customer-display-preview-clock {
        direction: ltr;
        text-align: left;
    }

    .customer-display-preview-clock strong {
        font-size: .74rem;
    }

    .customer-display-preview-clock span {
        margin-top: .12rem;
        color: var(--customer-preview-muted);
        font-size: .55rem;
    }

    .customer-display-preview-board {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: 1.08fr .92fr;
        gap: .7rem;
        padding: .8rem;
        min-height: 275px;
    }

    .customer-display-preview-column {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--customer-preview-border);
        border-radius: var(--customer-preview-radius);
        background:
            color-mix(
                in srgb,
                var(--customer-preview-panel)
                var(--customer-preview-panel-opacity),
                transparent
            );
        backdrop-filter: blur(var(--customer-preview-blur));
    }

    .customer-display-preview-column-head {
        min-height: 48px;
        padding: .55rem .65rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
        border-bottom: 1px solid var(--customer-preview-border);
    }

    .customer-display-preview-column-head > div {
        display: flex;
        align-items: center;
        gap: .4rem;
    }

    .customer-display-preview-column-head i {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--customer-preview-preparing);
        box-shadow:
            0 0 0 4px
            color-mix(
                in srgb,
                var(--customer-preview-preparing) 13%,
                transparent
            );
    }

    .customer-display-preview-column.ready
    .customer-display-preview-column-head i {
        background: var(--customer-preview-ready);
        box-shadow:
            0 0 0 4px
            color-mix(
                in srgb,
                var(--customer-preview-ready) 13%,
                transparent
            );
    }

    .customer-display-preview-column-head strong {
        font-size: .75rem;
    }

    .customer-display-preview-column-head > span {
        min-width: 26px;
        min-height: 25px;
        display: grid;
        place-items: center;
        border-radius: 999px;
        background: rgba(255,255,255,.08);
        font-size: .63rem;
        font-weight: 900;
    }

    .customer-display-preview-orders {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .5rem;
        padding: .6rem;
    }

    .customer-display-preview-column.ready
    .customer-display-preview-orders {
        grid-template-columns: 1fr;
    }

    .customer-display-preview-orders article {
        position: relative;
        min-height: 125px;
        padding: .65rem;
        display: flex;
        flex-direction: column;
        justify-content: center;
        overflow: hidden;
        border: 1px solid var(--customer-preview-border);
        border-radius:
            max(
                10px,
                calc(var(--customer-preview-radius) * .7)
            );
        background: var(--customer-preview-card);
    }

    .customer-display-preview-orders article::before {
        content: "";
        position: absolute;
        inset-block: 0;
        inset-inline-start: 0;
        width: 4px;
        background: var(--customer-preview-preparing);
    }

    .customer-display-preview-column.ready
    .customer-display-preview-orders article::before {
        background: var(--customer-preview-ready);
    }

    .customer-display-preview-column.ready
    .customer-display-preview-orders article {
        background:
            linear-gradient(
                135deg,
                color-mix(
                    in srgb,
                    var(--customer-preview-ready) 12%,
                    var(--customer-preview-card)
                ),
                var(--customer-preview-card) 65%
            );
    }

    .customer-display-preview-orders b {
        direction: ltr;
        color: var(--customer-preview-text);
        font-size: var(--customer-preview-number-size);
        line-height: 1;
        letter-spacing: .03em;
    }

    .customer-display-preview-orders strong {
        margin-top: .35rem;
        color: var(--customer-preview-preparing);
        font-size: .64rem;
    }

    .customer-display-preview-column.ready
    .customer-display-preview-orders strong {
        color: var(--customer-preview-ready);
    }

    .customer-display-preview-orders small {
        margin-top: .25rem;
        color: var(--customer-preview-muted);
        font-size: .54rem;
    }

    .customer-display-preview-footer {
        min-height: 34px;
        padding: .4rem .75rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        border-top: 1px solid var(--customer-preview-border);
        color: var(--customer-preview-muted);
        font-size: .55rem;
    }

    .customer-display-preview-footer span:first-child {
        color: var(--customer-preview-ready);
    }

    .customer-display-preview-note {
        margin-bottom: 1rem;
        padding: .7rem .8rem;
        color: var(--text-muted);
        background:
            color-mix(
                in srgb,
                var(--gold, #C98516) 6%,
                transparent
            );
        border: 1px solid
            color-mix(
                in srgb,
                var(--gold, #C98516) 16%,
                var(--border)
            );
        border-radius: 10px;
        font-size: .72rem;
        line-height: 1.7;
    }

    .customer-display-preview-note strong {
        color: var(--text);
    }

    @media(max-width: 760px) {
        .customer-display-preview-board {
            grid-template-columns: 1fr;
        }

        .customer-display-settings-preview {
            min-height: 560px;
        }
    }


    /* =========================================
       Chat theme preview
    ========================================= */

    .chat-theme-preview {
        --chat-preview-background: #F5F6F8;
        --chat-preview-channels: #FFFFFF;
        --chat-preview-channels-head: #FFFFFF;
        --chat-preview-active: #FFF4E8;
        --chat-preview-channel-text: #172435;
        --chat-preview-muted: #687482;
        --chat-preview-head: #FFFFFF;
        --chat-preview-mine: #C98516;
        --chat-preview-mine-text: #FFFFFF;
        --chat-preview-other: #FFFFFF;
        --chat-preview-other-text: #172435;
        --chat-preview-composer: #FFFFFF;
        --chat-preview-input: #FFFFFF;
        --chat-preview-input-text: #172435;
        --chat-preview-border: #DDE2E7;
        --chat-preview-accent: #C98516;
        --chat-preview-send: #C98516;
        --chat-preview-send-text: #FFFFFF;
        --chat-preview-radius: 14px;

        display: grid;
        grid-template-columns: 220px minmax(0, 1fr);
        min-height: 280px;
        margin-bottom: 1.25rem;
        overflow: hidden;
        border: 1px solid var(--chat-preview-border);
        border-radius: 14px;
        background: var(--chat-preview-background);
    }

    .chat-preview-sidebar {
        background: var(--chat-preview-channels);
        border-left: 1px solid var(--chat-preview-border);
    }

    .chat-preview-sidebar-head,
    .chat-preview-head {
        padding: .8rem 1rem;
        background: var(--chat-preview-channels-head);
        border-bottom: 1px solid var(--chat-preview-border);
        color: var(--chat-preview-channel-text);
        font-weight: 800;
        font-size: .78rem;
    }

    .chat-preview-channel {
        margin: .45rem;
        padding: .65rem;
        border-radius: 9px;
        color: var(--chat-preview-channel-text);
    }

    .chat-preview-channel.active {
        background: var(--chat-preview-active);
    }

    .chat-preview-channel strong,
    .chat-preview-channel span {
        display: block;
    }

    .chat-preview-channel strong {
        font-size: .72rem;
    }

    .chat-preview-channel span {
        margin-top: .2rem;
        color: var(--chat-preview-muted);
        font-size: .63rem;
    }

    .chat-preview-main {
        min-width: 0;
        display: grid;
        grid-template-rows: auto minmax(0, 1fr) auto;
        background: var(--chat-preview-background);
    }

    .chat-preview-head {
        background: var(--chat-preview-head);
    }

    .chat-preview-messages {
        display: flex;
        flex-direction: column;
        gap: .65rem;
        padding: 1rem;
    }

    .chat-preview-bubble {
        width: fit-content;
        max-width: 70%;
        padding: .6rem .75rem;
        border: 1px solid var(--chat-preview-border);
        border-radius: var(--chat-preview-radius);
        font-size: .7rem;
    }

    .chat-preview-bubble.other {
        align-self: flex-end;
        background: var(--chat-preview-other);
        color: var(--chat-preview-other-text);
    }

    .chat-preview-bubble.mine {
        align-self: flex-start;
        background: var(--chat-preview-mine);
        color: var(--chat-preview-mine-text);
        border-color: var(--chat-preview-mine);
    }

    .chat-preview-composer {
        display: flex;
        gap: .5rem;
        padding: .65rem;
        background: var(--chat-preview-composer);
        border-top: 1px solid var(--chat-preview-border);
    }

    .chat-preview-input {
        flex: 1;
        padding: .55rem .7rem;
        background: var(--chat-preview-input);
        color: var(--chat-preview-input-text);
        border: 1px solid var(--chat-preview-border);
        border-radius: 8px;
        font-size: .66rem;
    }

    .chat-preview-send {
        width: 34px;
        height: 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--chat-preview-send);
        color: var(--chat-preview-send-text);
        border-radius: 8px;
        font-size: .75rem;
    }

    @media(max-width: 700px) {
        .chat-theme-preview {
            grid-template-columns: 1fr;
        }

        .chat-preview-sidebar {
            border-left: 0;
            border-bottom: 1px solid var(--chat-preview-border);
        }
    }

    .theme-actions {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--border);
    }

    .settings-save-bar {
        position: sticky;
        bottom: 12px;
        z-index: 20;
        margin-top: 1rem;
        padding: .85rem 1rem;
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: 0 10px 28px rgba(0, 0, 0, .08);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .settings-save-bar strong,
    .settings-save-bar span {
        display: block;
    }

    .settings-save-bar span {
        color: var(--text-muted);
        font-size: .75rem;
        margin-top: .1rem;
    }

    .settings-save-actions {
        display: flex;
        align-items: center;
        gap: .6rem;
    }

    @media (max-width: 950px) {
        .settings-shell {
            grid-template-columns: 1fr;
        }

        .settings-nav {
            position: static;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .settings-grid {
            grid-template-columns: 1fr;
        }
    }



/* =========================================================
   System Theme Presets
========================================================= */

.system-theme-preset-section {
    margin-bottom: 1rem;
    padding: 1rem;
    background:
        linear-gradient(
            180deg,
            color-mix(in srgb, var(--surface, #fff) 96%, var(--gold, #C98516) 4%),
            var(--surface, #fff)
        );
    border: 1px solid var(--border);
    border-radius: 18px;
}

.system-theme-preset-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}

.system-theme-preset-head strong {
    display: block;
    color: var(--text);
    font-size: .98rem;
}

.system-theme-preset-head span:not(.system-theme-preset-status) {
    display: block;
    margin-top: .28rem;
    color: var(--text-muted);
    font-size: .78rem;
}

.system-theme-preset-status {
    flex: 0 0 auto;
    padding: .35rem .7rem;
    color: var(--gold, #C98516);
    background:
        color-mix(
            in srgb,
            var(--gold, #C98516) 10%,
            transparent
        );
    border: 1px solid
        color-mix(
            in srgb,
            var(--gold, #C98516) 24%,
            transparent
        );
    border-radius: 999px;
    font-size: .7rem;
    font-weight: 800;
}

.system-theme-preset-grid {
    display: grid;
    grid-template-columns:
        repeat(auto-fit, minmax(190px, 1fr));
    gap: .8rem;
    margin-top: .9rem;
}

.system-theme-preset-card {
    min-width: 0;
    padding: .8rem;
    overflow: hidden;
    color: var(--text);
    background: var(--surface, #fff);
    border: 1px solid var(--border);
    border-radius: 16px;
    text-align: right;
    cursor: pointer;
    transition:
        transform .18s ease,
        border-color .18s ease,
        box-shadow .18s ease;
}

.system-theme-preset-card:hover {
    transform: translateY(-2px);
    border-color:
        color-mix(
            in srgb,
            var(--gold, #C98516) 48%,
            var(--border)
        );
    box-shadow: 0 12px 28px rgba(15, 23, 42, .08);
}

.system-theme-preset-card.active {
    border-color: var(--gold, #C98516);
    box-shadow:
        0 0 0 3px
        color-mix(
            in srgb,
            var(--gold, #C98516) 14%,
            transparent
        );
}

.system-theme-preset-card > strong,
.system-theme-preset-card > small {
    display: block;
}

.system-theme-preset-card > strong {
    margin-top: .65rem;
    font-size: .84rem;
}

.system-theme-preset-card > small {
    min-height: 34px;
    margin-top: .2rem;
    color: var(--text-muted);
    font-size: .69rem;
    line-height: 1.55;
}

.system-theme-preset-preview {
    height: 78px;
    display: grid;
    grid-template-columns: 31% 69%;
    overflow: hidden;
    border: 1px solid rgba(15, 23, 42, .08);
    border-radius: 11px;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,.08);
}

.system-theme-mini-sidebar {
    display: block;
    background: var(--mini-sidebar);
}

.system-theme-mini-main {
    position: relative;
    display: block;
    padding: 9px;
    background: var(--mini-surface);
}

.system-theme-mini-main::before {
    content: "";
    position: absolute;
    inset: 0 0 auto;
    height: 15px;
    background:
        color-mix(
            in srgb,
            var(--mini-surface) 90%,
            #000 10%
        );
    border-bottom: 1px solid rgba(15,23,42,.06);
}

.system-theme-mini-main i,
.system-theme-mini-main b,
.system-theme-mini-main em {
    position: relative;
    z-index: 1;
    display: block;
    border-radius: 4px;
}

.system-theme-mini-main i {
    width: 82%;
    height: 22px;
    margin-top: 16px;
    background: #fff;
    border: 1px solid rgba(15,23,42,.08);
}

.system-theme-mini-main b {
    width: 64%;
    height: 8px;
    margin-top: 6px;
    background:
        color-mix(
            in srgb,
            var(--mini-accent) 68%,
            transparent
        );
}

.system-theme-mini-main em {
    width: 38%;
    height: 10px;
    margin-top: 5px;
    background: var(--mini-accent);
}

.system-theme-swatches {
    display: flex;
    gap: .35rem;
    margin-top: .6rem;
}

.system-theme-swatches i {
    width: 20px;
    height: 20px;
    display: inline-block;
    background: var(--sw);
    border: 1px solid rgba(15,23,42,.09);
    border-radius: 50%;
}

.system-theme-preset-note {
    margin-top: .75rem;
    padding: .65rem .75rem;
    color: var(--text-muted);
    background:
        color-mix(
            in srgb,
            var(--gold, #C98516) 5%,
            transparent
        );
    border-radius: 10px;
    font-size: .7rem;
}

.system-theme-preset-note strong,
.system-theme-preset-note b {
    color: var(--text);
}

@media (max-width: 700px) {
    .system-theme-preset-head {
        flex-direction: column;
    }

    .system-theme-preset-grid {
        grid-template-columns: 1fr;
    }
}

.chat-preset-section {
    margin-bottom: 1rem;
    padding: 1rem;
    background: linear-gradient(180deg, rgba(255,255,255,.92), rgba(248,250,252,.98));
    border: 1px solid var(--border);
    border-radius: 18px;
}

.chat-preset-head strong {
    display: block;
    color: var(--text);
    font-size: .96rem;
}

.chat-preset-head span {
    display: block;
    margin-top: .28rem;
    color: var(--text-muted);
    font-size: .78rem;
}

.chat-preset-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: .8rem;
    margin-top: .9rem;
}

.chat-preset-card {
    text-align: right;
    padding: .9rem;
    border: 1px solid var(--border);
    background: #fff;
    border-radius: 16px;
    cursor: pointer;
    transition: .18s ease;
}

.chat-preset-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 24px rgba(15,23,42,.06);
}

.chat-preset-card.active {
    border-color: var(--gold-deep);
    box-shadow: 0 0 0 3px rgba(201,133,22,.12);
}

.chat-preset-card strong,
.chat-preset-card small {
    display: block;
}

.chat-preset-card strong {
    color: var(--text);
    margin-top: .55rem;
    font-size: .84rem;
}

.chat-preset-card small {
    color: var(--text-muted);
    margin-top: .2rem;
    font-size: .72rem;
}

.chat-preset-swatches {
    display: flex;
    gap: .35rem;
}

.chat-preset-swatches i {
    width: 28px;
    height: 28px;
    display: inline-block;
    border-radius: 50%;
    background: var(--sw);
    border: 1px solid rgba(15,23,42,.08);
}

</style>


<script>
document.addEventListener('DOMContentLoaded', function () {

    const navButtons = document.querySelectorAll('[data-settings-target]');
    const sections = document.querySelectorAll('.settings-section');

    navButtons.forEach(function (button) {
        button.addEventListener('click', function () {

            navButtons.forEach(btn => btn.classList.remove('active'));
            sections.forEach(section => section.classList.remove('active'));

            button.classList.add('active');

            const target = document.getElementById(
                button.dataset.settingsTarget
            );

            if (target) {
                target.classList.add('active');
            }
        });
    });


    /*
    |--------------------------------------------------------------------------
    | Image preview
    |--------------------------------------------------------------------------
    */
    document.querySelectorAll('.brand-image-input').forEach(function (input) {

        input.addEventListener('change', function () {

            const file = input.files && input.files[0];

            if (!file) {
                return;
            }

            const preview = document.getElementById(
                input.dataset.preview
            );

            if (!preview) {
                return;
            }

            const reader = new FileReader();

            reader.onload = function (event) {

                preview.src = event.target.result;
                preview.style.display = 'block';

                const wrap = preview.closest('.brand-current-image');

                if (wrap) {
                    wrap.classList.remove('empty');

                    const emptyText = wrap.querySelector('span');

                    if (emptyText) {
                        emptyText.style.display = 'none';
                    }
                }

                if (
                    input.name
                    ===
                    'customer_display_background_image'
                ) {
                    const customerPreview =
                        document.getElementById(
                            'customer-display-settings-preview'
                        );

                    if (customerPreview) {
                        customerPreview.style.setProperty(
                            '--customer-preview-bg-image',
                            'url("' + event.target.result + '")'
                        );
                    }
                }
            };

            reader.readAsDataURL(file);
        });
    });


    const themeDefaults = @json($themeDefaults);


    function getThemeValue(key, fallback) {

        const input = document.querySelector(
            '[data-color-key="' + key + '"].color-value'
        );

        return input && input.value
            ? input.value
            : fallback;
    }



    function getSettingValue(key, fallback) {

        const input = document.getElementById(
            'setting_' + key
        );

        return input && input.value !== ''
            ? input.value
            : fallback;
    }



    function getCustomerDisplayValue(
        key,
        fallback
    ) {
        const field =
            document.getElementById(
                'setting_' + key
            );

        if (!field) {
            return fallback;
        }

        if (
            field.type === 'radio'
        ) {
            const checked =
                document.querySelector(
                    'input[name="' + key + '"]:checked'
                );

            return checked
                ? checked.value
                : fallback;
        }

        return field.value !== ''
            ? field.value
            : fallback;
    }


    function updateCustomerDisplayPreview() {
        const preview =
            document.getElementById(
                'customer-display-settings-preview'
            );

        if (!preview) {
            return;
        }

        const customerDisplayDefaults =
            @json($customerDisplayDefaults);

        const color = (
            key,
            fallback
        ) => {
            return getThemeValue(
                key,
                customerDisplayDefaults[key]
                || fallback
            );
        };

        const integer = (
            key,
            fallback,
            min,
            max
        ) => {
            let value =
                Number(
                    getCustomerDisplayValue(
                        key,
                        fallback
                    )
                );

            if (!Number.isFinite(value)) {
                value = fallback;
            }

            return Math.max(
                min,
                Math.min(
                    max,
                    value
                )
            );
        };

        preview.style.setProperty(
            '--customer-preview-background',
            color(
                'customer_display_background_color',
                '#090909'
            )
        );

        preview.style.setProperty(
            '--customer-preview-overlay',
            color(
                'customer_display_overlay_color',
                '#000000'
            )
        );

        preview.style.setProperty(
            '--customer-preview-header',
            color(
                'customer_display_header_bg',
                '#090909'
            )
        );

        preview.style.setProperty(
            '--customer-preview-panel',
            color(
                'customer_display_panel_bg',
                '#111111'
            )
        );

        preview.style.setProperty(
            '--customer-preview-card',
            color(
                'customer_display_card_bg',
                '#181818'
            )
        );

        preview.style.setProperty(
            '--customer-preview-text',
            color(
                'customer_display_text_color',
                '#FFFFFF'
            )
        );

        preview.style.setProperty(
            '--customer-preview-muted',
            color(
                'customer_display_muted_color',
                '#A3A3A3'
            )
        );

        preview.style.setProperty(
            '--customer-preview-preparing',
            color(
                'customer_display_preparing_color',
                '#F0B429'
            )
        );

        preview.style.setProperty(
            '--customer-preview-ready',
            color(
                'customer_display_ready_color',
                '#24C36B'
            )
        );

        preview.style.setProperty(
            '--customer-preview-accent',
            color(
                'customer_display_accent_color',
                '#D7A51D'
            )
        );

        preview.style.setProperty(
            '--customer-preview-border',
            color(
                'customer_display_border_color',
                '#2A2A2A'
            )
        );

        preview.style.setProperty(
            '--customer-preview-overlay-opacity',
            (
                integer(
                    'customer_display_overlay_opacity',
                    72,
                    0,
                    95
                ) / 100
            ).toFixed(2)
        );

        preview.style.setProperty(
            '--customer-preview-panel-opacity',
            integer(
                'customer_display_panel_opacity',
                92,
                35,
                100
            ) + '%'
        );

        preview.style.setProperty(
            '--customer-preview-blur',
            integer(
                'customer_display_glass_blur',
                8,
                0,
                30
            ) + 'px'
        );

        preview.style.setProperty(
            '--customer-preview-radius',
            integer(
                'customer_display_radius',
                22,
                0,
                40
            ) + 'px'
        );

        preview.style.setProperty(
            '--customer-preview-logo-size',
            Math.min(
                68,
                integer(
                    'customer_display_logo_size',
                    54,
                    32,
                    120
                )
            ) + 'px'
        );

        /*
         * المعاينة أصغر من شاشة التلفزيون الفعلية،
         * لذلك نحجّم رقم الطلب نسبياً داخل Preview فقط.
         */
        const realNumberSize =
            integer(
                'customer_display_order_number_size',
                70,
                36,
                120
            );

        preview.style.setProperty(
            '--customer-preview-number-size',
            Math.max(
                26,
                Math.min(
                    52,
                    realNumberSize * .56
                )
            ) + 'px'
        );
    }


    function updateChatThemePreview() {

        const preview =
            document.getElementById(
                'chat-theme-preview'
            );

        if (!preview) {
            return;
        }

        const map = {
            '--chat-preview-background':
                getThemeValue('chat_background_color', '#F5F6F8'),

            '--chat-preview-channels':
                getThemeValue('chat_channels_bg', '#FFFFFF'),

            '--chat-preview-channels-head':
                getThemeValue('chat_channels_header_bg', '#FFFFFF'),

            '--chat-preview-active':
                getThemeValue('chat_channel_active_bg', '#FFF4E8'),

            '--chat-preview-channel-text':
                getThemeValue('chat_channel_text', '#172435'),

            '--chat-preview-muted':
                getThemeValue('chat_channel_muted', '#687482'),

            '--chat-preview-head':
                getThemeValue('chat_conversation_header_bg', '#FFFFFF'),

            '--chat-preview-mine':
                getThemeValue('chat_message_mine_bg', '#C98516'),

            '--chat-preview-mine-text':
                getThemeValue('chat_message_mine_text', '#FFFFFF'),

            '--chat-preview-other':
                getThemeValue('chat_message_other_bg', '#FFFFFF'),

            '--chat-preview-other-text':
                getThemeValue('chat_message_other_text', '#172435'),

            '--chat-preview-composer':
                getThemeValue('chat_composer_bg', '#FFFFFF'),

            '--chat-preview-input':
                getThemeValue('chat_input_bg', '#FFFFFF'),

            '--chat-preview-input-text':
                getThemeValue('chat_input_text', '#172435'),

            '--chat-preview-border':
                getThemeValue('chat_border', '#DDE2E7'),

            '--chat-preview-accent':
                getThemeValue('chat_accent', '#C98516'),

            '--chat-preview-send':
                getThemeValue('chat_send_button_bg', '#C98516'),

            '--chat-preview-send-text':
                getThemeValue('chat_send_button_text', '#FFFFFF'),

            '--chat-preview-radius':
                (
                    getSettingValue(
                        'chat_bubble_radius',
                        '14'
                    )
                ) + 'px',
        };

        Object.entries(map).forEach(
            ([key, value]) => {
                preview.style.setProperty(
                    key,
                    value
                );
            }
        );
    }


    function updateThemePreview() {

        const preview = document.getElementById('theme-preview');

        if (!preview) {
            return;
        }

        preview.style.setProperty(
            '--theme-preview-bg',
            getThemeValue('theme_background', '#F5F6F8')
        );

        preview.style.setProperty(
            '--theme-preview-surface',
            getThemeValue('theme_surface', '#FFFFFF')
        );

        preview.style.setProperty(
            '--theme-preview-text',
            getThemeValue('theme_text', '#172435')
        );

        preview.style.setProperty(
            '--theme-preview-muted',
            getThemeValue('theme_text_muted', '#687482')
        );

        preview.style.setProperty(
            '--theme-preview-border',
            getThemeValue('theme_border', '#DDE2E7')
        );

        preview.style.setProperty(
            '--theme-preview-sidebar',
            getThemeValue('theme_sidebar_bg', '#0A2948')
        );

        preview.style.setProperty(
            '--theme-preview-sidebar-text',
            getThemeValue('theme_sidebar_text', '#FFFFFF')
        );

        preview.style.setProperty(
            '--theme-preview-active',
            getThemeValue('theme_sidebar_active', '#C98516')
        );

        preview.style.setProperty(
            '--theme-preview-footer-bg',
            getThemeValue('theme_sidebar_footer_bg', '#FFFFFF')
        );

        preview.style.setProperty(
            '--theme-preview-footer-text',
            getThemeValue('theme_sidebar_footer_text', '#172435')
        );

        preview.style.setProperty(
            '--theme-preview-footer-muted',
            getThemeValue('theme_sidebar_footer_muted', '#687482')
        );

        preview.style.setProperty(
            '--theme-preview-footer-border',
            getThemeValue('theme_sidebar_footer_border', '#DDE2E7')
        );

        preview.style.setProperty(
            '--theme-preview-footer-icon',
            getThemeValue('theme_sidebar_footer_icon', '#687482')
        );

        preview.style.setProperty(
            '--theme-preview-header',
            getThemeValue('theme_header_bg', '#FFFFFF')
        );

        preview.style.setProperty(
            '--theme-preview-accent',
            getThemeValue('theme_accent', '#C98516')
        );

        const radiusInput = document.getElementById(
            'setting_theme_radius'
        );

        preview.style.setProperty(
            '--theme-preview-radius',
            (radiusInput?.value || 10) + 'px'
        );
    }


    document.querySelectorAll('[data-color-key]').forEach(function (input) {

        input.addEventListener('input', function () {

            const key = input.dataset.colorKey;

            const picker = document.querySelector(
                '.color-picker[data-color-key="' + key + '"]'
            );

            const textInput = document.querySelector(
                '.color-value[data-color-key="' + key + '"]'
            );

            if (input.classList.contains('color-picker')) {

                if (textInput) {
                    textInput.value = input.value.toUpperCase();
                }

            } else {

                if (
                    picker
                    &&
                    /^#[0-9A-Fa-f]{6}$/.test(input.value)
                ) {
                    picker.value = input.value;
                }
            }

            updateThemePreview();
            updateChatThemePreview();
            updateCustomerDisplayPreview();
        });
    });


    const radiusInput = document.getElementById(
        'setting_theme_radius'
    );

    if (radiusInput) {
        radiusInput.addEventListener(
            'input',
            updateThemePreview
        );
    }


    const chatBubbleRadiusInput =
        document.getElementById(
            'setting_chat_bubble_radius'
        );

    if (chatBubbleRadiusInput) {
        chatBubbleRadiusInput.addEventListener(
            'input',
            updateChatThemePreview
        );
    }

    const chatOverlayInput =
        document.getElementById(
            'setting_chat_background_overlay'
        );

    if (chatOverlayInput) {
        chatOverlayInput.addEventListener(
            'input',
            updateChatThemePreview
        );
    }



    [
        'customer_display_overlay_opacity',
        'customer_display_panel_opacity',
        'customer_display_glass_blur',
        'customer_display_radius',
        'customer_display_logo_size',
        'customer_display_order_number_size',
    ].forEach(function (key) {
        const field =
            document.getElementById(
                'setting_' + key
            );

        if (field) {
            field.addEventListener(
                'input',
                updateCustomerDisplayPreview
            );

            field.addEventListener(
                'change',
                updateCustomerDisplayPreview
            );
        }
    });


    [
        'customer_display_show_service_type',
        'customer_display_show_table',
        'customer_display_show_clock',
    ].forEach(function (key) {
        document
            .querySelectorAll(
                'input[name="' + key + '"]'
            )
            .forEach(function (field) {
                field.addEventListener(
                    'change',
                    updateCustomerDisplayPreview
                );
            });
    });


    const resetButton = document.getElementById(
        'reset-theme-defaults'
    );

    if (resetButton) {

        resetButton.addEventListener('click', function () {

            Object.entries(themeDefaults).forEach(
                function ([key, value]) {

                    const picker = document.querySelector(
                        '.color-picker[data-color-key="' + key + '"]'
                    );

                    const textInput = document.querySelector(
                        '.color-value[data-color-key="' + key + '"]'
                    );

                    if (picker) {
                        picker.value = value;
                    }

                    if (textInput) {
                        textInput.value = value;
                    }
                }
            );

            if (radiusInput) {
                radiusInput.value = 10;
            }

            setSystemThemePresetState(
                'dahab-gold'
            );

            updateThemePreview();
        });
    }


    updateThemePreview();
    updateChatThemePreview();
    updateCustomerDisplayPreview();
});



/* =========================================================
   System Theme Presets
========================================================= */

const systemThemePresetMap = {
    'dahab-gold': {
        theme_primary: '#0A2948',
        theme_secondary: '#C98516',
        theme_accent: '#C98516',
        theme_background: '#F5F6F8',
        theme_surface: '#FFFFFF',
        theme_text: '#172435',
        theme_text_muted: '#687482',
        theme_border: '#DDE2E7',
        theme_sidebar_bg: '#0A2948',
        theme_sidebar_text: '#FFFFFF',
        theme_sidebar_active: '#C98516',
        theme_sidebar_footer_bg: '#FFFFFF',
        theme_sidebar_footer_text: '#172435',
        theme_sidebar_footer_muted: '#687482',
        theme_sidebar_footer_border: '#DDE2E7',
        theme_sidebar_footer_icon: '#687482',
        theme_header_bg: '#FFFFFF',
        theme_success: '#197438',
        theme_warning: '#C98516',
        theme_danger: '#E22929',
        theme_info: '#2F72C4',
        theme_radius: '10',
    },

    'midnight': {
        theme_primary: '#0B1220',
        theme_secondary: '#334155',
        theme_accent: '#38BDF8',
        theme_background: '#0B1220',
        theme_surface: '#111827',
        theme_text: '#E5EEF8',
        theme_text_muted: '#94A3B8',
        theme_border: '#263247',
        theme_sidebar_bg: '#070D18',
        theme_sidebar_text: '#E5EEF8',
        theme_sidebar_active: '#38BDF8',
        theme_sidebar_footer_bg: '#0F172A',
        theme_sidebar_footer_text: '#E5EEF8',
        theme_sidebar_footer_muted: '#94A3B8',
        theme_sidebar_footer_border: '#263247',
        theme_sidebar_footer_icon: '#94A3B8',
        theme_header_bg: '#111827',
        theme_success: '#22C55E',
        theme_warning: '#F59E0B',
        theme_danger: '#F43F5E',
        theme_info: '#38BDF8',
        theme_radius: '12',
    },

    'emerald': {
        theme_primary: '#064E3B',
        theme_secondary: '#047857',
        theme_accent: '#10B981',
        theme_background: '#F3F8F6',
        theme_surface: '#FFFFFF',
        theme_text: '#163129',
        theme_text_muted: '#65766F',
        theme_border: '#D5E3DD',
        theme_sidebar_bg: '#064E3B',
        theme_sidebar_text: '#ECFDF5',
        theme_sidebar_active: '#6EE7B7',
        theme_sidebar_footer_bg: '#FFFFFF',
        theme_sidebar_footer_text: '#163129',
        theme_sidebar_footer_muted: '#65766F',
        theme_sidebar_footer_border: '#D5E3DD',
        theme_sidebar_footer_icon: '#65766F',
        theme_header_bg: '#FFFFFF',
        theme_success: '#15803D',
        theme_warning: '#D97706',
        theme_danger: '#DC2626',
        theme_info: '#0284C7',
        theme_radius: '12',
    },

    'ocean': {
        theme_primary: '#0F3D66',
        theme_secondary: '#1E5B8F',
        theme_accent: '#2F80ED',
        theme_background: '#F5F9FF',
        theme_surface: '#FFFFFF',
        theme_text: '#16324A',
        theme_text_muted: '#667A8C',
        theme_border: '#D5E2EE',
        theme_sidebar_bg: '#0F3D66',
        theme_sidebar_text: '#F3F8FF',
        theme_sidebar_active: '#65B5FF',
        theme_sidebar_footer_bg: '#FFFFFF',
        theme_sidebar_footer_text: '#16324A',
        theme_sidebar_footer_muted: '#667A8C',
        theme_sidebar_footer_border: '#D5E2EE',
        theme_sidebar_footer_icon: '#667A8C',
        theme_header_bg: '#FFFFFF',
        theme_success: '#14804A',
        theme_warning: '#D97706',
        theme_danger: '#D92D20',
        theme_info: '#2F80ED',
        theme_radius: '12',
    },

    'rose': {
        theme_primary: '#881337',
        theme_secondary: '#BE123C',
        theme_accent: '#E11D48',
        theme_background: '#FFF7F8',
        theme_surface: '#FFFFFF',
        theme_text: '#3A1B26',
        theme_text_muted: '#7A6870',
        theme_border: '#F0D8DF',
        theme_sidebar_bg: '#881337',
        theme_sidebar_text: '#FFF1F2',
        theme_sidebar_active: '#FDA4AF',
        theme_sidebar_footer_bg: '#FFFFFF',
        theme_sidebar_footer_text: '#3A1B26',
        theme_sidebar_footer_muted: '#7A6870',
        theme_sidebar_footer_border: '#F0D8DF',
        theme_sidebar_footer_icon: '#7A6870',
        theme_header_bg: '#FFFFFF',
        theme_success: '#16803B',
        theme_warning: '#D97706',
        theme_danger: '#BE123C',
        theme_info: '#2563EB',
        theme_radius: '14',
    },

    'graphite': {
        theme_primary: '#1F2937',
        theme_secondary: '#374151',
        theme_accent: '#F59E0B',
        theme_background: '#F3F4F6',
        theme_surface: '#FFFFFF',
        theme_text: '#111827',
        theme_text_muted: '#6B7280',
        theme_border: '#D1D5DB',
        theme_sidebar_bg: '#1F2937',
        theme_sidebar_text: '#F9FAFB',
        theme_sidebar_active: '#FBBF24',
        theme_sidebar_footer_bg: '#FFFFFF',
        theme_sidebar_footer_text: '#111827',
        theme_sidebar_footer_muted: '#6B7280',
        theme_sidebar_footer_border: '#D1D5DB',
        theme_sidebar_footer_icon: '#6B7280',
        theme_header_bg: '#FFFFFF',
        theme_success: '#15803D',
        theme_warning: '#D97706',
        theme_danger: '#DC2626',
        theme_info: '#2563EB',
        theme_radius: '9',
    },
};

const systemThemePresetNames = {
    'dahab-gold': 'Dahab Gold',
    'midnight': 'Midnight',
    'emerald': 'Emerald',
    'ocean': 'Ocean Blue',
    'rose': 'Rose',
    'graphite': 'Graphite',
    'custom': 'مخصص',
};

let applyingSystemThemePreset = false;

function setSystemThemePresetState(preset) {
    const presetInput =
        document.getElementById(
            'setting_system_theme_preset'
        );

    if (presetInput) {
        presetInput.value = preset;
    }

    document
        .querySelectorAll(
            '[data-system-theme-preset]'
        )
        .forEach(
            (button) => {
                button.classList.toggle(
                    'active',
                    button.dataset.systemThemePreset
                        === preset
                );
            }
        );

    const status =
        document.getElementById(
            'systemThemePresetStatus'
        );

    if (status) {
        status.textContent =
            systemThemePresetNames[preset]
            || 'مخصص';
    }
}

function setThemeFieldValue(key, value) {
    const field =
        document.getElementById(
            `setting_${key}`
        );

    if (!field) {
        return;
    }

    field.value = value;

    const picker =
        document.querySelector(
            `.color-picker[data-color-key="${key}"]`
        );

    const textInput =
        document.querySelector(
            `.color-value[data-color-key="${key}"]`
        );

    if (
        /^#[0-9A-Fa-f]{6}$/.test(value)
    ) {
        if (picker) {
            picker.value = value;
        }

        if (textInput) {
            textInput.value =
                value.toUpperCase();
        }
    }

    field.dispatchEvent(
        new Event(
            'input',
            {
                bubbles: true,
            }
        )
    );

    field.dispatchEvent(
        new Event(
            'change',
            {
                bubbles: true,
            }
        )
    );
}

function applySystemThemePreset(preset) {
    const values =
        systemThemePresetMap[preset];

    if (!values) {
        return;
    }

    applyingSystemThemePreset =
        true;

    Object
        .entries(values)
        .forEach(
            ([key, value]) =>
                setThemeFieldValue(
                    key,
                    value
                )
        );

    applyingSystemThemePreset =
        false;

    setSystemThemePresetState(
        preset
    );

    if (
        typeof updateThemePreview
        === 'function'
    ) {
        updateThemePreview();
    }
}

document
    .querySelectorAll(
        '[data-system-theme-preset]'
    )
    .forEach(
        (button) => {
            button.addEventListener(
                'click',
                () => {
                    applySystemThemePreset(
                        button
                            .dataset
                            .systemThemePreset
                    );
                }
            );
        }
    );

/*
 * إذا عدّل المستخدم أي قيمة ثيم يدويًا،
 * نحتفظ بالقيم ونحوّل الحالة إلى "مخصص".
 */
document
    .querySelectorAll(
        '[data-color-key^="theme_"], #setting_theme_radius, #setting_theme_font_family'
    )
    .forEach(
        (field) => {
            field.addEventListener(
                'input',
                () => {
                    if (
                        !applyingSystemThemePreset
                    ) {
                        setSystemThemePresetState(
                            'custom'
                        );
                    }
                }
            );

            field.addEventListener(
                'change',
                () => {
                    if (
                        !applyingSystemThemePreset
                    ) {
                        setSystemThemePresetState(
                            'custom'
                        );
                    }
                }
            );
        }
    );


const chatPresetMap = {
    'whatsapp-soft': {
        chat_background_color: '#EAF7EF',
        chat_background_overlay: '8',
        chat_channels_bg: '#FFFFFF',
        chat_channels_header_bg: '#F8FAFC',
        chat_channel_active_bg: '#EAF8F1',
        chat_channel_text: '#122033',
        chat_channel_muted: '#6B7280',
        chat_conversation_header_bg: '#FDFEFE',
        chat_message_mine_bg: '#DCF8C6',
        chat_message_mine_text: '#12301F',
        chat_message_other_bg: '#FFFFFF',
        chat_message_other_text: '#172435',
        chat_composer_bg: '#F8FAFC',
        chat_input_bg: '#FFFFFF',
        chat_input_text: '#172435',
        chat_border: '#DDE5E8',
        chat_accent: '#25D366',
        chat_send_button_bg: '#25D366',
        chat_send_button_text: '#FFFFFF',
        chat_unread_badge_bg: '#25D366',
        chat_unread_badge_text: '#FFFFFF',
        chat_bubble_radius: '18',
    },
    'emerald': {
        chat_background_color: '#ECFDF5',
        chat_background_overlay: '10',
        chat_channels_bg: '#FFFFFF',
        chat_channels_header_bg: '#F8FAFC',
        chat_channel_active_bg: '#DFF7EA',
        chat_channel_text: '#0F172A',
        chat_channel_muted: '#64748B',
        chat_conversation_header_bg: '#FFFFFF',
        chat_message_mine_bg: '#10B981',
        chat_message_mine_text: '#FFFFFF',
        chat_message_other_bg: '#FFFFFF',
        chat_message_other_text: '#172435',
        chat_composer_bg: '#F8FAFC',
        chat_input_bg: '#FFFFFF',
        chat_input_text: '#172435',
        chat_border: '#D7E9DF',
        chat_accent: '#10B981',
        chat_send_button_bg: '#10B981',
        chat_send_button_text: '#FFFFFF',
        chat_unread_badge_bg: '#059669',
        chat_unread_badge_text: '#FFFFFF',
        chat_bubble_radius: '18',
    },
    'midnight': {
        chat_background_color: '#0F172A',
        chat_background_overlay: '20',
        chat_channels_bg: '#0F172A',
        chat_channels_header_bg: '#111827',
        chat_channel_active_bg: '#1E293B',
        chat_channel_text: '#E5EEF8',
        chat_channel_muted: '#94A3B8',
        chat_conversation_header_bg: '#111827',
        chat_message_mine_bg: '#2563EB',
        chat_message_mine_text: '#FFFFFF',
        chat_message_other_bg: '#1E293B',
        chat_message_other_text: '#E5EEF8',
        chat_composer_bg: '#111827',
        chat_input_bg: '#172033',
        chat_input_text: '#F8FAFC',
        chat_border: '#253247',
        chat_accent: '#38BDF8',
        chat_send_button_bg: '#2563EB',
        chat_send_button_text: '#FFFFFF',
        chat_unread_badge_bg: '#38BDF8',
        chat_unread_badge_text: '#0F172A',
        chat_bubble_radius: '18',
    },
    'rose': {
        chat_background_color: '#FFF1F2',
        chat_background_overlay: '8',
        chat_channels_bg: '#FFFFFF',
        chat_channels_header_bg: '#FFF7F8',
        chat_channel_active_bg: '#FFE4E6',
        chat_channel_text: '#172435',
        chat_channel_muted: '#7A6870',
        chat_conversation_header_bg: '#FFFFFF',
        chat_message_mine_bg: '#E11D48',
        chat_message_mine_text: '#FFFFFF',
        chat_message_other_bg: '#FFFFFF',
        chat_message_other_text: '#172435',
        chat_composer_bg: '#FFF7F8',
        chat_input_bg: '#FFFFFF',
        chat_input_text: '#172435',
        chat_border: '#F4D7DE',
        chat_accent: '#E11D48',
        chat_send_button_bg: '#E11D48',
        chat_send_button_text: '#FFFFFF',
        chat_unread_badge_bg: '#BE123C',
        chat_unread_badge_text: '#FFFFFF',
        chat_bubble_radius: '18',
    }
};

function applyChatPreset(preset) {
    const values = chatPresetMap[preset];
    if (!values) return;

    Object.entries(values).forEach(([key, value]) => {
        const field = document.getElementById(`setting_${key}`);
        if (!field) return;
        field.value = value;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    });

    const presetInput = document.getElementById('setting_chat_theme_preset');
    if (presetInput) presetInput.value = preset;

    document.querySelectorAll('[data-chat-preset]').forEach((button) => {
        button.classList.toggle('active', button.dataset.chatPreset === preset);
    });
}

document.querySelectorAll('[data-chat-preset]').forEach((button) => {
    button.addEventListener('click', () => applyChatPreset(button.dataset.chatPreset));
});

</script>

@endsection
