<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class CustomerDisplaySettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'customer_display_background_image',
                'value' => '',
                'type' => 'image',
                'group' => 'customer_display',
                'label' => 'خلفية شاشة الطلبات',
                'description' => 'صورة خلفية كاملة لشاشة عرض الطلبات. يفضّل 1920×1080 أو أعلى.',
            ],
            [
                'key' => 'customer_display_background_color',
                'value' => '#090909',
                'type' => 'color',
                'group' => 'customer_display',
                'label' => 'لون الخلفية الأساسي',
                'description' => 'يظهر كلون أساسي أو عند عدم وجود صورة خلفية.',
            ],
            [
                'key' => 'customer_display_overlay_color',
                'value' => '#000000',
                'type' => 'color',
                'group' => 'customer_display',
                'label' => 'لون طبقة التعتيم',
                'description' => 'طبقة فوق صورة الخلفية لتحسين وضوح النص والبطاقات.',
            ],
            [
                'key' => 'customer_display_overlay_opacity',
                'value' => '72',
                'type' => 'integer',
                'group' => 'customer_display',
                'label' => 'شفافية طبقة التعتيم %',
                'description' => 'من 0 إلى 95. القيمة الأعلى تجعل الخلفية أغمق.',
            ],
            [
                'key' => 'customer_display_header_bg',
                'value' => '#090909',
                'type' => 'color',
                'group' => 'customer_display',
                'label' => 'لون شريط الرأس',
                'description' => 'خلفية الشعار والفرع والساعة وأزرار الشاشة.',
            ],
            [
                'key' => 'customer_display_panel_bg',
                'value' => '#111111',
                'type' => 'color',
                'group' => 'customer_display',
                'label' => 'لون لوحات الحالات',
                'description' => 'خلفية لوحتي قيد التحضير وجاهز للاستلام.',
            ],
            [
                'key' => 'customer_display_card_bg',
                'value' => '#181818',
                'type' => 'color',
                'group' => 'customer_display',
                'label' => 'لون بطاقة الطلب',
                'description' => 'لون بطاقات أرقام الطلبات داخل الشاشة.',
            ],
            [
                'key' => 'customer_display_text_color',
                'value' => '#FFFFFF',
                'type' => 'color',
                'group' => 'customer_display',
                'label' => 'لون النص الأساسي',
                'description' => 'لون العناوين وأرقام الطلبات.',
            ],
            [
                'key' => 'customer_display_muted_color',
                'value' => '#A3A3A3',
                'type' => 'color',
                'group' => 'customer_display',
                'label' => 'لون النص الثانوي',
                'description' => 'للتاريخ ونوع الخدمة والمعلومات الأقل أهمية.',
            ],
            [
                'key' => 'customer_display_preparing_color',
                'value' => '#F0B429',
                'type' => 'color',
                'group' => 'customer_display',
                'label' => 'لون قيد التحضير',
                'description' => 'لون مؤشر وحالة الطلبات قيد التحضير.',
            ],
            [
                'key' => 'customer_display_ready_color',
                'value' => '#24C36B',
                'type' => 'color',
                'group' => 'customer_display',
                'label' => 'لون جاهز للاستلام',
                'description' => 'لون حالة الطلب الجاهز والتنبيه البصري.',
            ],
            [
                'key' => 'customer_display_accent_color',
                'value' => '#D7A51D',
                'type' => 'color',
                'group' => 'customer_display',
                'label' => 'اللون المميز',
                'description' => 'يستخدم في اسم الفرع والتفاصيل البارزة.',
            ],
            [
                'key' => 'customer_display_border_color',
                'value' => '#2A2A2A',
                'type' => 'color',
                'group' => 'customer_display',
                'label' => 'لون الحدود',
                'description' => 'حدود اللوحات والبطاقات.',
            ],
            [
                'key' => 'customer_display_panel_opacity',
                'value' => '92',
                'type' => 'integer',
                'group' => 'customer_display',
                'label' => 'شفافية اللوحات %',
                'description' => 'من 35 إلى 100. تقل الشفافية لإظهار الخلفية أكثر.',
            ],
            [
                'key' => 'customer_display_glass_blur',
                'value' => '8',
                'type' => 'integer',
                'group' => 'customer_display',
                'label' => 'ضبابية الخلفية خلف اللوحات',
                'description' => 'قيمة من 0 إلى 30 بكسل.',
            ],
            [
                'key' => 'customer_display_radius',
                'value' => '22',
                'type' => 'integer',
                'group' => 'customer_display',
                'label' => 'استدارة البطاقات',
                'description' => 'قيمة من 0 إلى 40 بكسل.',
            ],
            [
                'key' => 'customer_display_logo_size',
                'value' => '54',
                'type' => 'integer',
                'group' => 'customer_display',
                'label' => 'حجم الشعار',
                'description' => 'من 32 إلى 120 بكسل.',
            ],
            [
                'key' => 'customer_display_order_number_size',
                'value' => '70',
                'type' => 'integer',
                'group' => 'customer_display',
                'label' => 'حجم رقم الطلب',
                'description' => 'من 36 إلى 120 بكسل على الشاشات الكبيرة.',
            ],
            [
                'key' => 'customer_display_show_service_type',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'customer_display',
                'label' => 'عرض نوع الخدمة',
                'description' => 'إظهار داخل المطعم / سفري أسفل رقم الطلب.',
            ],
            [
                'key' => 'customer_display_show_table',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'customer_display',
                'label' => 'عرض الطاولة',
                'description' => 'إظهار رقم/اسم الطاولة لطلبات داخل المطعم.',
            ],
            [
                'key' => 'customer_display_show_clock',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'customer_display',
                'label' => 'عرض الساعة والتاريخ',
                'description' => 'إظهار الساعة والتاريخ في الشريط العلوي.',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        SystemSetting::flushCache();
    }
}
