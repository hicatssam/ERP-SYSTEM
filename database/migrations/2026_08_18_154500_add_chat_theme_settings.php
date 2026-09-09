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
                'key' => 'chat_background_image',
                'value' => '',
                'type' => 'image',
                'group' => 'chat',
                'label' => 'صورة خلفية المحادثة',
                'description' => 'صورة تظهر خلف الرسائل داخل مساحة المحادثة.',
            ],
            [
                'key' => 'chat_background_color',
                'value' => '#F5F6F8',
                'type' => 'color',
                'group' => 'chat',
                'label' => 'لون خلفية المحادثة',
                'description' => 'اللون الأساسي خلف الرسائل، ويستخدم أيضًا عند عدم رفع صورة.',
            ],
            [
                'key' => 'chat_background_overlay',
                'value' => '20',
                'type' => 'integer',
                'group' => 'chat',
                'label' => 'تعتيم صورة الخلفية',
                'description' => 'درجة التعتيم فوق صورة الخلفية من 0 إلى 100.',
            ],
            [
                'key' => 'chat_channels_bg',
                'value' => '#FFFFFF',
                'type' => 'color',
                'group' => 'chat',
                'label' => 'خلفية قائمة القنوات',
                'description' => 'لون الجزء الذي يعرض فروع وقنوات المحادثة.',
            ],
            [
                'key' => 'chat_channels_header_bg',
                'value' => '#FFFFFF',
                'type' => 'color',
                'group' => 'chat',
                'label' => 'خلفية عنوان قائمة القنوات',
                'description' => 'لون رأس قائمة المحادثات.',
            ],
            [
                'key' => 'chat_channel_active_bg',
                'value' => '#FFF4E8',
                'type' => 'color',
                'group' => 'chat',
                'label' => 'خلفية القناة النشطة',
                'description' => 'لون القناة المحددة حاليًا.',
            ],
            [
                'key' => 'chat_channel_text',
                'value' => '#172435',
                'type' => 'color',
                'group' => 'chat',
                'label' => 'لون نص القنوات',
                'description' => 'لون أسماء الفروع داخل قائمة المحادثات.',
            ],
            [
                'key' => 'chat_channel_muted',
                'value' => '#687482',
                'type' => 'color',
                'group' => 'chat',
                'label' => 'لون النص الثانوي للقنوات',
                'description' => 'لون آخر رسالة والوقت والنصوص الثانوية.',
            ],
            [
                'key' => 'chat_conversation_header_bg',
                'value' => '#FFFFFF',
                'type' => 'color',
                'group' => 'chat',
                'label' => 'خلفية رأس المحادثة',
                'description' => 'لون الشريط الذي يعرض اسم الفرع أعلى المحادثة.',
            ],
            [
                'key' => 'chat_message_mine_bg',
                'value' => '#C98516',
                'type' => 'color',
                'group' => 'chat',
                'label' => 'خلفية رسائلي',
                'description' => 'لون فقاعة الرسائل المرسلة من المستخدم الحالي.',
            ],
            [
                'key' => 'chat_message_mine_text',
                'value' => '#FFFFFF',
                'type' => 'color',
                'group' => 'chat',
                'label' => 'لون نص رسائلي',
                'description' => 'لون النص داخل رسائلي.',
            ],
            [
                'key' => 'chat_message_other_bg',
                'value' => '#FFFFFF',
                'type' => 'color',
                'group' => 'chat',
                'label' => 'خلفية رسائل الطرف الآخر',
                'description' => 'لون فقاعة الرسائل المستلمة.',
            ],
            [
                'key' => 'chat_message_other_text',
                'value' => '#172435',
                'type' => 'color',
                'group' => 'chat',
                'label' => 'لون نص الرسائل المستلمة',
                'description' => 'لون النص داخل الرسائل الواردة.',
            ],
            [
                'key' => 'chat_composer_bg',
                'value' => '#FFFFFF',
                'type' => 'color',
                'group' => 'chat',
                'label' => 'خلفية شريط الكتابة',
                'description' => 'لون المنطقة السفلية التي تحتوي حقل الرسالة والمرفقات.',
            ],
            [
                'key' => 'chat_input_bg',
                'value' => '#FFFFFF',
                'type' => 'color',
                'group' => 'chat',
                'label' => 'خلفية حقل الرسالة',
                'description' => 'لون مربع كتابة الرسالة.',
            ],
            [
                'key' => 'chat_input_text',
                'value' => '#172435',
                'type' => 'color',
                'group' => 'chat',
                'label' => 'لون نص حقل الرسالة',
                'description' => 'لون النص أثناء كتابة الرسالة.',
            ],
            [
                'key' => 'chat_border',
                'value' => '#DDE2E7',
                'type' => 'color',
                'group' => 'chat',
                'label' => 'لون حدود المحادثة',
                'description' => 'حدود القنوات والرسائل والحقول.',
            ],
            [
                'key' => 'chat_accent',
                'value' => '#C98516',
                'type' => 'color',
                'group' => 'chat',
                'label' => 'لون التمييز للمحادثة',
                'description' => 'الأيقونات والرد والعناصر البارزة.',
            ],
            [
                'key' => 'chat_send_button_bg',
                'value' => '#C98516',
                'type' => 'color',
                'group' => 'chat',
                'label' => 'لون زر الإرسال',
                'description' => 'خلفية زر إرسال الرسالة.',
            ],
            [
                'key' => 'chat_send_button_text',
                'value' => '#FFFFFF',
                'type' => 'color',
                'group' => 'chat',
                'label' => 'لون أيقونة زر الإرسال',
                'description' => 'لون السهم داخل زر الإرسال.',
            ],
            [
                'key' => 'chat_unread_badge_bg',
                'value' => '#E22929',
                'type' => 'color',
                'group' => 'chat',
                'label' => 'لون عداد الرسائل غير المقروءة',
                'description' => 'خلفية Badge الرسائل غير المقروءة.',
            ],
            [
                'key' => 'chat_unread_badge_text',
                'value' => '#FFFFFF',
                'type' => 'color',
                'group' => 'chat',
                'label' => 'لون نص عداد الرسائل',
                'description' => 'لون الرقم داخل Badge الرسائل غير المقروءة.',
            ],
            [
                'key' => 'chat_bubble_radius',
                'value' => '14',
                'type' => 'integer',
                'group' => 'chat',
                'label' => 'استدارة فقاعات الرسائل',
                'description' => 'من 0 إلى 30 بكسل.',
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
                'chat_background_image',
                'chat_background_color',
                'chat_background_overlay',
                'chat_channels_bg',
                'chat_channels_header_bg',
                'chat_channel_active_bg',
                'chat_channel_text',
                'chat_channel_muted',
                'chat_conversation_header_bg',
                'chat_message_mine_bg',
                'chat_message_mine_text',
                'chat_message_other_bg',
                'chat_message_other_text',
                'chat_composer_bg',
                'chat_input_bg',
                'chat_input_text',
                'chat_border',
                'chat_accent',
                'chat_send_button_bg',
                'chat_send_button_text',
                'chat_unread_badge_bg',
                'chat_unread_badge_text',
                'chat_bubble_radius',
            ])
            ->delete();
    }
};