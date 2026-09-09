<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $settings = [
            [
                'key' => 'theme_sidebar_footer_bg',
                'value' => '#FFFFFF',
                'type' => 'color',
                'group' => 'theme',
                'label' => 'خلفية منطقة المستخدم أسفل القائمة',
                'description' => 'لون خلفية الجزء السفلي من Sidebar الذي يحتوي اسم المستخدم والدور.',
            ],
            [
                'key' => 'theme_sidebar_footer_text',
                'value' => '#172435',
                'type' => 'color',
                'group' => 'theme',
                'label' => 'لون اسم المستخدم أسفل القائمة',
                'description' => 'لون اسم المستخدم داخل المنطقة السفلية للقائمة الجانبية.',
            ],
            [
                'key' => 'theme_sidebar_footer_muted',
                'value' => '#687482',
                'type' => 'color',
                'group' => 'theme',
                'label' => 'لون الدور أسفل القائمة',
                'description' => 'لون الدور مثل Admin داخل منطقة المستخدم.',
            ],
            [
                'key' => 'theme_sidebar_footer_border',
                'value' => '#DDE2E7',
                'type' => 'color',
                'group' => 'theme',
                'label' => 'حد منطقة المستخدم أسفل القائمة',
                'description' => 'لون الخط الفاصل أعلى منطقة حساب المستخدم.',
            ],
            [
                'key' => 'theme_sidebar_footer_icon',
                'value' => '#687482',
                'type' => 'color',
                'group' => 'theme',
                'label' => 'لون زر تسجيل الخروج أسفل القائمة',
                'description' => 'لون أيقونة تسجيل الخروج داخل Sidebar footer.',
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
                'theme_sidebar_footer_bg',
                'theme_sidebar_footer_text',
                'theme_sidebar_footer_muted',
                'theme_sidebar_footer_border',
                'theme_sidebar_footer_icon',
            ])
            ->delete();
    }
};