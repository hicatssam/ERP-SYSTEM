<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class BrandingThemeSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // ─────────────────────────────────────────────────────────────
            // Branding
            // ─────────────────────────────────────────────────────────────
            [
                'key' => 'system_name_en',
                'value' => 'Dahab Sweets',
                'type' => 'string',
                'group' => 'branding',
                'label' => 'اسم العلامة بالإنجليزية',
                'description' => 'يظهر في الهوية والفوتر والتقارير.',
            ],
            [
                'key' => 'brand_tagline_ar',
                'value' => '',
                'type' => 'string',
                'group' => 'branding',
                'label' => 'الشعار النصي بالعربية',
                'description' => 'عبارة قصيرة أسفل اسم العلامة.',
            ],
            [
                'key' => 'brand_tagline_en',
                'value' => '',
                'type' => 'string',
                'group' => 'branding',
                'label' => 'الشعار النصي بالإنجليزية',
                'description' => 'عبارة قصيرة أسفل اسم العلامة.',
            ],
            [
                'key' => 'brand_footer_text',
                'value' => 'حلويات دهب - Dahab Sweets',
                'type' => 'string',
                'group' => 'branding',
                'label' => 'نص الفوتر',
                'description' => 'النص الافتراضي أسفل النظام والتقارير.',
            ],

            ['key' => 'brand_logo', 'value' => '', 'type' => 'image', 'group' => 'branding', 'label' => 'الشعار الرئيسي', 'description' => 'يظهر في الشريط الجانبي والهيدر.'],
            ['key' => 'brand_logo_small', 'value' => '', 'type' => 'image', 'group' => 'branding', 'label' => 'الشعار المصغّر', 'description' => 'للأيقونات والمساحات الصغيرة.'],
            ['key' => 'brand_favicon', 'value' => '', 'type' => 'image', 'group' => 'branding', 'label' => 'أيقونة المتصفح Favicon', 'description' => 'يفضل صورة مربعة.'],
            ['key' => 'brand_report_logo', 'value' => '', 'type' => 'image', 'group' => 'branding', 'label' => 'شعار التقارير والفواتير', 'description' => 'يستخدم في PDF والطباعة.'],
            ['key' => 'brand_stamp', 'value' => '', 'type' => 'image', 'group' => 'branding', 'label' => 'الختم الرسمي', 'description' => 'PNG بخلفية شفافة يعطي أفضل نتيجة.'],
            ['key' => 'brand_signature', 'value' => '', 'type' => 'image', 'group' => 'branding', 'label' => 'التوقيع المعتمد', 'description' => 'التوقيع المستخدم في التقارير والفواتير.'],
            ['key' => 'brand_login_background', 'value' => '', 'type' => 'image', 'group' => 'branding', 'label' => 'خلفية صفحة تسجيل الدخول', 'description' => 'صورة خلفية اختيارية لصفحة الدخول.'],

            // ─────────────────────────────────────────────────────────────
            // Theme
            // ─────────────────────────────────────────────────────────────
            ['key' => 'theme_primary', 'value' => '#0A2948', 'type' => 'color', 'group' => 'theme', 'label' => 'اللون الرئيسي', 'description' => 'اللون الأساسي للهوية.'],
            ['key' => 'theme_secondary', 'value' => '#C98516', 'type' => 'color', 'group' => 'theme', 'label' => 'اللون الثانوي', 'description' => 'لون ثانوي للواجهة.'],
            ['key' => 'theme_accent', 'value' => '#C98516', 'type' => 'color', 'group' => 'theme', 'label' => 'لون التمييز', 'description' => 'الأزرار والعناصر البارزة.'],
            ['key' => 'theme_background', 'value' => '#F5F6F8', 'type' => 'color', 'group' => 'theme', 'label' => 'خلفية النظام', 'description' => 'الخلفية العامة للصفحات.'],
            ['key' => 'theme_surface', 'value' => '#FFFFFF', 'type' => 'color', 'group' => 'theme', 'label' => 'خلفية البطاقات', 'description' => 'البطاقات والجداول والنوافذ.'],
            ['key' => 'theme_text', 'value' => '#172435', 'type' => 'color', 'group' => 'theme', 'label' => 'لون النص الرئيسي', 'description' => 'النصوص والعناوين الأساسية.'],
            ['key' => 'theme_text_muted', 'value' => '#687482', 'type' => 'color', 'group' => 'theme', 'label' => 'لون النص الثانوي', 'description' => 'الوصف والنصوص الثانوية.'],
            ['key' => 'theme_border', 'value' => '#DDE2E7', 'type' => 'color', 'group' => 'theme', 'label' => 'لون الحدود', 'description' => 'حدود الجداول والحقول والبطاقات.'],
            ['key' => 'theme_sidebar_bg', 'value' => '#0A2948', 'type' => 'color', 'group' => 'theme', 'label' => 'خلفية القائمة الجانبية', 'description' => 'لون Sidebar.'],
            ['key' => 'theme_sidebar_text', 'value' => '#FFFFFF', 'type' => 'color', 'group' => 'theme', 'label' => 'نص القائمة الجانبية', 'description' => 'لون النص والأيقونات في Sidebar.'],
            ['key' => 'theme_sidebar_active', 'value' => '#C98516', 'type' => 'color', 'group' => 'theme', 'label' => 'العنصر النشط في القائمة', 'description' => 'لون العنصر المحدد في Sidebar.'],
            ['key' => 'theme_header_bg', 'value' => '#FFFFFF', 'type' => 'color', 'group' => 'theme', 'label' => 'خلفية الهيدر', 'description' => 'خلفية الشريط العلوي.'],
            ['key' => 'theme_success', 'value' => '#197438', 'type' => 'color', 'group' => 'theme', 'label' => 'لون النجاح', 'description' => 'الحالات الناجحة والمكتملة.'],
            ['key' => 'theme_warning', 'value' => '#C98516', 'type' => 'color', 'group' => 'theme', 'label' => 'لون التحذير', 'description' => 'التنبيهات والحالات المعلقة.'],
            ['key' => 'theme_danger', 'value' => '#E22929', 'type' => 'color', 'group' => 'theme', 'label' => 'لون الخطأ', 'description' => 'الحذف والرفض والأخطاء.'],
            ['key' => 'theme_info', 'value' => '#2F72C4', 'type' => 'color', 'group' => 'theme', 'label' => 'لون المعلومات', 'description' => 'المعلومات والحالات الإرشادية.'],
            ['key' => 'theme_font_family', 'value' => 'Cairo', 'type' => 'select', 'group' => 'theme', 'label' => 'الخط الافتراضي', 'description' => 'الخط المستخدم في النظام.'],
            ['key' => 'theme_radius', 'value' => '10', 'type' => 'integer', 'group' => 'theme', 'label' => 'استدارة الزوايا', 'description' => 'من 0 إلى 30 بكسل.'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        SystemSetting::flushCache();
    }
}