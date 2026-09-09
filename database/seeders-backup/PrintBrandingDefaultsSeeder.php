<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class PrintBrandingDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'print_template' => [
                'value' => 'modern',
                'type' => 'string',
                'label' => 'نمط المستند',
                'description' => 'النمط الافتراضي للمستندات المطبوعة.',
            ],
            'print_primary_color' => [
                'value' => '#d6a925',
                'type' => 'string',
                'label' => 'لون الطباعة الرئيسي',
                'description' => 'اللون الرئيسي للهوية المطبوعة.',
            ],
            'print_secondary_color' => [
                'value' => '#111827',
                'type' => 'string',
                'label' => 'لون الطباعة الثانوي',
                'description' => 'لون العناوين والعناصر الثانوية.',
            ],
            'print_text_color' => [
                'value' => '#1f2937',
                'type' => 'string',
                'label' => 'لون النص',
                'description' => 'لون النص الأساسي داخل المستندات.',
            ],
            'print_logo_position' => [
                'value' => 'right',
                'type' => 'string',
                'label' => 'موضع الشعار',
                'description' => 'يمين أو وسط أو يسار رأس المستند.',
            ],
            'print_logo_size' => [
                'value' => 90,
                'type' => 'integer',
                'label' => 'حجم الشعار',
                'description' => 'حجم الشعار بالبكسل.',
            ],
            'print_paper_size' => [
                'value' => 'A4',
                'type' => 'string',
                'label' => 'حجم الورق',
                'description' => 'A4 أو A5 أو 80mm.',
            ],
            'print_show_logo' => [
                'value' => 1,
                'type' => 'boolean',
                'label' => 'إظهار الشعار',
                'description' => 'إظهار شعار المنشأة في المستندات.',
            ],
            'print_show_business_info' => [
                'value' => 1,
                'type' => 'boolean',
                'label' => 'إظهار بيانات المنشأة',
                'description' => 'العنوان والهاتف والبريد والرقم الضريبي.',
            ],
            'print_show_document_number' => [
                'value' => 1,
                'type' => 'boolean',
                'label' => 'إظهار رقم المستند',
                'description' => 'إظهار رقم أو كود المستند داخل الرأس.',
            ],
            'print_show_signatures' => [
                'value' => 1,
                'type' => 'boolean',
                'label' => 'إظهار التواقيع',
                'description' => 'إظهار أماكن التوقيع أسفل المستند.',
            ],
            'print_show_stamp' => [
                'value' => 0,
                'type' => 'boolean',
                'label' => 'إظهار الختم',
                'description' => 'إظهار الختم المرفوع في أسفل المستند.',
            ],
            'print_show_footer' => [
                'value' => 1,
                'type' => 'boolean',
                'label' => 'إظهار التذييل',
                'description' => 'إظهار نص التذييل أسفل المستند.',
            ],
            'print_footer_text' => [
                'value' => '',
                'type' => 'string',
                'label' => 'نص التذييل',
                'description' => 'نص اختياري يظهر أسفل المستند.',
            ],
            'print_logo' => [
                'value' => '',
                'type' => 'string',
                'label' => 'شعار الطباعة',
                'description' => 'شعار خاص بالمستندات إن وجد.',
            ],
            'print_stamp' => [
                'value' => '',
                'type' => 'string',
                'label' => 'ختم الطباعة',
                'description' => 'صورة الختم المستخدمة في المستندات.',
            ],
        ];

        foreach ($defaults as $key => $meta) {
            $setting = SystemSetting::query()
                ->firstOrNew(['key' => $key]);

            if (! $setting->exists) {
                $setting->value = (string) $meta['value'];
            }

            $setting->type =
                $setting->type ?: $meta['type'];

            $setting->group =
                $setting->group ?: 'print_branding';

            $setting->label =
                $setting->label ?: $meta['label'];

            $setting->description =
                $setting->description
                ?: $meta['description'];

            $setting->save();
        }

        SystemSetting::flushCache();
    }
}
