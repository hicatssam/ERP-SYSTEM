<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $settings = [

            /*
            |--------------------------------------------------------------------------
            | الهوية والشعار
            |--------------------------------------------------------------------------
            */

            [
                'key' => 'system_name_en',
                'value' => 'Dahab Sweets',
                'type' => 'string',
                'group' => 'branding',
                'label' => 'اسم العلامة بالإنجليزية',
                'description' => 'الاسم الإنجليزي الذي يظهر في الهوية والتقارير.',
            ],

            [
                'key' => 'brand_tagline_ar',
                'value' => '',
                'type' => 'string',
                'group' => 'branding',
                'label' => 'الشعار النصي بالعربية',
                'description' => 'عبارة قصيرة تظهر أسفل اسم العلامة.',
            ],

            [
                'key' => 'brand_tagline_en',
                'value' => '',
                'type' => 'string',
                'group' => 'branding',
                'label' => 'الشعار النصي بالإنجليزية',
                'description' => 'عبارة قصيرة باللغة الإنجليزية.',
            ],

            [
                'key' => 'brand_footer_text',
                'value' => 'حلويات دهب - Dahab Sweets',
                'type' => 'string',
                'group' => 'branding',
                'label' => 'نص الفوتر',
                'description' => 'النص الافتراضي في أسفل النظام والتقارير.',
            ],

            [
                'key' => 'brand_logo',
                'value' => '',
                'type' => 'image',
                'group' => 'branding',
                'label' => 'الشعار الرئيسي',
                'description' => 'الشعار الرئيسي للنظام والقائمة الجانبية.',
            ],

            [
                'key' => 'brand_logo_small',
                'value' => '',
                'type' => 'image',
                'group' => 'branding',
                'label' => 'الشعار المصغّر',
                'description' => 'شعار صغير للمساحات الضيقة.',
            ],

            [
                'key' => 'brand_favicon',
                'value' => '',
                'type' => 'image',
                'group' => 'branding',
                'label' => 'أيقونة المتصفح',
                'description' => 'Favicon ويفضل أن تكون الصورة مربعة.',
            ],

            [
                'key' => 'brand_report_logo',
                'value' => '',
                'type' => 'image',
                'group' => 'branding',
                'label' => 'شعار التقارير والفواتير',
                'description' => 'الشعار المستخدم في التقارير والطباعة.',
            ],

            [
                'key' => 'brand_stamp',
                'value' => '',
                'type' => 'image',
                'group' => 'branding',
                'label' => 'الختم الرسمي',
                'description' => 'الختم المستخدم في التقارير والفواتير.',
            ],

            [
                'key' => 'brand_signature',
                'value' => '',
                'type' => 'image',
                'group' => 'branding',
                'label' => 'التوقيع المعتمد',
                'description' => 'التوقيع المستخدم في التقارير والفواتير.',
            ],

            [
                'key' => 'brand_login_background',
                'value' => '',
                'type' => 'image',
                'group' => 'branding',
                'label' => 'خلفية تسجيل الدخول',
                'description' => 'صورة خلفية اختيارية لصفحة الدخول.',
            ],


            /*
            |--------------------------------------------------------------------------
            | الثيم والألوان
            |--------------------------------------------------------------------------
            */

            [
                'key' => 'theme_primary',
                'value' => '#0A2948',
                'type' => 'color',
                'group' => 'theme',
                'label' => 'اللون الرئيسي',
                'description' => 'اللون الأساسي للهوية.',
            ],

            [
                'key' => 'theme_secondary',
                'value' => '#C98516',
                'type' => 'color',
                'group' => 'theme',
                'label' => 'اللون الثانوي',
                'description' => 'اللون الثانوي للواجهة.',
            ],

            [
                'key' => 'theme_accent',
                'value' => '#C98516',
                'type' => 'color',
                'group' => 'theme',
                'label' => 'لون التمييز',
                'description' => 'الأزرار والعناصر المهمة.',
            ],

            [
                'key' => 'theme_background',
                'value' => '#F5F6F8',
                'type' => 'color',
                'group' => 'theme',
                'label' => 'خلفية النظام',
                'description' => 'الخلفية العامة للصفحات.',
            ],

            [
                'key' => 'theme_surface',
                'value' => '#FFFFFF',
                'type' => 'color',
                'group' => 'theme',
                'label' => 'خلفية البطاقات',
                'description' => 'لون البطاقات والجداول والنوافذ.',
            ],

            [
                'key' => 'theme_text',
                'value' => '#172435',
                'type' => 'color',
                'group' => 'theme',
                'label' => 'لون النص الرئيسي',
                'description' => 'لون العناوين والنص الأساسي.',
            ],

            [
                'key' => 'theme_text_muted',
                'value' => '#687482',
                'type' => 'color',
                'group' => 'theme',
                'label' => 'لون النص الثانوي',
                'description' => 'لون الوصف والنصوص الثانوية.',
            ],

            [
                'key' => 'theme_border',
                'value' => '#DDE2E7',
                'type' => 'color',
                'group' => 'theme',
                'label' => 'لون الحدود',
                'description' => 'حدود الحقول والجداول والبطاقات.',
            ],

            [
                'key' => 'theme_sidebar_bg',
                'value' => '#0A2948',
                'type' => 'color',
                'group' => 'theme',
                'label' => 'خلفية القائمة الجانبية',
                'description' => 'لون Sidebar.',
            ],

            [
                'key' => 'theme_sidebar_text',
                'value' => '#FFFFFF',
                'type' => 'color',
                'group' => 'theme',
                'label' => 'نص القائمة الجانبية',
                'description' => 'لون النص والأيقونات داخل Sidebar.',
            ],

            [
                'key' => 'theme_sidebar_active',
                'value' => '#C98516',
                'type' => 'color',
                'group' => 'theme',
                'label' => 'العنصر النشط بالقائمة',
                'description' => 'لون العنصر الحالي في Sidebar.',
            ],

            [
                'key' => 'theme_header_bg',
                'value' => '#FFFFFF',
                'type' => 'color',
                'group' => 'theme',
                'label' => 'خلفية الهيدر',
                'description' => 'خلفية الشريط العلوي.',
            ],

            [
                'key' => 'theme_success',
                'value' => '#197438',
                'type' => 'color',
                'group' => 'theme',
                'label' => 'لون النجاح',
                'description' => 'الحالات المكتملة والناجحة.',
            ],

            [
                'key' => 'theme_warning',
                'value' => '#C98516',
                'type' => 'color',
                'group' => 'theme',
                'label' => 'لون التحذير',
                'description' => 'الحالات المعلقة والتنبيهات.',
            ],

            [
                'key' => 'theme_danger',
                'value' => '#E22929',
                'type' => 'color',
                'group' => 'theme',
                'label' => 'لون الخطأ',
                'description' => 'الأخطاء والحذف والرفض.',
            ],

            [
                'key' => 'theme_info',
                'value' => '#2F72C4',
                'type' => 'color',
                'group' => 'theme',
                'label' => 'لون المعلومات',
                'description' => 'الحالات الإرشادية والمعلومات.',
            ],

            [
                'key' => 'theme_font_family',
                'value' => 'Cairo',
                'type' => 'select',
                'group' => 'theme',
                'label' => 'الخط الافتراضي',
                'description' => 'الخط الأساسي في النظام.',
            ],

            [
                'key' => 'theme_radius',
                'value' => '10',
                'type' => 'integer',
                'group' => 'theme',
                'label' => 'استدارة الزوايا',
                'description' => 'قيمة من 0 إلى 30 بكسل.',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge(
                    $setting,
                    [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                )
            );
        }
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->whereIn('key', [
                'system_name_en',
                'brand_tagline_ar',
                'brand_tagline_en',
                'brand_footer_text',
                'brand_logo',
                'brand_logo_small',
                'brand_favicon',
                'brand_report_logo',
                'brand_stamp',
                'brand_signature',
                'brand_login_background',

                'theme_primary',
                'theme_secondary',
                'theme_accent',
                'theme_background',
                'theme_surface',
                'theme_text',
                'theme_text_muted',
                'theme_border',
                'theme_sidebar_bg',
                'theme_sidebar_text',
                'theme_sidebar_active',
                'theme_header_bg',
                'theme_success',
                'theme_warning',
                'theme_danger',
                'theme_info',
                'theme_font_family',
                'theme_radius',
            ])
            ->delete();
    }
};